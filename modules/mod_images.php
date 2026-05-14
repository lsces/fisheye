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
use Bitweaver\Users\RoleUser;
global $gQueryUserId, $gContent, $moduleParams;
// makes things in older modules easier
// extract( $moduleParams );

$image = new FisheyeImage();

$display = true;

$listHash = $moduleParams->value;

if( !empty( $gContent ) && $gContent->getField( 'content_type_guid' ) == FISHEYEGALLERY_CONTENT_TYPE_GUID ) {
	$displayCount = empty( $gContent->mItems ) ? 0 : count( $gContent->mItems );
	$thumbCount = $gContent->mInfo['rows_per_page'] * $gContent->mInfo["cols_per_page"];
	$listHash['gallery_id'] = $gContent->mGalleryId;
	$display = $displayCount >= $thumbCount;
}

if( $display ) {
	$listHash['max_records'] = $module_rows ?? 3;
	if( $gQueryUserId ) {
		$listHash['user_id'] = $gQueryUserId;
	} elseif( !empty( $_REQUEST['user_id'] ) ) {
		$gBitSmarty->assign( 'userGallery', $_REQUEST['user_id'] );
		$listHash['user_id'] = $_REQUEST['user_id'];
	} elseif( !empty( $listHash['recent_users'] ) ) {
		$listHash['recent_users'] = true;
	}

	// this is needed to avoid wrong sort_modes entered resulting in db errors
	$sort_options = [ 'hits', 'created' ];
	$sort_mode = !empty( $listHash['sort_mode'] ) && in_array( $listHash['sort_mode'], $sort_options )
		? $listHash['sort_mode'].'_desc' : 'random';

	$listHash['sort_mode'] = $sort_mode;

	$images = $image->getList( $listHash );

	if( empty( $title ) && $images ) {
		$moduleTitle = '';
		if( !empty( $listHash['sort_mode'] ) ) {
			if( $listHash['sort_mode'] == 'random' ) {
				$moduleTitle = 'Random';
			} elseif( $listHash['sort_mode'] == 'created' ) {
				$moduleTitle = 'Recent';
			} elseif( $listHash['sort_mode'] == 'hits' ) {
				$moduleTitle = 'Popular';
			}
		} else {
			$moduleTitle = 'Random';
		}

		$moduleTitle .= ' Images';
		$moduleTitle = KernelTools::tra( $moduleTitle );

		if( !empty( $listHash['user_id'] ) ) {
			$moduleTitle .= ' '.KernelTools::tra('by').' '.RoleUser::getDisplayNameFromHash( current( $images ), true );
		} elseif( !empty( $listHash['recent_users'] ) ) {
			$moduleTitle .= ' '.KernelTools::tra( 'by' ).' <a href="'.USERS_PKG_URL.'">'.KernelTools::tra( 'New Users' ).'</a>';
		}

		$listHash['sort_mode'] = $sort_mode;
		$gBitSmarty->assign( 'moduleTitle', $moduleTitle );
	} else {
		$gBitSmarty->assign( 'moduleTitle', $title );
	}

	$gBitSmarty->assign( 'imageSort', $sort_mode );
	$gBitSmarty->assign( 'modImages', $images );
	$gBitSmarty->assign( 'module_params', $listHash );
	$gBitSmarty->assign( 'maxlen', isset( $listHash["maxlen"] ) );
	$gBitSmarty->assign( 'maxlendesc', isset( $listHash["maxlendesc"] ) );
}
