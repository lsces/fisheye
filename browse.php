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

global $gBitSystem, $gBitSmarty;

$gFisheyeGallery = new FisheyeGallery();
$galleryList = $gFisheyeGallery->getList( $_REQUEST );
$gBitSmarty->assign( 'galleryList', $galleryList );

$gBitSystem->display( "bitpackage:fisheye/browse_galleries.tpl" , null, [ 'display_mode' => 'display' ] );
