<?php
/**
 * Lists real on-disk artist/composer folders directly under a chosen base folder (a subdirectory
 * of fisheye_disk_storage_root's own Music/) that don't have a matching gallery yet, so an admin
 * can create the gallery before ever importing the albums inside it - same one-off "create the
 * gallery first, cheap/instant" step load_collection.php already established for Films,
 * load_album.php then handles the (more expensive) album import into that gallery. This page's
 * own icon lives on the top-level "Music" gallery only (music_gallery_icons_inc.tpl's "Add Music
 * Collection"), same gating as Film's own "Load Collections" icon.
 *
 * No base folder hardcoded to exactly two names - scans whatever real subdirectories actually sit
 * directly under Music/, so adding another base later needs no code change here.
 *
 * "Real collection" vs a single album sitting in its own folder - same distinction load_collection.
 * php makes for Films: a base folder holding more than one album unit (a subfolder containing at
 * least one recognised track file, or a CD-numbered subfolder) is offered; a folder holding just
 * one gets no gallery of its own; it can still be registered directly via FisheyeAlbum::
 * registerFromDisk() without one (same graceful-degrade FisheyeAlbum's own registerFromDisk()
 * already has for a missing gallery title).
 *
 * @package fisheye
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';
require_once dirname( __DIR__ ).'/liberty/plugins/mime.film.php';
// FISHEYEALBUM_TRACK_EXTENSIONS below is a plain namespaced const, not a class - referencing it
// alone doesn't trigger the autoloader the way instantiating FisheyeAlbum would.
require_once __DIR__.'/includes/classes/FisheyeAlbum.php';

global $gBitSystem, $gBitSmarty, $gBitDb;

$gBitSystem->verifyPermission( 'p_fisheye_admin' );

function load_music_gallery_id_for_title( string $pTitle ) {
	global $gBitDb;
	return $gBitDb->getOne(
		"SELECT fg.gallery_id FROM `".BIT_DB_PREFIX."fisheye_gallery` fg
		 INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.content_id = fg.content_id
		 WHERE lc.title = ?",
		[ $pTitle ]
	);
}

// An artist/composer folder's own "album unit" count - a subfolder counts as one album unit if it
// either directly contains a recognised track file, or looks like a disc subfolder (CD1/CD2/etc,
// same convention FisheyeAlbum::registerFromDisk() already scans for) - more than one makes this a
// real collection worth its own gallery.
function load_music_unit_count( string $pDir ): int {
	$units = 0;
	foreach( scandir( $pDir ) ?: [] as $entry ) {
		if( $entry === '.' || $entry === '..' ) {
			continue;
		}
		$fullPath = $pDir.$entry.'/';
		if( !is_dir( $fullPath ) ) {
			continue;
		}
		foreach( scandir( $fullPath ) ?: [] as $subEntry ) {
			$subPath = $fullPath.$subEntry;
			$ext = strtolower( pathinfo( $subEntry, PATHINFO_EXTENSION ) );
			// A disc subfolder (CD1/CD2/etc, same convention FisheyeAlbum::registerFromDisk()
			// scans for) holds its own track files one level deeper - check inside it too, or a
			// multi-disc album would otherwise count as zero units here.
			if( in_array( $ext, FISHEYEALBUM_TRACK_EXTENSIONS, true )
				|| ( is_dir( $subPath ) && preg_match( '/^CD\s*\d+/i', $subEntry ) && array_filter(
					scandir( $subPath ) ?: [],
					fn( $discEntry ) => in_array( strtolower( pathinfo( $discEntry, PATHINFO_EXTENSION ) ), FISHEYEALBUM_TRACK_EXTENSIONS, true )
				) )
			) {
				$units++;
				break;
			}
		}
	}
	return $units;
}

$topGalleryId = FisheyeGallery::getTopGalleryId( 'Music' );
$root = \Bitweaver\Liberty\mime_film_get_storage_root();
$musicDir = $root.'Music/';

$baseFolders = [];
if( !empty( $root ) && is_dir( $musicDir ) ) {
	foreach( scandir( $musicDir ) ?: [] as $entry ) {
		if( $entry === '.' || $entry === '..' || !is_dir( $musicDir.$entry ) ) {
			continue;
		}
		$baseFolders[] = $entry;
	}
	natsort( $baseFolders );
}

$base = trim( (string)( $_REQUEST['base'] ?? '' ) );
if( $base !== '' && !in_array( $base, $baseFolders, true ) ) {
	$base = '';
}
$baseDir = $base !== '' ? $musicDir.$base.'/' : null;

$result = null;
if( $base !== '' && !empty( $_REQUEST['fCreate'] ) ) {
	$result = [ 'created' => [], 'errors' => [] ];
	foreach( (array)( $_REQUEST['selected'] ?? [] ) as $folderName ) {
		$folderName = trim( (string)$folderName );
		if( empty( $folderName ) || !is_dir( $baseDir.$folderName ) ) {
			continue;
		}
		if( load_music_gallery_id_for_title( $folderName ) ) {
			continue;
		}
		$gallery = new FisheyeGallery();
		$storeHash = [ 'title' => $folderName, 'gallery_pagination' => FISHEYE_PAGINATION_MUSIC_GRID ];
		if( $gallery->store( $storeHash ) ) {
			$gallery->storePreference( 'gallery_pagination', FISHEYE_PAGINATION_MUSIC_GRID );
			if( $topGalleryId ) {
				$gallery->addToGalleries( [ $topGalleryId ] );
			}
			$result['created'][] = [ 'folder' => $folderName, 'gallery_id' => $gallery->mGalleryId ];
		} else {
			$result['errors'][] = [ 'folder' => $folderName, 'error' => implode( '; ', $gallery->mErrors ) ];
		}
	}
}

$candidates = [];
if( $baseDir && is_dir( $baseDir ) ) {
	$entries = scandir( $baseDir );
	natsort( $entries );
	foreach( $entries as $entry ) {
		if( $entry === '.' || $entry === '..' || !is_dir( $baseDir.$entry ) ) {
			continue;
		}
		if( load_music_unit_count( $baseDir.$entry.'/' ) <= 1 ) {
			continue;
		}
		if( load_music_gallery_id_for_title( $entry ) ) {
			continue;
		}
		$candidates[] = [ 'folder' => $entry ];
	}
}

$topGalleryUrlHash = [ 'gallery_id' => $topGalleryId ];
$gBitSmarty->assign( 'topGalleryUrl', FisheyeGallery::getDisplayUrlFromHash( $topGalleryUrlHash ) );
$gBitSmarty->assign( 'baseFolders', $baseFolders );
$gBitSmarty->assign( 'base', $base );
$gBitSmarty->assign( 'candidates', $candidates );
$gBitSmarty->assign( 'result', $result );

$gBitSystem->display( 'bitpackage:fisheye/load_music.tpl', KernelTools::tra( 'Load Music Collections' ), [ 'display_mode' => 'edit' ] );
