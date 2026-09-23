<?php
/**
 * Batch-fetches a linked Discogs release for every already-registered album directly in this
 * gallery that has an 'mbid' xref (MB-matched) but no 'discogs' xref yet - see
 * FisheyeAlbum::fetchDiscogsLink()'s own docblock for how the lookup/storage works, and the
 * 'liberty_xref_item' row Lester added for content_type_guid='fisheyealbum' (matching
 * fisheyefilm/fisheyeseason/fisheyeprogram's own imdb/tmdb rows) for how it then renders as a real
 * link on view_album.php.
 *
 * Deliberately scoped to one gallery's own direct items only, same as load_album.php/load_video.php -
 * a box set or discography-category's own nested gallery (Studio/Live/...) is visited separately by
 * following its own link and running this page there too, rather than this page recursing into
 * subgalleries itself.
 *
 * One MusicBrainz API call per album, rate-limited to 1/sec (MB's own API guidance) - capped at
 * FETCH_DISCOGS_LIMIT per request so a big gallery doesn't turn into a multi-minute page load.
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitDb;

$gBitSystem->verifyPermission( 'p_fisheye_admin' );

const FETCH_DISCOGS_LIMIT = 20;

$galleryIdParam = (int)( $_REQUEST['gallery_id'] ?? 0 );
if( !$galleryIdParam ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No gallery specified.' ) );
}
// gallery_id here is fisheye_gallery's own PK (fg.gallery_id), matching load_album.php/
// load_video.php's own bootstrap and the icon link's own {$gContent->mGalleryId} - NOT content_id
// (the (null, $pContentId) constructor slot), which was this file's own original mistake.
$gallery = new FisheyeGallery( $galleryIdParam );
$gallery->load();
// isValid() alone doesn't prove load() found a real row - see load_album.php's own identical
// check for why.
if( !$gallery->isValid() || empty( $gallery->getTitle() ) ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No gallery exists with the given ID.' ) );
}
$galleryTitle = $gallery->getTitle();

$result = null;
if( !empty( $_REQUEST['fFetch'] ) ) {
	$result = [ 'found' => [], 'none' => [], 'errors' => [] ];
	foreach( (array)( $_REQUEST['selected'] ?? [] ) as $contentId ) {
		$contentId = (int)$contentId;
		if( !$contentId ) {
			continue;
		}
		$album = new FisheyeAlbum( null, $contentId );
		$album->load();
		if( !$album->isValid() ) {
			continue;
		}
		$row = $album->fetchDiscogsLink();
		if( !empty( $row['error'] ) ) {
			$result['errors'][] = [ 'title' => $album->getTitle(), 'error' => $row['error'] ];
		} elseif( !empty( $row['url'] ) ) {
			$result['found'][] = [ 'title' => $album->getTitle(), 'url' => $row['url'] ];
		} else {
			$result['none'][] = [ 'title' => $album->getTitle() ];
		}
		sleep( 1 ); // MusicBrainz API rate limit: max 1 request/second
	}
}

$candidates = $gBitDb->getAll(
	"SELECT lc.content_id, lc.title
	 FROM `fisheye_gallery_image_map` map
	 INNER JOIN `liberty_content` lc ON lc.content_id = map.item_content_id
	 INNER JOIN `liberty_xref` mb ON mb.content_id = lc.content_id AND mb.item = 'mbid'
	 LEFT JOIN `liberty_xref` dg ON dg.content_id = lc.content_id AND dg.item = 'discogs'
	 WHERE map.gallery_content_id = ? AND lc.content_type_guid = 'fisheyealbum' AND dg.xref_id IS NULL
	 ORDER BY lc.title",
	[ $gallery->mContentId ]
);
$candidates = array_slice( $candidates ?: [], 0, FETCH_DISCOGS_LIMIT );

$gBitSmarty->assign( 'galleryTitle', $galleryTitle );
$gBitSmarty->assign( 'galleryUrl', $gallery->getDisplayUrl() );
$gBitSmarty->assign( 'galleryIdParam', $galleryIdParam );
$gBitSmarty->assign( 'candidateLimit', FETCH_DISCOGS_LIMIT );
$gBitSmarty->assign( 'candidates', $candidates );
$gBitSmarty->assign( 'result', $result );

$gBitSystem->display( 'bitpackage:fisheye/fetch_discogs.tpl', KernelTools::tra( 'Fetch Discogs Links: ' ).$galleryTitle, [ 'display_mode' => 'edit' ] );
