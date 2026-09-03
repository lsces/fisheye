<?php
/**
 * Lists films found under the storage root's Films/ folder (or one of its real subfolders, e.g.
 * Films/Harry Potter/, standing in for a collection) that aren't registered yet, so an admin can
 * pick a batch to import rather than the whole folder at once - deliberately capped
 * (LOAD_FILM_LIMIT below), not a "scan and import everything" tool. Registration itself
 * (FisheyeFilm::registerFromDisk() - store/gallery-link/Plex-backfill) is cheap per film; the one
 * genuinely expensive step, thumbnail generation, isn't triggered here at all - it happens lazily
 * per film via mime_film_get_thumbnail_url() whenever that film's own page is first viewed, not
 * as part of this bulk import. See fisheye.md for the fuller reasoning (session log entry
 * matching this file's own introduction) - a batch import followed by a page that displays many
 * freshly-imported films together could still trigger a burst of synchronous ffmpeg calls at
 * that later point; not addressed by this page.
 *
 * Folder scoping (Lester, 2026-09-03): a collection isn't modelled as anything more formal than
 * "a real subfolder under Films/ whose name matches a gallery's own title" - see the
 * $topGalleryId/gallery_id/folder handling below for the three ways this page ends up
 * scoped to one.
 *
 * @package fisheye
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitDb, $gLibertySystem;

$gBitSystem->verifyPermission( 'p_fisheye_admin' );

require_once dirname( __DIR__ ).'/liberty/plugins/mime.film.php';
if( !$gLibertySystem->isPluginActive( 'mimefilm' ) ) {
	$gLibertySystem->setActivePlugin( 'mimefilm' );
}

const LOAD_FILM_LIMIT = 20;
const LOAD_FILM_EXTENSIONS = [ 'mkv', 'mp4', 'm4v', 'avi' ];
// Resolved by title, not hardcoded - see FisheyeGallery::getTopGalleryId()'s own docblock for
// why (was a literal, install-order-dependent "1" here until 2026-09-03).
$topGalleryId = FisheyeGallery::getTopGalleryId( 'Films' );

$root = \Bitweaver\Liberty\mime_film_get_storage_root();
$filmsDir = $root.'Films/';

// Which folder to scan and which gallery to link into are deliberately independent - a gallery's
// own title is NOT assumed to match a folder name (that was tried, too rigid the moment a
// collection's folder doesn't happen to match its gallery title exactly). Instead:
// - ?gallery_id=N (N != top-level) - the "Load Films" icon on a collection gallery's own page
//   (film_grid's film_gallery_icons_inc.tpl) links here with its own gallery_id. That gallery is
//   the link target from here on, however the request got here - carried through as a hidden
//   field / query param regardless of which folder subsequently gets chosen.
// - ?folder=Name - which folder to actually scan, chosen explicitly from the picker below rather
//   than assumed. If no $scopeGallery is known, linking falls back to the old folder-name-matches-
//   gallery-title convention (the only option left when there's no explicit target).
// - neither yet, or gallery_id given but no folder yet - show the folder picker instead of
//   scanning anything; reloading with a folder in the link is what actually selects one.
$galleryIdParam = (int)( $_REQUEST['gallery_id'] ?? 0 );
$folderParam = trim( (string)( $_REQUEST['folder'] ?? '' ) );
$folderName = $folderParam !== '' ? $folderParam : null;

$scopeGallery = null;
if( $galleryIdParam && $galleryIdParam !== $topGalleryId ) {
	$scopeGallery = new FisheyeGallery( $galleryIdParam );
	$scopeGallery->load();
	if( !$scopeGallery->isValid() ) {
		$scopeGallery = null;
	}
}
$galleryTitle = $scopeGallery ? $scopeGallery->getTitle() : ( $folderName ?? 'Films' );

// A specific collection gallery was the entry point but no folder's been picked yet - show the
// picker only, don't guess/scan Films/ root as if it were this gallery's own content.
$needsFolderChoice = ( $scopeGallery !== null ) && ( $folderName === null );

$scanDir = $needsFolderChoice ? null : $filmsDir.( $folderName !== null ? $folderName.'/' : '' );
$scanRelativePrefix = 'Films/'.( $folderName !== null ? $folderName.'/' : '' );

// Real subfolders under Films/ - offered as a picker whenever a folder hasn't been chosen yet
// (true top level, or a collection gallery still needing one) - collections aren't nested, so
// this never applies once inside a chosen folder.
$subfolders = [];
if( $folderName === null && is_dir( $filmsDir ) ) {
	foreach( scandir( $filmsDir ) as $entry ) {
		if( $entry === '.' || $entry === '..' || !is_dir( $filmsDir.$entry ) ) {
			continue;
		}
		$subfolders[] = $entry;
	}
	natsort( $subfolders );
}

$result = null;
if( !empty( $_REQUEST['fImport'] ) ) {
	$fetchImages = !empty( $_REQUEST['fetch_images'] );
	$batchStart = microtime( true );
	$result = [ 'imported' => [], 'already' => [], 'errors' => [], 'fetch_images' => $fetchImages ];
	foreach( (array)( $_REQUEST['selected'] ?? [] ) as $relativePath ) {
		$relativePath = trim( (string)$relativePath );
		if( empty( $relativePath ) || !is_file( $root.$relativePath ) ) {
			continue;
		}
		$filmStart = microtime( true );
		$row = FisheyeFilm::registerFromDisk( $relativePath, null, $fetchImages, $galleryTitle );
		$elapsed = round( microtime( true ) - $filmStart, 2 );
		if( !empty( $row['already'] ) ) {
			$result['already'][] = [ 'path' => $relativePath, 'content_id' => $row['already'] ];
		} elseif( !empty( $row['created'] ) ) {
			$result['imported'][] = [ 'path' => $relativePath, 'content_id' => $row['created'], 'plex' => $row['plex'], 'images' => $row['images'] ?? null, 'seconds' => $elapsed ];
		} else {
			$result['errors'][] = [ 'path' => $relativePath, 'error' => $row['error'] ?? KernelTools::tra( 'Unknown error' ) ];
		}
	}
	$result['total_seconds'] = round( microtime( true ) - $batchStart, 2 );
}

// Re-scan every time (including right after an import) so the list always reflects what's
// actually still outstanding - cheap: a capped directory listing plus one indexed lookup per
// candidate file, not a concern at this scale.
$candidates = [];
if( !empty( $root ) && $scanDir !== null && is_dir( $scanDir ) ) {
	$files = scandir( $scanDir );
	natsort( $files );
	foreach( $files as $file ) {
		if( count( $candidates ) >= LOAD_FILM_LIMIT ) {
			break;
		}
		$fullPath = $scanDir.$file;
		if( !is_file( $fullPath ) ) {
			continue;
		}
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if( !in_array( $ext, LOAD_FILM_EXTENSIONS, true ) ) {
			continue;
		}
		$relativePath = $scanRelativePrefix.$file;
		$existingContentId = $gBitDb->getOne(
			"SELECT la.content_id FROM liberty_attachments la INNER JOIN liberty_files lf ON lf.file_id = la.foreign_id WHERE la.attachment_plugin_guid = 'mimefilm' AND lf.file_name = ?",
			[ $relativePath ]
		);
		if( $existingContentId ) {
			continue;
		}
		$candidates[] = [
			'relative_path' => $relativePath,
			'title'         => pathinfo( $file, PATHINFO_FILENAME ),
		];
	}
}

$gBitSmarty->assign( 'storageRoot', $root );
$gBitSmarty->assign( 'filmsDir', $scanDir );
$gBitSmarty->assign( 'folderName', $folderName );
$gBitSmarty->assign( 'scopeGallery', $scopeGallery );
$gBitSmarty->assign( 'needsFolderChoice', $needsFolderChoice );
$gBitSmarty->assign( 'subfolders', $subfolders );
$gBitSmarty->assign( 'candidates', $candidates );
$gBitSmarty->assign( 'candidateLimit', LOAD_FILM_LIMIT );
$gBitSmarty->assign( 'result', $result );

$gBitSystem->display( 'bitpackage:fisheye/load_film.tpl', KernelTools::tra( 'Load Films' ), [ 'display_mode' => 'edit' ] );
