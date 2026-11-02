<?php
/**
 * @version $Header$
 * @package fisheye
 * @subpackage functions
 */

use Bitweaver\KernelTools;
use Bitweaver\HttpStatusCodes;

/**
 * required setup
 */
require_once '../kernel/includes/setup_inc.php';

$gBitSystem->verifyPackage( 'fisheye' );

global $gBitSystem, $fisheyeErrors, $fisheyeWarnings, $fisheyeSuccess;

include_once FISHEYE_PKG_INCLUDE_PATH.'gallery_lookup_inc.php';

if( $gContent && $gContent->isValid() ) {
	$gBitSystem->setCanonicalLink( $gContent->getDisplayUrl() );
}

global $gHideModules;
$gHideModules = $gBitSystem->isFeatureActive( 'fisheye_gallery_hide_modules' );

if ( !$gContent->isValid() ) {
	if ( !empty( $_REQUEST['gallery_id'] ) ) {
		$gBitSystem->fatalError( KernelTools::tra('No gallery exists with the given ID'), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
	}
	// No gallery was indicated so we will redirect to the browse galleries page
	KernelTools::bit_redirect( FISHEYE_PKG_URL."list_galleries.php", HttpStatusCodes::HTTP_FOUND );
	die;
}

// A content_id that belongs to something other than a gallery (a film, book, album...) has no gallery layout
// to display - send it to its own page rather than fatal in the gallery display.
if( !method_exists( $gContent, 'getLayout' ) ) {
	KernelTools::bit_redirect( $gContent->getDisplayUrl(), HttpStatusCodes::HTTP_FOUND );
	die;
}

if( $gContent->isCommentable() ) {
	$commentsParentId = $gContent->mContentId;
	$comments_vars = [ 'fisheyegallery' ];
	$comments_prefix_var='fisheyegallery:';
	$comments_object_var='fisheyegallery';
	$comments_return_url = $_SERVER['SCRIPT_NAME']."?gallery_id=".$gContent->mGalleryId;
	include_once LIBERTY_PKG_INCLUDE_PATH.'comments_inc.php';
}

if (!empty($_REQUEST['download'])){
	// Checked against global users group assignment so that feature can be restricted on a group level.
	// If content was checked, user would always have permission to do this.
	$gContent->verifyUserPermission('p_fisheye_download_gallery_arc');
	if( $gContent->download() === false && !empty( $gContent->mErrors['download'] ) ){
		$gBitSystem->fatalError( KernelTools::tra( $gContent->mErrors['download'] ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
	}
} else {
	require_once FISHEYE_PKG_INCLUDE_PATH.'display_fisheye_gallery_inc.php';
}
