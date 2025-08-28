<?php
/**
 * @package fisheye
 * @subpackage functions
 */

use \Bitweaver\HttpStatusCodes;
use Bitweaver\KernelTools;

if( !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( "No image exists with the given ID" ) ,'error.tpl', '', HttpStatusCodes::HTTP_GONE );
}

$displayHash = [ 'perm_name' => 'p_fisheye_view' ];
$gContent->invokeServices( 'content_display_function', $displayHash );

// Get the proper thumbnail size to display on this page
if( empty( $_REQUEST['size'] )) {
	$_REQUEST['size'] = $gBitSystem->getConfig( 'fisheye_image_default_thumbnail_size', FISHEYE_DEFAULT_THUMBNAIL_SIZE );
}

$gBitSystem->setBrowserTitle( $gContent->getTitle() );
if( $gBitThemes->isAjaxRequest() ) {
	$gBitSmarty->display( $gContent->getRenderTemplate() );
} else {
	$gBitSystem->display( $gContent->getRenderTemplate() , null, [ 'display_mode' => 'display' ] );
}

