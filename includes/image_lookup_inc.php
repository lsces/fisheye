<?php
/**
 * @version $Header$
 * @package fisheye
 * @subpackage functions
 */

global $gContent, $gGallery;
use \Bitweaver\Fisheye\FisheyeImage;
use \Bitweaver\Fisheye\FisheyeGallery;
;
if( $gContent = FisheyeImage::lookup( $_REQUEST ) ) {
	// nothing to do. ::lookup will do a full load
} else {
	$gContent = new FisheyeImage();
	$imageId = null;
}

if( !empty( $_REQUEST['gallery_path'] ) ) {
	$_REQUEST['gallery_path'] = rtrim( $_REQUEST['gallery_path'], '/' );
	$gContent->setGalleryPath( $_REQUEST['gallery_path'] );
	$matches = [];
	$tail = strrpos( $_REQUEST['gallery_path'], '/' );
	$_REQUEST['gallery_id'] = substr( $_REQUEST['gallery_path'], $tail + 1 );
}
if( empty( $_REQUEST['gallery_id'] ) ) {
	if( $parents = $gContent->getParentGalleries() ) {
		$gal = current( $parents );
		$gContent->setGalleryPath( '/'.$gal['gallery_id'] );
		$_REQUEST['gallery_id'] = $gal['gallery_id'];
	}
}
// the image is considered the primary content, however the gallery is useful
if( !empty($_REQUEST['gallery_id']) && is_numeric($_REQUEST['gallery_id']) ) {
	$gGallery = FisheyeGallery::lookup( $_REQUEST );
	if( is_object( $gGallery ) && $gGallery->isValid() ) {
		$gGallery->loadCurrentImage( $gContent->mImageId );
	}
	$gBitSmarty->assign('gGallery', $gGallery);
	$gBitSmarty->assign('galleryId', $_REQUEST['gallery_id']);
}

// This user does not own this gallery and they have not been granted the permission to edit this gallery
$gContent->verifyViewPermission();

$gBitSmarty->assign('gContent', $gContent);
$gBitSmarty->assign('imageId', $gContent->mImageId );
