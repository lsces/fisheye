<?php
/**
 * @package fisheye
 * @subpackage functions
 */

global $gContent;
use \Bitweaver\Fisheye\FisheyeGallery;

$lookup = [];

if( !$gContent = FisheyeGallery::lookup( $_REQUEST ) ) {
	$gContent = new FisheyeGallery();
	$galleryId = null;
}

if( !empty( $_REQUEST['gallery_path'] ) ) {
	$gContent->setGalleryPath( $_REQUEST['gallery_path'] );
}

$gBitSmarty->assign('gContent', $gContent);
$gBitSmarty->assign('galleryId', $gContent->mGalleryId);

