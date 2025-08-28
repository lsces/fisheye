<?php
global $gQueryUser, $moduleParams;
extract( $moduleParams );
$gFisheyeImage = new FisheyeImage();

if( !empty( $module_rows ) ) {
	$_REQUEST['max_records'] = $module_rows;
} elseif (!empty($_REQUEST['offset']) && is_numeric($_REQUEST['offset'])) {
	$gBitSmarty->assign('iMaxRows', $iMaxRows);
}
if (empty($_REQUEST['sort_mode'])) {
	$_REQUEST['sort_mode'] = 'random';
}
if (!empty($_REQUEST['search'])) {
	$gBitSmarty->assign('iSearchString', $iSearchtring);
}

$gBitSmarty->assign('iSortMode', $_REQUEST['sort_mode']);

/* Get a list of galleries which matches the imput paramters (default is to list every gallery in the system) */
if( !empty( $gQueryUser ) && $gQueryUser->mUserId ) {
	$_REQUEST['user_id'] = $gQueryUser->mUserId;
}
$_REQUEST['root_only'] = true;
$_REQUEST['get_thumbnails'] = true;
$thumbnailList = $gFisheyeImage->getList( $_REQUEST );
$gBitSmarty->assign('thumbnailList', $thumbnailList);

/* Process the input parameters this page accepts */
if (!empty($gQueryUser) && $gQueryUser->isRegistered()) {
	$gBitSmarty->assign('gQuerUserId', $gQueryUser->mUserId);
	$template = 'user_galleries.tpl';
} else {
	$template = 'list_galleries.tpl';
}

$gBitSmarty->assign( 'fisheye_center_params', $module_params );
?>
