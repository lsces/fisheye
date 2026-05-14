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

global $gBitSmarty, $gContent;
// makes things in older modules easier

if ( !empty($gBitSmarty->tpl_vars) ) {
	$tpls = $gBitSmarty->tpl_vars;
	$module_params = $tpls['moduleParams'];
	$listHash = $module_params->value;
	$listHash['gallery_id'] = $module_params->value['module_rows'];
}
$image = new FisheyeImage();

$display = true;

$listHash['size'] = 'extra-large';
$listHash['max_records'] = 5;
$listHash['sort_mode'] = 'random';

$images = $image->getList( $listHash );
$moduleTitle = 'Banner Image';
$gBitSmarty->assign( 'moduleTitle', $moduleTitle );
$gBitSmarty->assign( 'modImages', $images );
$gBitSmarty->assign( 'module_params', $listHash );
