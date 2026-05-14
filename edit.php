<?php
/**
 * @package fisheye
 * @subpackage functions
 */

/**
 * required setup
 */
namespace Bitweaver\Fisheye;

require_once '../kernel/includes/setup_inc.php';
use Bitweaver\KernelTools;

global $gBitSystem;

include_once LIBERTY_PKG_INCLUDE_PATH.'liberty_lib.php';
include_once FISHEYE_PKG_INCLUDE_PATH.'gallery_lookup_inc.php';

// Ensure the user has the permission to create new image galleries
if( $gContent->isValid() ){
	$gContent->verifyUpdatePermission();
}else{
	$gContent->verifyCreatePermission();
}

if( $gBitUser->hasPermission( 'p_fisheye_change_thumb_size' ) ) {
	$gBitSmarty->assign( 'thumbnailSizes', \Bitweaver\Liberty\get_image_size_options( null ));
}

$gBitSmarty->assign( 'galleryPaginationTypes', $gContent::getAllLayouts() );

if( !empty( $_REQUEST['savegallery'] ) ) {
	if( $_REQUEST['gallery_pagination'] == 'auto_flow' ) {
		$_REQUEST['rows_per_page'] = $_REQUEST['total_per_page'];
		$_REQUEST['cols_per_page'] = '1';
	} elseif ( $_REQUEST['gallery_pagination'] == 'simple_list' ) {
		$_REQUEST['rows_per_page'] = $_REQUEST['lines_per_page'];
		$_REQUEST['cols_per_page'] = '1';
	} elseif ( $_REQUEST['gallery_pagination'] == 'matteo' ) {
		$_REQUEST['rows_per_page'] = $_REQUEST['images_per_page'];
		$_REQUEST['cols_per_page'] = '1';
	}
	if( $gContent->store( $_REQUEST ) ) {
		$gContent->storePreference( 'is_public', !empty( $_REQUEST['is_public'] ) ? $_REQUEST['is_public'] : null );
		$gContent->storePreference( 'allow_comments', !empty( $_REQUEST['allow_comments'] ) ? $_REQUEST['allow_comments'] : null );
		$gContent->storePreference( 'gallery_pagination', !empty( $_REQUEST['gallery_pagination'] ) ? $_REQUEST['gallery_pagination'] : null );
		$gContent->storePreference( 'link_original_images', !empty( $_REQUEST['link_original_images'] ) ? $_REQUEST['link_original_images'] : null );
		// make sure var is fully stuffed with current data
		$gContent->load();
		// set the mappings, or if nothing checked, nuke them all
		$gContent->addToGalleries( !empty( $_REQUEST['gallery_additions'] ) ? $_REQUEST['gallery_additions'] : null );

		if( !empty( $_REQUEST['generate_thumbnails'] ) ) {
			$gContent->generateThumbnails();
		}
		header("location: ".$gContent->getDisplayUrl() );
		die();
	}
} elseif( !empty( $_REQUEST['delete'] ) ) {
	$gContent->hasUserPermission( 'p_fisheye_admin', true); // , KernelTools::tra( "You do not have permission to delete this image gallery" ) );

	if( !empty( $_REQUEST['cancel'] ) ) {
		// user cancelled - just continue on, doing nothing
	} elseif( empty( $_REQUEST['confirm'] ) ) {
		$formHash['delete'] = true;
		$formHash['gallery_id'] = $gContent->mGalleryId;
		$formHash['input'] = [
			'<label><input name="recurse" value="" type="radio" checked="checked" /> '.KernelTools::tra( 'Delete only images in this gallery. Sub-galleries will not be removed.' ).'</label>',
			'<label><input name="recurse" value="all" type="radio" /> '.KernelTools::tra( 'Permanently delete all contents, even if they appear in other galleries.' ).'</label>',
		];
		$gBitSystem->confirmDialog( $formHash,
			[
				'warning' => KernelTools::tra('Are you sure you want to delete this gallery?') . ' ' . $gContent->getTitle(),
				'error' => KernelTools::tra('This cannot be undone!'),
			],
		);
	} else {
		$userId = $gContent->getField( 'user_id' );

		$gContent->pRecursiveDelete = !empty( $_REQUEST['recurse'] ) && ($_REQUEST['recurse'] == 'all');

		if( $gContent->expunge() ) {
			header( "Location: ".FISHEYE_PKG_URL.'?user_id='.$userId );
		}
	}

} elseif( !empty($_REQUEST['cancelgallery'] ) ) {
	header( 'Location: '.$gContent->getDisplayUrl() );
	die();
}

// Initalize the errors list which contains any errors which occured during storage
$errors = !empty($gContent->mErrors) ? $gContent->mErrors : [];
$gBitSmarty->assign('errors', $errors);

$gBitSystem->setOnloadScript( 'updateGalleryPagination();' );

$gallery = $gContent->getParentGalleries();
$gBitSmarty->assign( 'parentGalleries', $gallery );
$getHash = [
	'user_id'       => $gBitUser->mUserId,
//	'max_records'   => -1,
//	'no_thumbnails' => true,
//	'sort_mode'     => 'title_asc',
//	'show_empty'    => true,
];
if( $gContent->mContentId ) {
	$getHash['contain_item'] = $gContent->mContentId;
}
// modify listHash according to global preferences
if( $gBitSystem->isFeatureActive( 'fisheye_show_all_to_admins' ) && $gBitUser->hasPermission( 'p_fisheye_admin' ) ) {
	unset( $getHash['user_id'] );
} elseif( $gBitSystem->isFeatureActive( 'fisheye_show_public_on_upload' ) ) {
//	$getHash['show_public'] = true;
}
$galleryTree = $gContent->generateList( $getHash,  [ 'name' => "gallery_id", 'id' => "gallerylist", 'item_attributes' => [ 'class'=>'listingtitle'], 'radio_checkbox' => true, ] );
$gBitSmarty->assign( 'galleryTree', $galleryTree );

$gContent->invokeServices( 'content_edit_function' );

$gBitSystem->display( 'bitpackage:fisheye/edit_gallery.tpl', KernelTools::tra('Edit Gallery: ').$gContent->getTitle() , [ 'display_mode' => 'edit' ]);
