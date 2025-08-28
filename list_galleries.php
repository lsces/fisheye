<?php
/**
 * @version $Header$
 * @package fisheye
 * @subpackage functions
 */

/**
 * required setup
 */
namespace Bitweaver\Fisheye;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gFisheyeGallery;

//$gBitSystem->verifyPermission( 'p_fisheye_list_galleries' );

require_once LIBERTY_PKG_PATH.'plugins/mime.image.php';
$gFisheyeGallery = new FisheyeGallery();

/* Get a list of galleries which matches the input parameters (default is to list every gallery in the system) */
$_REQUEST['root_only'] = true;
/* Process the input parameters this page accepts */
if (!empty($_REQUEST['user_id']) && is_numeric($_REQUEST['user_id'])) {
	if( $_REQUEST['user_id'] == $gBitUser->mUserId ) {
		$_REQUEST['show_empty'] = true;
	}
	$gBitSmarty->assign('gQueryUserId', $_REQUEST['user_id']);
	$template = 'user_galleries.tpl';
} else {
	$template = 'list_galleries2.tpl';
}

$_REQUEST['thumbnail_size'] = $gBitSystem->getConfig( 'fisheye_list_thumbnail_size', 'small' );

$galleryList = $gFisheyeGallery->getList( $_REQUEST );
$gFisheyeGallery->invokeServices( 'content_list_function', $_REQUEST );
// Pagination Data
$gBitSmarty->assign( 'listInfo', $_REQUEST['listInfo'] );
$gBitSmarty->assign( 'galleryList', $galleryList );

// Display the template
$gDefaultCenter = "bitpackage:fisheye/$template";
$gBitSmarty->assign( 'gDefaultCenter', $gDefaultCenter );
$gBitSystem->display( 'bitpackage:kernel/dynamic.tpl', 'List Galleries' , [ 'display_mode' => 'list' ] );
