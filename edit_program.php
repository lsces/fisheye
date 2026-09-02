<?php
/**
 * Stub edit page for TV show ("program") content - title edit plus the generic liberty xref
 * table (list_xref.tpl / add_xref.php / edit_xref.php) for genre/cast/rating/duration/imdb/tvdb/
 * tmdb/images, same reuse-the-generic-table decision as edit_film.php - a clone of that page
 * (Lester, 2026-09-02: "you need a clone of edit_film to create an edit_program"), replacing the
 * inherited generic FisheyeGallery edit.php as this show's real edit page.
 *
 * Also hosts both Plex actions, same split as edit_film.php: 'Reload Metadata'
 * (FisheyeProgram::reloadPlexMetadata()) and 'Reload Images' (FisheyeProgram::reloadPlexImages()).
 * view_program.php stays pure display with no update actions of its own (Lester: "view is ONLY
 * a view page").
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;
use Bitweaver\HttpStatusCodes;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty;

$gContent = FisheyeGallery::lookup( $_REQUEST );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No show exists with the given ID' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
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
} elseif( !empty( $_REQUEST['fReloadMetadata'] ) ) {
	$plexResult = $gContent->reloadPlexMetadata();
	$plexResultLabel = KernelTools::tra( 'Metadata reloaded from Plex' );
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

$gBitSystem->display( 'bitpackage:fisheye/edit_program.tpl', KernelTools::tra( 'Edit Show: ' ).$gContent->getTitle(), [ 'display_mode' => 'edit' ] );
