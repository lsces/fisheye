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
use Bitweaver\KernelTools;
use Bitweaver\Liberty\LibertyBase;

global $gBitSystem;

include_once FISHEYE_PKG_INCLUDE_PATH.'gallery_lookup_inc.php';

if( $gBitSystem->isPackageActive( 'gatekeeper' ) ) {
	global $gGatekeeper;
	$gBitSmarty->assign( 'securities', $gGatekeeper->getSecurityList( $gBitUser->mUserId ) );
}

// Ensure the user has the permission to create new image galleries
$gContent->verifyUpdatePermission();

if (!empty($_REQUEST['cancel'])) {
	header( 'Location: '.$gContent->getDisplayUrl() );
	die;
} elseif (!empty($_REQUEST['save_order'])) {
	if( !empty( $_REQUEST['batch'] ) ) {
		// flip so we can do instant hash lookup
		$batchCon = array_flip( $_REQUEST['batch'] );
		// increment the first element from 0 to 1 (element 0 index before flip) so any conditional tests will pass, particularly in the .tpl
		$batchCon[key($batchCon)]++;
		$gBitSmarty->assign( 'batchEdit', $batchCon );
	}

	if( !empty( $_REQUEST['is_favorite'] ) ) {
		// flip so we can do instant hash lookup
		$favoriteCon = array_flip( $_REQUEST['is_favorite'] );
		// increment the first element from 0 to 1 (element 0 index before flip) so any conditional tests will pass, particularly in the .tpl
		$favoriteCon[key($favoriteCon)]++;
	}

	$gContent->loadImages();

	$feedback = null;

	if( !empty( $_REQUEST['reorder_gallery'] ) ) {
		switch( $_REQUEST['reorder_gallery'] ){
			case 'photo_date':
				foreach( array_keys( $gContent->mItems ) as $imageId ) {
					$reorder[$gContent->mItems[$imageId]->mContentId] = $gContent->mItems[$imageId]->mInfo['event_time'];
				}
				break;
			case 'upload_date':
				foreach( array_keys( $gContent->mItems ) as $imageId ) {
					$reorder[$gContent->mItems[$imageId]->mContentId] = $gContent->mItems[$imageId]->mInfo['created'];
				}
				break;
			case 'caption':
				foreach( array_keys( $gContent->mItems ) as $imageId ) {
					$reorder[$gContent->mItems[$imageId]->mContentId] = $gContent->mItems[$imageId]->mInfo['title'];
				}
				break;
			case 'file_name':
				foreach( array_keys( $gContent->mItems ) as $imageId ) {
					$reorder[$gContent->mItems[$imageId]->mContentId] = $gContent->mItems[$imageId]->mInfo['file_name'];
				}
				break;
			case 'random':
				foreach( array_keys( $gContent->mItems ) as $imageId ) {
					$reorder[$gContent->mItems[$imageId]->mContentId] = rand( 0, 9999999 );
				}
				break;
		}

		natcasesort( $reorder );
		$sortPos = 10;
		foreach( $reorder as $conId => $sortVal ) {
			$newOrder[$conId] = $sortPos;
			$sortPos += 10;
		}
	}

	if( !empty( $gContent->mItems ) ) {
		foreach( array_keys( $gContent->mItems ) as $itemConId ) {
			if( $gContent->mItems[$itemConId]->getField( 'is_favorite' ) && empty( $favoriteCon[$itemConId] ) ) {
				$gBitUser->expungeFavorite( $itemConId );
			} elseif( !$gContent->mItems[$itemConId]->getField('is_favorite') && !empty( $favoriteCon[$itemConId] ) ) {
				$gBitUser->storeFavorite( $itemConId );
			}
		}
	}

	foreach ($_REQUEST['imagePosition'] as $contentId=>$newPos) {
		if( $galleryItem = LibertyBase::getLibertyObject( $contentId ) ) {
			$galleryItem->load();
			if( isset( $batchCon[$contentId] ) ) {
				if( !empty( $_REQUEST['batch_command'] ) ) {
					@list( $batchCommand, $batchParam ) = @explode( ':', $_REQUEST['batch_command'] );
					switch( $batchCommand ) {
						case 'delete':
							$galleryItem->expunge();
							$galleryItem = null;
							break;
						case 'remove':
							$parents = $galleryItem->getParentGalleries();
							if( $galleryItem->isContentType( FISHEYEGALLERY_CONTENT_TYPE_GUID ) || count( $parents ) > 1 ) {
								$gContent->removeItem( $contentId );
							} else {
								$galleryItem->expunge();
							}
							$galleryItem = null;
							break;
						case 'rotate':
							if( is_a( $galleryItem, '\Bitweaver\Fisheye\FisheyeImage' ) ) {
								$galleryItem->rotateImage( $batchParam );
								$feedback['success'] = KernelTools::tra( "Images rotated" );
							}
							break;
						case 'thumbnail':
							$galleryItem->generateGalleryThumbnails();
							$feedback['success'] = KernelTools::tra( "Thumbnail regeneration queued" );
							break;
						case 'grayscale':
							$galleryItem->convertColorspace( 'grayscale' );
							break;
						case 'security':
							$storageHash['security_id'] = $batchParam;
							$feedback['success'] = KernelTools::tra( "Items security assigned" );
							break;
						case 'gallerymove':
							if( empty( $destGallery ) ) {
								$destGallery = new FisheyeGallery( null, $batchParam );
								$destGallery->load();
							}
							if( $batchParam != $contentId ) {
								$gContent->removeItem( $contentId );
							}
						case 'gallerycopy':
							if( empty( $destGallery ) ) {
								$destGallery = new FisheyeGallery( null, $batchParam );
								$destGallery->load();
							}
							if( $destGallery->addItem( $contentId ) ) {
								$feedback['success'][] = $galleryItem->getTitle().' '.KernelTools::tra( "added to" ).' '.$destGallery->getTitle();
							} else {
								$feedback['error'][] = $galleryItem->getTitle().' '.KernelTools::tra( "could not be added to" ).' '.$destGallery->getTitle();
							}
							break;
						case 'filenametoimagename':
							$renameHash = [];
							if( !empty( $galleryItem->mInfo['filename'] ) ) {
								$renameHash['title'] = KernelTools::file_name_to_title( $galleryItem->mInfo['filename'] );
								$galleryItem->store( $renameHash );
								// update to prevent renaming value in text input
								$_REQUEST['image_title'][$contentId] = $renameHash['title'];
							}
							break;
					}
				}
			}
			if( is_object( $galleryItem ) ) {
				if( !empty( $_REQUEST['batch_security_id'] ) ) {
				}
				// if we are reordered, that takes precident
				$newPos = preg_replace( '/[^\d\.]/', '', !empty( $newOrder[$contentId] ) ? $newOrder[$contentId] : $newPos);

				if ($galleryItem->mInfo['title'] != $_REQUEST['image_title'][$contentId]) {
					$storageHash = [ 'title' => $_REQUEST['image_title'][$contentId] ];
					// make sure we don't delete the 'data' field on en masse title updating
					$storageHash['edit'] = $galleryItem->getField( 'data' );
				}
				if( !empty( $storageHash ) ) {
					$galleryItem->store($storageHash);
				}
				$galleryItem->updatePosition($gContent->mContentId, $newPos);
			}
		}
		unset( $storageHash );
	}

	if ($gContent->storeGalleryThumbnail($_REQUEST['gallery_preview_content_id'])) {
		$gContent->mInfo['preview_content_id'] = $_REQUEST['gallery_preview_content_id'];
	}

	$_SESSION['image_order_feedback'] = $feedback;

	// Redirect so reload does not cause double-batch processing
	KernelTools::bit_redirect( FISHEYE_PKG_URL.'image_order.php?gallery_id='.$gContent->getField( 'gallery_id' ) );
}

