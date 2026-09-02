<?php
/**
 * Dedicated view page for a TV show ("program" - FisheyeProgram, extends FisheyeGallery) -
 * show-level facts (genre/cast/rating/external links, same allXrefs() pattern view_film.php
 * uses) plus a grid of this show's own season members. Deliberately separate from the generic
 * gallery view.php, same reasoning as view_film.php being separate from view_image.php - see
 * fisheye.md's 2026-09-02 "program liberty object" entry for the wider design.
 *
 * Named view_program.php (not its original list_program.php) to match the view_X.php convention
 * every other per-item content type uses (view_film.php, view_image.php) - the old name made a
 * TV Show gallery's member links look like they routed to a listing page rather than a single
 * show's own detail view, unlike the Films gallery's view_film.php links.
 *
 * Also hosts 'Reload Images' (FisheyeProgram::reloadPlexImages()) directly on this view page
 * rather than a separate edit_program.php - a show's real edit page (title/layout/permissions)
 * is the inherited, unmodified FisheyeGallery edit.php (a show genuinely is a gallery), so there
 * was nowhere natural to put a Plex-specific action without either touching that shared
 * edit.tpl (used by every gallery) or adding it here instead.
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

$gContent = FisheyeGallery::lookup( $_REQUEST );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No show exists with the given ID' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}
$gContent->verifyViewPermission();
$gContent->addHit();

$plexResult = null;
if( !empty( $_REQUEST['fReloadImages'] ) ) {
	$gContent->verifyUpdatePermission();
	$plexResult = $gContent->reloadPlexImages();
}

// bucket this show's own xref data - same flat allXrefs() pass as view_film.php, no group names
// hardcoded (see that page's own comment for why that matters).
$gContent->loadXrefInfo();
$genres = $directors = $writers = $stars = [];
$contentRating = $durationMs = null;
$externalLinks = [];
if( $gContent->mXrefInfo ) {
	foreach( $gContent->mXrefInfo->allXrefs() as $xref ) {
		switch( $xref['item'] ) {
			case 'genre':          $genres[]     = $xref['xkey_ext']; break;
			case 'director':       $directors[]  = $xref['xkey_ext']; break;
			case 'writer':         $writers[]    = $xref['xkey_ext']; break;
			case 'star':           $stars[]      = $xref['xkey_ext']; break;
			case 'content_rating': $contentRating = $xref['xkey_ext']; break;
			case 'duration':       $durationMs    = (int)$xref['xkey_ext']; break;
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
$gBitSmarty->assign( 'plexResult', $plexResult );

// this show's own season members - real gallery membership, unchanged from plain FisheyeGallery.
$listHash = [ 'max_records' => -1 ];
$gContent->loadImages( $listHash );

$gBitSmarty->assign( 'gContent', $gContent );

$gBitSystem->setCanonicalLink( $gContent->getDisplayUrl() );
$gBitSystem->setBrowserTitle( $gContent->getTitle() );
$gBitSystem->display( 'bitpackage:fisheye/view_program.tpl', null, [ 'display_mode' => 'display' ] );
