<?php
/**
 * Dedicated view page for TV season content (FisheyeSeason) - the "matching pair" to
 * edit_season.php, alongside view_program.php/edit_program.php (Lester, 2026-09-02). Same shape
 * as view_film.php: facts panel via one flat allXrefs() pass (no group names hardcoded), plus
 * this season's own alternate poster/backdrop images strip. Adds an episode list (this season's
 * own 'episode' xref rows) and drops the video player - a season has no single file of its own.
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;
use Bitweaver\HttpStatusCodes;

require_once '../kernel/includes/setup_inc.php';
global $gBitSystem, $gBitSmarty;

$gBitSystem->verifyPackage( 'fisheye' );

$gContent = FisheyeImage::lookup( $_REQUEST );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No season exists with the given ID' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}
$gContent->verifyViewPermission();
$gContent->addHit();

// bucket this season's own xref data - same flat allXrefs() pass as view_film.php, no group names
// hardcoded (see that page's own comment for why that matters).
$gContent->loadXrefInfo();
$genres = $directors = $writers = $stars = [];
$contentRating = $durationMs = null;
$externalLinks = [];
$seasonImages = [];
$episodes = [];
if( $gContent->mXrefInfo ) {
	foreach( $gContent->mXrefInfo->allXrefs() as $xref ) {
		switch( $xref['item'] ) {
			case 'genre':          $genres[]     = $xref['xkey_ext']; break;
			case 'director':       $directors[]  = $xref['xkey_ext']; break;
			case 'writer':         $writers[]    = $xref['xkey_ext']; break;
			case 'star':           $stars[]      = $xref['xkey_ext']; break;
			case 'content_rating': $contentRating = $xref['xkey_ext']; break;
			case 'duration':       $durationMs    = (int)$xref['xkey_ext']; break;
			case 'image':          $seasonImages[] = [ 'xref_id' => $xref['xref_id'] ]; break;
			case 'episode':
				// no per-episode title/number data exists yet (xkey_ext is just the raw file
				// path) - shown as its own basename until that's built out.
				$episodes[] = [ 'title' => pathinfo( $xref['xkey_ext'], PATHINFO_FILENAME ) ];
				break;
		}
		if( !empty( $xref['cross_ref_href'] ) && !empty( $xref['xkey'] )) {
			$externalLinks[] = [
				'title' => $xref['xref_title'] ?? strtoupper( $xref['item'] ),
				'url'   => $xref['cross_ref_href'].$xref['xkey'],
			];
		}
	}
}
$gBitSmarty->assign( 'genres', $genres );
$gBitSmarty->assign( 'directors', $directors );
$gBitSmarty->assign( 'writers', $writers );
$gBitSmarty->assign( 'stars', $stars );
$gBitSmarty->assign( 'contentRating', $contentRating );
$gBitSmarty->assign( 'durationMs', $durationMs );
$gBitSmarty->assign( 'externalLinks', $externalLinks );
$gBitSmarty->assign( 'seasonImages', $seasonImages );
$gBitSmarty->assign( 'episodes', $episodes );

// parent show, for the breadcrumb line - same pattern as view_film.php.
$gGallery = null;
if( !empty( $_REQUEST['gallery_id'] ) && is_numeric( $_REQUEST['gallery_id'] )) {
	$gGallery = FisheyeGallery::lookup( $_REQUEST );
} elseif( $parents = $gContent->getParentGalleries() ) {
	$gal = current( $parents );
	$gGallery = new FisheyeGallery( $gal['gallery_id'] );
	$gGallery->load();
}
$gBitSmarty->assign( 'gGallery', $gGallery );
$gBitSmarty->assign( 'gContent', $gContent );

$gBitSystem->setCanonicalLink( $gContent->getDisplayUrl() );
$gBitSystem->setBrowserTitle( $gContent->getTitle() );
$gBitSystem->display( 'bitpackage:fisheye/view_season.tpl', null, [ 'display_mode' => 'display' ] );
