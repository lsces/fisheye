<?php
/**
 * Stub edit page for TV season content - title edit plus the generic liberty xref table
 * (list_xref.tpl / add_xref.php / edit_xref.php) for genre/director/writer/star/rating/duration/
 * imdb/tvdb/tmdb/episodes/images - same reuse-the-generic-table decision as edit_film.php.
 *
 * Hosts 'Reload Images' (FisheyeSeason::reloadPlexImages()) and 'Load Episodes'
 * (FisheyeSeason::reloadPlexEpisodes()) - no season-level 'Reload Metadata' exists deliberately
 * (Lester, 2026-09-02: "Plex DOESN'T put anything up on a season page... it's the TV that toggles
 * to display a selected episode's metadata as you select each") - genre/director/writer/star/
 * rating/duration live per-episode instead, fetched by 'Load Episodes' and shown per-episode on
 * view_season.php.
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;
use Bitweaver\HttpStatusCodes;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty;

$gContent = FisheyeImage::lookup( $_REQUEST );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No season exists with the given ID' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}
$gContent->verifyUpdatePermission();

$plexResult = null;
$plexResultLabel = null;
if( !empty( $_REQUEST['fCancel'] ) ) {
	KernelTools::bit_redirect( $gContent->getDisplayUrl() );
} elseif( !empty( $_REQUEST['fSave'] ) ) {
	// No Plex-sourced season-level description exists to auto-fill this (Plex has nothing at
	// season granularity - see this file's own docblock), but a manual note is still useful -
	// same 'edit' field-name convention as edit_program.php's own description box.
	$storeHash = [ 'content_id' => $gContent->mContentId, 'title' => trim( $_REQUEST['title'] ?? '' ), 'edit' => trim( $_REQUEST['edit'] ?? '' ) ];
	if( $gContent->store( $storeHash ) ) {
		KernelTools::bit_redirect( $gContent->getDisplayUrl() );
	}
	$gContent->load();
} elseif( !empty( $_REQUEST['fReloadImages'] ) ) {
	$plexResult = $gContent->reloadPlexImages();
	$plexResultLabel = KernelTools::tra( 'Images reloaded from Plex' );
} elseif( !empty( $_REQUEST['fReloadEpisodes'] ) ) {
	$plexResult = $gContent->reloadPlexEpisodes();
	$plexResultLabel = KernelTools::tra( 'Episodes loaded from Plex' );
}

$gBitSmarty->assign( 'errors', $gContent->mErrors );

$gContent->loadXrefInfo();
$gBitSmarty->assign( 'gXrefInfo', $gContent->mXrefInfo );
$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'plexResult', $plexResult );
$gBitSmarty->assign( 'plexResultLabel', $plexResultLabel );

$gBitSystem->display( 'bitpackage:fisheye/edit_season.tpl', KernelTools::tra( 'Edit Season: ' ).$gContent->getTitle(), [ 'display_mode' => 'edit' ] );
