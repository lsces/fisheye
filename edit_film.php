<?php
/**
 * Stub edit page for film content - title edit plus the generic liberty xref table
 * (list_xref.tpl / add_xref.php / edit_xref.php, already built for contact/stock - reused as-is
 * rather than bespoke per-field markup) for genre/director/writer/star/rating/duration/imdb/tmdb.
 * Also hosts the 'Reload Metadata' action, re-running FisheyeFilm::reloadPlexMetadata() for a
 * film that was imported before that backfill existed, or needs re-syncing after a Plex library
 * update - see that method's docblock for the matching detail.
 *
 * Deliberately minimal - no upload/rotate/resize/gallery-picker machinery like edit_image.php
 * has, none of that applies to an externally-stored film file.
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
	$gBitSystem->fatalError( KernelTools::tra( 'No film exists with the given ID' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}
$gContent->verifyUpdatePermission();

$plexResult = null;
if( !empty( $_REQUEST['fCancel'] ) ) {
	KernelTools::bit_redirect( $gContent->getDisplayUrl() );
} elseif( !empty( $_REQUEST['fSave'] ) ) {
	$storeHash = [ 'content_id' => $gContent->mContentId, 'title' => trim( $_REQUEST['title'] ?? '' ) ];
	if( $gContent->store( $storeHash ) ) {
		KernelTools::bit_redirect( $gContent->getDisplayUrl() );
	}
	$gContent->load();
} elseif( !empty( $_REQUEST['fReloadMetadata'] ) ) {
	$plexResult = $gContent->reloadPlexMetadata();
}

$gBitSmarty->assign( 'errors', $gContent->mErrors );

$gContent->loadXrefInfo();
$gBitSmarty->assign( 'gXrefInfo', $gContent->mXrefInfo );
$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'plexResult', $plexResult );

$gBitSystem->display( 'bitpackage:fisheye/edit_film.tpl', KernelTools::tra( 'Edit Film: ' ).$gContent->getTitle(), [ 'display_mode' => 'edit' ] );
