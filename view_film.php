<?php
/**
 * Dedicated view page for film content (mime.film.php-backed FisheyeImage rows) - deliberately
 * separate from view_image.php rather than folding film-specific rendering into the generic
 * photo view. See liberty.md's 2026-09-01 entries for the wider design.
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';
global $gBitSystem, $gBitSmarty;

$gBitSystem->verifyPackage( 'fisheye' );

$gContent = FisheyeImage::lookup( $_REQUEST );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No film exists with the given ID' ), 'error.tpl' );
}
$gContent->verifyViewPermission();
$gContent->addHit();

// bucket the film's xref metadata (all share x_group='metadata') into template-friendly arrays
$gContent->loadXrefInfo();
$genres = $directors = $writers = $stars = [];
$contentRating = $durationMs = null;
if( $gContent->mXrefInfo && !empty( $gContent->mXrefInfo->mGroups['metadata'] )) {
	foreach( $gContent->mXrefInfo->mGroups['metadata']->mXrefs as $xref ) {
		switch( $xref['item'] ) {
			case 'genre':          $genres[]     = $xref['xkey_ext']; break;
			case 'director':       $directors[]  = $xref['xkey_ext']; break;
			case 'writer':         $writers[]    = $xref['xkey_ext']; break;
			case 'star':           $stars[]      = $xref['xkey_ext']; break;
			case 'content_rating': $contentRating = $xref['xkey_ext']; break;
			case 'duration':       $durationMs    = (int)$xref['xkey_ext']; break;
		}
	}
}
$gBitSmarty->assign( 'genres', $genres );
$gBitSmarty->assign( 'directors', $directors );
$gBitSmarty->assign( 'writers', $writers );
$gBitSmarty->assign( 'stars', $stars );
$gBitSmarty->assign( 'contentRating', $contentRating );
$gBitSmarty->assign( 'durationMs', $durationMs );

// external links (imdb/tvdb/tmdb/...) - x_group='external', href template: xkey holds the id,
// cross_ref_href the link prefix (both loaded onto every xref row already, see
// LibertyXrefType.php) - built into a real url here since nothing generic renders these outside
// the admin xref-list table.
$externalLinks = [];
if( $gContent->mXrefInfo && !empty( $gContent->mXrefInfo->mGroups['external'] )) {
	foreach( $gContent->mXrefInfo->mGroups['external']->mXrefs as $xref ) {
		if( !empty( $xref['cross_ref_href'] ) && !empty( $xref['xkey'] )) {
			$externalLinks[] = [
				'title' => $xref['xref_title'] ?? strtoupper( $xref['item'] ),
				'url'   => $xref['cross_ref_href'].$xref['xkey'],
			];
		}
	}
}
$gBitSmarty->assign( 'externalLinks', $externalLinks );

// gallery + sibling films for the poster row, modeled on contact/templates/fisheye_fixed_grid_contact.tpl
$gGallery = null;
if( !empty( $_REQUEST['gallery_id'] ) && is_numeric( $_REQUEST['gallery_id'] )) {
	$gGallery = FisheyeGallery::lookup( $_REQUEST );
} elseif( $parents = $gContent->getParentGalleries() ) {
	$gal = current( $parents );
	$gGallery = new FisheyeGallery( $gal['gallery_id'] );
	$gGallery->load();
}
if( is_object( $gGallery ) && $gGallery->isValid() ) {
	$listHash = [ 'max_records' => -1 ];
	$gGallery->loadImages( $listHash );
}
$gBitSmarty->assign( 'gGallery', $gGallery );
$gBitSmarty->assign( 'gContent', $gContent );

$gBitSystem->setCanonicalLink( $gContent->getDisplayUrl() );
$gBitSystem->setBrowserTitle( $gContent->getTitle() );
$gBitSystem->display( 'bitpackage:fisheye/view_film.tpl', null, [ 'display_mode' => 'display' ] );
