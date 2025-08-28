<?php
/**
 * @version $Header$
 * @package fisheye
 * @subpackage functions
 */

/**
 * required setup
 */
require_once '../kernel/includes/setup_inc.php';

global $gBitSystem;

$gBitSystem->display("bitpackage:fisheye/gallery_tree.tpl", null, [ 'display_mode' => 'display' ] );
