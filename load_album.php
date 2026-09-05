<?php
/**
 * Register album folders into an already-existing music collection gallery - one level, unlike
 * load_program.php's show->season nesting, since the gallery itself (an artist/composer folder,
 * e.g. "Bob Marley" or "Classic Composers") is already known from the gallery_id the calling
 * icon passes (music_gallery_icons_inc.tpl's "Load Album" button, shown on any collection gallery
 * below the top-level "Music" one - see FisheyeAlbum.php's own docblock for the wider
 * load_collection/load_discography plan this is the first piece of).
 *
 * No top-level discovery step the way load_program.php has one for shows - creating a NEW
 * collection gallery is "Add Music Collection" (load_music.php) on the top-level gallery's own
 * icon set instead; this page only ever lists album folders under an *existing* collection
 * gallery's own folder.
 *
 * Folder resolution: a collection gallery's title is expected to match a real folder directly
 * under one of the base folders under fisheye_disk_storage_root's own Music/ (no separate config
 * key for this - same fisheye_disk_storage_root as Films). All of Music/'s own subfolders are
 * checked; whichever actually contains a matching folder wins - same generic "don't hardcode
 * exactly two names" approach as load_music.php.
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitDb;

$gBitSystem->verifyPermission( 'p_fisheye_admin' );

// mime_film_get_storage_root() below is only auto-loaded via the LibertyMime attachment-plugin
// dispatch - same fix FisheyeProgram.php/FisheyeSeason.php/FisheyeFilm.php/FisheyeAlbum.php/
// load_collection.php all already needed.
require_once dirname( __DIR__ ).'/liberty/plugins/mime.film.php';

const LOAD_ALBUM_LIMIT = 40;

$galleryIdParam = (int)( $_REQUEST['gallery_id'] ?? 0 );
if( !$galleryIdParam ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No gallery specified.' ) );
}
$gallery = new FisheyeGallery( $galleryIdParam );
$gallery->load();
if( !$gallery->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No gallery exists with the given ID.' ) );
}
$galleryTitle = $gallery->getTitle();

$root = \Bitweaver\Liberty\mime_film_get_storage_root();
$musicDir = $root.'Music/';
$artistDir = null;
if( !empty( $root ) && is_dir( $musicDir ) ) {
	foreach( scandir( $musicDir ) ?: [] as $subDir ) {
		if( $subDir === '.' || $subDir === '..' || !is_dir( $musicDir.$subDir ) ) {
			continue;
		}
		$candidateDir = $musicDir.$subDir.'/'.$galleryTitle.'/';
		if( is_dir( $candidateDir ) ) {
			$artistDir = $candidateDir;
			$artistRelative = 'Music/'.$subDir.'/'.$galleryTitle.'/';
			break;
		}
	}
}

$importResult = null;
if( !empty( $_REQUEST['fImportAlbums'] ) ) {
	$importResult = [ 'created' => [], 'errors' => [] ];
	foreach( (array)( $_REQUEST['selected'] ?? [] ) as $albumFolder ) {
		$albumFolder = trim( (string)$albumFolder );
		if( $albumFolder === '' ) {
			continue;
		}
		$row = FisheyeAlbum::registerFromDisk( $artistRelative.$albumFolder, null, $galleryTitle );
		if( !empty( $row['error'] ) ) {
			$importResult['errors'][] = [ 'folder' => $albumFolder, 'error' => $row['error'] ];
		} else {
			$importResult['created'][] = [ 'folder' => $albumFolder, 'content_id' => $row['created'] ?? $row['already'], 'tracks' => $row['tracks'] ?? null, 'cover' => $row['cover'] ?? null ];
		}
	}
}

$candidates = [];
if( $artistDir ) {
	$entries = scandir( $artistDir );
	natsort( $entries );
	foreach( $entries as $entry ) {
		if( count( $candidates ) >= LOAD_ALBUM_LIMIT ) {
			break;
		}
		if( $entry === '.' || $entry === '..' || !is_dir( $artistDir.$entry ) ) {
			continue;
		}
		$existingContentId = $gBitDb->getOne(
			"SELECT content_id FROM liberty_content WHERE content_type_guid = 'fisheyealbum' AND title = ?",
			[ $entry ]
		);
		if( $existingContentId ) {
			continue;
		}
		$candidates[] = $entry;
	}
}

$gBitSmarty->assign( 'galleryTitle', $galleryTitle );
$gBitSmarty->assign( 'galleryUrl', $gallery->getDisplayUrl() );
$gBitSmarty->assign( 'galleryIdParam', $galleryIdParam );
$gBitSmarty->assign( 'artistDir', $artistDir );
$gBitSmarty->assign( 'candidateLimit', LOAD_ALBUM_LIMIT );
$gBitSmarty->assign( 'candidates', $candidates );
$gBitSmarty->assign( 'importResult', $importResult );

$gBitSystem->display( 'bitpackage:fisheye/load_album.tpl', KernelTools::tra( 'Load Albums: ' ).$galleryTitle, [ 'display_mode' => 'edit' ] );
