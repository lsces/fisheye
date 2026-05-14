<?php
/**
 * @version $Header$
 * @package fisheye
 * @subpackage modules
 */

/**
 * required setup
 */
namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;
global $gQueryUserId, $gContent, $moduleParams;

// makes things in older modules easier
extract( $moduleParams );

$image = new FisheyeImage();

$display = true;

$listHash = $module_params;
$listHash['gallery_id'] = 3;

if( $display ) {
	$listHash['size'] = 'medium';
	$listHash['max_records'] = 5;
	$listHash['sort_mode'] = 'random';
	$images = $image->getList( $listHash );

	$moduleTitle = 'Specials';
	$moduleTitle = KernelTools::tra( $moduleTitle );

	$gBitSmarty->assign( 'moduleTitle', $moduleTitle );
	$gBitSmarty->assign( 'modImages', $images );
	$gBitSmarty->assign( 'module_params', $module_params );
	$gBitSmarty->assign( 'maxlen', $module_params["maxlen"] ?? 0 );
	$gBitSmarty->assign( 'maxlendesc', $module_params["maxlendesc"] ?? 0 );
}
