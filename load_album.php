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
 * Folder resolution: a collection gallery's title is normally expected to match a real folder
 * directly under fisheye_disk_storage_root's own Music/ (no separate config key for this - same
 * fisheye_disk_storage_root as Films) - same one-level layout load_music.php's own candidate scan
 * uses. A box set's own nested gallery (FisheyeAlbum::createBoxSetGallery()) sits one level deeper
 * than that though - Music/<artist>/<box set>/, not Music/<box set>/ - so a direct lookup falling
 * through tries the gallery's own real parent gallery next, same one level FisheyeAlbum's own
 * getParentGalleries() call already covers (a box set is never nested more than one level deep).
 *
 * Selecting a box-set-shaped candidate here (still has a real CDxx subfolder - see
 * FisheyeAlbum::isBoxSetFolder()) creates its own nested gallery rather than registering the whole
 * folder as one (very noisy) multi-hundred-track album - deliberately just the gallery, no track
 * scanning at all yet, so picking a handful of discs to import at a time (a normal load_album.php
 * visit pointed at that new gallery, CDxx folders showing up as ordinary candidates) never has to
 * wait on scanning every track in the whole box set first.
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

const LOAD_ALBUM_LIMIT = 20;

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
$artistRelative = null;
if( !empty( $root ) ) {
	if( is_dir( $musicDir.$galleryTitle.'/' ) ) {
		$artistDir = $musicDir.$galleryTitle.'/';
		$artistRelative = 'Music/'.$galleryTitle.'/';
	} else {
		// Not directly under Music/ - a box set's own nested gallery, one level deeper under its
		// real parent (the artist/composer gallery) instead.
		$parentGalleries = $gallery->getParentGalleries();
		$parentTitle = $parentGalleries ? current( $parentGalleries )['title'] : null;
		if( $parentTitle && is_dir( $musicDir.$parentTitle.'/'.$galleryTitle.'/' ) ) {
			$artistDir = $musicDir.$parentTitle.'/'.$galleryTitle.'/';
			$artistRelative = 'Music/'.$parentTitle.'/'.$galleryTitle.'/';
		}
	}
}

$importResult = null;
if( !empty( $_REQUEST['fImportAlbums'] ) ) {
	$importResult = [ 'created' => [], 'boxsets' => [], 'errors' => [] ];
	foreach( (array)( $_REQUEST['selected'] ?? [] ) as $albumFolder ) {
		// Same detoxify() decode as load_film.php - HTML-escapes every $_REQUEST value, which
		// breaks a raw filesystem lookup like this one for any folder name containing &, <, or >.
		$albumFolder = htmlspecialchars_decode( trim( (string)$albumFolder ), ENT_NOQUOTES );
		if( $albumFolder === '' ) {
			continue;
		}
		// A CDxx subfolder still sitting inside means Lester deliberately kept it as a box set of
		// distinct recordings rather than flattening it into one multi-disc album (see
		// FisheyeAlbum::isBoxSetFolder()'s own docblock) - create its own nested gallery only, no
		// track scanning yet (see this file's own docblock for why: letting a subset of discs get
		// picked afterward via an ordinary load_album.php visit, rather than every disc's every
		// track scanning in this one request).
		if( FisheyeAlbum::isBoxSetFolder( $artistDir.$albumFolder.'/' ) ) {
			$row = FisheyeAlbum::createBoxSetGallery( $artistRelative.$albumFolder, $galleryTitle );
			if( !empty( $row['error'] ) ) {
				$importResult['errors'][] = [ 'folder' => $albumFolder, 'error' => $row['error'] ];
			} else {
				// Same getDisplayUrlFromHash() route the top-level "Music" gallery link elsewhere
				// on this page already uses, not a hardcoded view.php?gallery_id= guess.
				$boxSetUrlHash = [ 'gallery_id' => $row['gallery_id'] ];
				$loadUrlHash = [ 'gallery_id' => $row['gallery_id'] ];
				$importResult['boxsets'][] = [
					'folder'   => $albumFolder,
					'url'      => FisheyeGallery::getDisplayUrlFromHash( $boxSetUrlHash ),
					'loadUrl'  => FISHEYE_PKG_URL.'load_album.php?gallery_id='.$row['gallery_id'],
					'already'  => !empty( $row['already'] ),
				];
			}
			continue;
		}
		// A bare CDxx folder being imported directly means this gallery is itself a box set's own
		// nested gallery (see above) - the title itself stays the plain folder name (registerFromDisk()'s
		// own default, required so getImageStorageRoot() can resolve this disc's real folder back
		// from it), but its real content (getDiscTitle()) goes into the description instead, same
		// field view_album.tpl already renders for every other album.
		$discTitle = preg_match( '/^CD\s*\d+/i', $albumFolder ) ? FisheyeAlbum::getDiscTitle( $artistDir.$albumFolder.'/' ) : null;
		$row = FisheyeAlbum::registerFromDisk( $artistRelative.$albumFolder, null, $galleryTitle, $discTitle );
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
		if( str_starts_with( $entry, '.' ) || !is_dir( $artistDir.$entry ) ) {
			continue;
		}
		if( !FisheyeAlbum::folderHasTracks( $artistDir.$entry.'/' ) ) {
			continue; // an Artwork/Videos/scans-style extras folder, not a real album
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
