<?php
/**
 * Lists films found under the storage root's Films/ folder that aren't registered yet, so an
 * admin can pick a batch to import rather than the whole folder at once - deliberately capped
 * (LOAD_FILM_LIMIT below), not a "scan and import everything" tool. Registration itself
 * (FisheyeFilm::registerFromDisk() - store/gallery-link/Plex-backfill) is cheap per film; the one
 * genuinely expensive step, thumbnail generation, isn't triggered here at all - it happens lazily
 * per film via mime_film_get_thumbnail_url() whenever that film's own page is first viewed, not
 * as part of this bulk import. See fisheye.md for the fuller reasoning (session log entry
 * matching this file's own introduction) - a batch import followed by a page that displays many
 * freshly-imported films together could still trigger a burst of synchronous ffmpeg calls at
 * that later point; not addressed by this page.
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

$root = \Bitweaver\Liberty\mime_film_get_storage_root();
$filmsDir = $root.'Films/';

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
		$row = FisheyeFilm::registerFromDisk( $relativePath, null, $fetchImages );
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
if( !empty( $root ) && is_dir( $filmsDir ) ) {
	$files = scandir( $filmsDir );
	natsort( $files );
	foreach( $files as $file ) {
		if( count( $candidates ) >= LOAD_FILM_LIMIT ) {
			break;
		}
		$fullPath = $filmsDir.$file;
		if( !is_file( $fullPath ) ) {
			continue;
		}
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if( !in_array( $ext, LOAD_FILM_EXTENSIONS, true ) ) {
			continue;
		}
		$relativePath = 'Films/'.$file;
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
$gBitSmarty->assign( 'filmsDir', $filmsDir );
$gBitSmarty->assign( 'candidates', $candidates );
$gBitSmarty->assign( 'candidateLimit', LOAD_FILM_LIMIT );
$gBitSmarty->assign( 'result', $result );

$gBitSystem->display( 'bitpackage:fisheye/load_film.tpl', KernelTools::tra( 'Load Films' ), [ 'display_mode' => 'edit' ] );