if( !empty( $_SESSION['image_order_feedback'] ) ) {
	$feedback = $_SESSION['image_order_feedback'];
	unset( $_SESSION['image_order_feedback'] );
}

// Get a list of usable galleries
$listHash = [
	'user_id'       => $gBitUser->mUserId,
	'page'			=> -1,
	'max_records'   => -1,
	'no_thumbnails' => true,
	'sort_mode'     => 'title_asc',
	'show_empty'    => true
];
// modify listHash according to global preferences
if( $gBitSystem->isFeatureActive( 'fisheye_show_all_to_admins' ) && $gBitUser->hasPermission( 'p_fisheye_admin' ) ) {
	unset( $listHash['user_id'] );
} elseif( $gBitSystem->isFeatureActive( 'fisheye_show_public_on_upload' ) ) {
//	$listHash['show_public'] = true;
}
$galleryList = $gContent->getList( $listHash );
$gBitSmarty->assign( 'galleryList', $galleryList );
$gContent->loadImages( $listHash );
if ( !empty( $feedback ) ) {
	$gBitSmarty->assign('formfeedback', $feedback);
}

// $gBitThemes->loadAjax( 'mochikit' );
$gBitSystem->display( 'bitpackage:fisheye/image_order.tpl', KernelTools::tra( 'Edit Gallery Images' ).': '.$gContent->getTitle() , [ 'display_mode' => 'display' ] );
