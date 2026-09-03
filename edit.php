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
	// When creating a sub-gallery, inherit protector role from the first parent if not explicitly set
	if( !$gContent->isValid() && !empty( $_REQUEST['gallery_additions'] ) && ( !isset( $_REQUEST['protector']['role_id'] ) || $_REQUEST['protector']['role_id'] == -1 ) ) {
		$parentGalleryId = reset( $_REQUEST['gallery_additions'] );
		if( $parentContentId = $gContent->mDb->GetOne( "SELECT `content_id` FROM `".BIT_DB_PREFIX."fisheye_gallery` WHERE `gallery_id`=?", [ $parentGalleryId ] ) ) {
			// GetOne() returns null (ADODB_GETONE_EOF's default), not false, when no row
			// matches - the parent gallery having no liberty_content_role_map row at all (the
			// common case: default/anonymous access, nothing explicitly restricted) previously
			// passed a `!== false` check and injected a literal null into
			// $_REQUEST['protector']['role_id'], fatalling downstream in
			// LibertyProtector::storeProtection() on liberty_content_role_map's NOT NULL
			// role_id column. Found 2026-09-03 creating a new top-level-adjacent gallery.
			$parentRole = $gContent->mDb->GetOne( "SELECT `role_id` FROM `".BIT_DB_PREFIX."liberty_content_role_map` WHERE `content_id`=?", [ $parentContentId ] );
			if( $parentRole !== null ) {
				$_REQUEST['protector']['role_id'] = $parentRole;
			}
		}
	}
	if( $gContent->store( $_REQUEST ) ) {
		$gContent->storePreference( 'is_public', !empty( $_REQUEST['is_public'] ) ? $_REQUEST['is_public'] : null );
		$gContent->storePreference( 'allow_comments', !empty( $_REQUEST['allow_comments'] ) ? $_REQUEST['allow_comments'] : null );
		$gContent->storePreference( 'gallery_pagination', !empty( $_REQUEST['gallery_pagination'] ) ? $_REQUEST['gallery_pagination'] : null );
		$gContent->storePreference( 'link_original_images', !empty( $_REQUEST['link_original_images'] ) ? $_REQUEST['link_original_images'] : null );
		$gContent->storePreference( 'total_per_page', !empty( $_REQUEST['total_per_page'] ) ? (int)$_REQUEST['total_per_page'] : null );
		$gContent->storePreference( 'galleriffic_num_thumbs', !empty( $_REQUEST['galleriffic_num_thumbs'] ) ? (int)$_REQUEST['galleriffic_num_thumbs'] : null );
		$gContent->storePreference( 'galleriffic_style', !empty( $_REQUEST['galleriffic_style'] ) ? (int)$_REQUEST['galleriffic_style'] : null );
		$gContent->storePreference( 'show_description', !empty( $_REQUEST['show_description'] ) ? 'y' : 'n' );
		// make sure var is fully stuffed with current data
		$gContent->load();
		// set the mappings, or if nothing checked, nuke them all
		$gContent->addToGalleries( !empty( $_REQUEST['gallery_additions'] ) ? $_REQUEST['gallery_additions'] : null );

		header("location: ".$gContent->getDisplayUrl() );
		if( !empty( $_REQUEST['generate_thumbnails'] ) ) {
			if( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			$gContent->generateGalleryThumbnails();
		}
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
