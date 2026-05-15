<?php
/**
 * @version $Header$
 * @package fisheye
 * @subpackage modules
 */

/**
 * A specialized version of liberty/modules/mod_last_comments.php for fisheye
 */
use Bitweaver\Liberty\LibertyComment;
use Bitweaver\KernelTools;

global $gQueryUser, $gBitUser, $gLibertySystem, $moduleParams;
$params = $moduleParams['module_params'];
$moduleTitle = !empty($moduleParams['title'])? $moduleParams['title'] : 'Recent Image Comments';

$userId = null;
if( !empty( $gQueryUser->mUserId ) ) {
	$userId = $gQueryUser->mUserId;
}

$listHash = [
	'user_id' => $userId,
	'max_records' => $moduleParams['module_rows'],
];

if (!empty($params['full'])) {
	$listHash['parse'] = true;
}

if (!empty($params['pigeonholes'])) {
	$listHash['pigeonholes']['root_filter'] = $params['pigeonholes'];
}

if( !empty( $params['root_content_type_guid'] ) ) {
	if( empty($moduleTitle) && is_string( $params['root_content_type_guid'] ) ) {
		$moduleTitle = $gLibertySystem->getContentTypeName( $params['root_content_type_guid'] ).' '.KernelTools::tra( 'Comments' );
	}
	$listHash['root_content_type_guid'] = $params['root_content_type_guid'];
} else {
	// default to base image types
	$listHash['root_content_type_guid'] = ['fisheyeimage','fisheyegallery'];
}
$gBitSmarty->assign( 'moduleTitle', $moduleTitle );

$lcom = new LibertyComment();
$modLastComments = $lcom->getList( $listHash );
$keys = array_keys( $modLastComments );
foreach( $keys as $k ) {
	$modLastComments[$k]['object'] = LibertyBase::getLibertyObject( $modLastComments[$k]['root_id'], $modLastComments[$k]['root_content_type_guid'] );
}
$gBitSmarty->assign( 'modLastComments', $modLastComments );
?>
