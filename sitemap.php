<?php
/**
 * @package fisheye
 * @subpackage functions
 */

require_once '../kernel/includes/setup_inc.php';
use Bitweaver\Fisheye\FisheyeGallery;
use Bitweaver\Fisheye\FisheyeImage;

$gBitSystem->verifyPackage( 'fisheye' );

$gSiteMapHash = [];

$gallery = new FisheyeGallery();
$listHash = [ 'max_records' => -1, 'no_thumbnails' => true ];
if( $galleries = $gallery->getList( $listHash ) ) {
	foreach( $galleries as $row ) {
		$url = FisheyeGallery::getDisplayUrlFromHash( $row );
		if( empty( $url ) ) continue;
		$gSiteMapHash['g'.$row['gallery_id']] = [
			'loc'        => BIT_BASE_URI . $url,
			'lastmod'    => date( 'Y-m-d', $row['last_modified'] ),
			'changefreq' => 'monthly',
			'priority'   => 0.6,
		];
	}
}

$image = new FisheyeImage();
$listHash = [ 'max_records' => -1, 'no_thumbnails' => true ];
if( $images = $image->getList( $listHash ) ) {
	foreach( $images as $row ) {
		$url = FisheyeImage::getDisplayUrlFromHash( $row );
		if( empty( $url ) ) continue;
		$gSiteMapHash['i'.$row['image_id']] = [
			'loc'        => BIT_BASE_URI . $url,
			'lastmod'    => date( 'Y-m-d', $row['last_modified'] ),
			'changefreq' => 'monthly',
			'priority'   => 0.5,
		];
	}
}

$gBitSmarty->assign( 'gSiteMapHash', $gSiteMapHash );
$gBitThemes->setFormatHeader( 'xml' );
header( 'Content-Type: application/xml; charset=utf-8' );
$gBitSystem->display( 'bitpackage:kernel/sitemap.tpl' );
