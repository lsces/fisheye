<?php
/**
 * Dedicated view page for TV season content (FisheyeSeason) - the "matching pair" to
 * edit_season.php, alongside view_program.php/edit_program.php (Lester, 2026-09-02).
 *
 * No season-level facts panel - Plex itself has none (Lester, 2026-09-02: "Plex DOESN'T put
 * anything up on a season page... it's the TV that toggles to display a selected episode's
 * metadata as you select each"). Instead this page's main content is the episode list; each
 * episode's own JSON packet (FisheyeSeason::reloadPlexEpisodes(), stored in its xref row's `data`
 * column - title/summary/air_date/director/writer/star/content_rating/duration) is decoded here
 * and rendered into the template already, so selecting an episode client-side just toggles which
 * already-rendered detail block is visible - no per-episode request, mirroring Plex's own
 * highlight-swaps-the-panel interaction.
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

$gContent->loadXrefInfo();
$externalLinks = [];
$seasonImages = [];
$episodes = [];
if( $gContent->mXrefInfo ) {
	foreach( $gContent->mXrefInfo->allXrefs() as $xref ) {
		switch( $xref['item'] ) {
			case 'image':
				$seasonImages[] = [ 'xref_id' => $xref['xref_id'] ];
				break;
			case 'episode':
				$data = !empty( $xref['data'] ) ? json_decode( $xref['data'], true ) : [];
				$episodes[] = [
					'xorder'        => (int)$xref['xorder'],
					'title'         => $data['title'] ?? pathinfo( $xref['xkey_ext'], PATHINFO_FILENAME ),
					'summary'       => $data['summary'] ?? '',
					'air_date'      => $data['air_date'] ?? '',
					'directors'     => $data['director'] ?? [],
					'writers'       => $data['writer'] ?? [],
					'stars'         => $data['star'] ?? [],
					'content_rating'=> $data['content_rating'] ?? '',
					'durationMs'    => $data['duration'] ?? null,
				];
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
