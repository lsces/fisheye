<?php
/**
 * Stub edit page for TV season content - title edit plus the generic liberty xref table
 * (list_xref.tpl / add_xref.php / edit_xref.php) for genre/director/writer/star/rating/duration/
 * imdb/tvdb/tmdb/episodes/images - same reuse-the-generic-table decision as edit_film.php.
 *
 * Only hosts 'Reload Images' (FisheyeSeason::reloadPlexImages()) for now - no season equivalent
 * of reloadPlexMetadata() exists yet, not asked for when this was built (Lester, 2026-09-02:
 * "do the same for the season/episode images").
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
	$storeHash = [ 'content_id' => $gContent->mContentId, 'title' => trim( $_REQUEST['title'] ?? '' ) ];
	if( $gContent->store( $storeHash ) ) {
		KernelTools::bit_redirect( $gContent->getDisplayUrl() );
	}
	$gContent->load();
} elseif( !empty( $_REQUEST['fReloadImages'] ) ) {
	$plexResult = $gContent->reloadPlexImages();
	$plexResultLabel = KernelTools::tra( 'Images reloaded from Plex' );
}

$gBitSmarty->assign( 'errors', $gContent->mErrors );

$gContent->loadXrefInfo();
$gBitSmarty->assign( 'gXrefInfo', $gContent->mXrefInfo );
$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'plexResult', $plexResult );
$gBitSmarty->assign( 'plexResultLabel', $plexResultLabel );

$gBitSystem->display( 'bitpackage:fisheye/edit_season.tpl', KernelTools::tra( 'Edit Season: ' ).$gContent->getTitle(), [ 'display_mode' => 'edit' ] );
