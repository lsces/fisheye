<?php
/**
 * @package fisheye
 * @subpackage functions
 */

use Bitweaver\KernelTools;

$displayHash = [ 'perm_name' => 'p_fisheye_view' ];
$gContent->invokeServices( 'content_display_function', $displayHash );

$listHash = $_REQUEST;
$listHash['max_records'] = $gContent->mInfo["images_per_page"] ?? $max_records;

switch( $gContent->getLayout() ) {
	case 'auto_flow':
		$gBitThemes->loadCss( FISHEYE_PKG_PATH."css/div_layout.css", true );
		break;
	case 'matteo':
		$gBitThemes->loadCss( FISHEYE_PKG_PATH."gallery_view/matteo/mb_layout.css", true );
		$gBitThemes->loadAjax( 'jquery' );
		$gBitThemes->loadJavascript( FISHEYE_PKG_PATH.'/gallery_views/matteo/mbGallery.js', false, 500, false );
		$gBitThemes->loadJavascript( FISHEYE_PKG_PATH.'/gallery_views/matteo/mbGalleryBox.js', false, 501, false );
		break;
	case 'galleriffic':
		$listHash['max_records'] = -1;
		// Need to add options for different styles of layout
		$gBitThemes->loadCss( FISHEYE_PKG_PATH."/gallery_views/galleriffic/css/galleriffic_style_1.css", true );
		$gBitThemes->loadJavascript( FISHEYE_PKG_PATH.'/gallery_views/galleriffic/js/jquery.galleriffic.js', false, 500, false );
		$gBitThemes->loadJavascript( FISHEYE_PKG_PATH.'/gallery_views/galleriffic/js/jquery.opacityrollover.js', false, 502, false );
		break;
}

$gContent->loadImages( $listHash );
$gContent->loadParentGalleries();
$gContent->addHit();

$gBitSmarty->assign( 'listInfo', $listHash['listInfo'] );

$gBitSystem->setBrowserTitle( $gContent->getTitle().' '.KernelTools::tra('Gallery') );
$gBitSystem->display( $gContent->getRenderTemplate() , null, [ 'display_mode' => 'display' ] );
