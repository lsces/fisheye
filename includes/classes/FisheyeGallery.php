<?php
/**
 * @package fisheye
 */

/**
 * required setup
 */
namespace Bitweaver\Fisheye;

use Bitweaver\BitBase;
use Bitweaver\Liberty\LibertyContent;

define( 'FISHEYE_PAGINATION_FIXED_GRID', 'fixed_grid' );
// Content-type-specific grid variants - same rows*cols grid pagination as FIXED_GRID (see
// getList()'s layout branch below), own gallery_views/ template so each swaps its floaticon set
// for the type's own workflow (e.g. film_grid links to load_film.php instead of upload.php).
define( 'FISHEYE_PAGINATION_FILM_GRID', 'film_grid' );
define( 'FISHEYE_PAGINATION_PROGRAM_GRID', 'program_grid' );
define( 'FISHEYE_PAGINATION_MUSIC_GRID', 'music_grid' );
define( 'FISHEYE_PAGINATION_AUTO_FLOW', 'auto_flow' );
define( 'FISHEYE_PAGINATION_POSITION_NUMBER', 'position_number' );
define( 'FISHEYE_PAGINATION_SIMPLE_LIST', 'simple_list' );
define( 'FISHEYE_PAGINATION_MATTEO', 'matteo' );
define( 'FISHEYE_PAGINATION_GALLERIFFIC', 'galleriffic' );

/**
 * FisheyeBase extends LibertyMime, which this class doesn't need, but we need a common base class
 *
 * @package fisheye
 */
#[\AllowDynamicProperties]
class FisheyeGallery extends FisheyeBase {
	public $mGalleryId;		// fisheye_gallery.gallery_id
	public $mItems;			// Array of FisheyeImage class instances which belong to this gallery
	public $mPaginationLookup;
	public $mPreviewImage;
	public $pRecursiveDelete;

	public function __construct($pGalleryId = null, $pContentId = null) {
		parent::__construct();
		if( $this->verifyId( $pGalleryId ) ) {
			$this->mGalleryId = (int)$pGalleryId;		// Set member variables according to the parameters we were passed
		}
		if( $this->verifyId( $pContentId ) ) {
			$this->mContentId = (int)$pContentId;		// liberty_content.content_id which this gallery references
		}
		$this->mItems = [];					// Assume no images (if $pAutoLoad is true we will populate this array later)
		$this->mAdminContentPerm = 'p_fisheye_admin';

		// This registers the content type for FishEye galleries
		// FYI: Any class which uses a table which inherits from liberty_content should create their own content type(s)
		$this->registerContentType(
			FISHEYEGALLERY_CONTENT_TYPE_GUID, [
				'content_type_guid' => FISHEYEGALLERY_CONTENT_TYPE_GUID,
				'content_name' => 'Image Gallery',
				'content_name_plural' => 'Image Galleries',
				'handler_class' => 'FisheyeGallery',
				'handler_package' => 'fisheye',
				'handler_file' => 'FisheyeGallery.php',
				'maintainer_url' => 'https://www.bitweaver.org',
		], );

		// Permission setup
		$this->mViewContentPerm  = 'p_fisheye_view';
		$this->mCreateContentPerm  = 'p_fisheye_create';
		$this->mUpdateContentPerm  = 'p_fisheye_update';
		$this->mAdminContentPerm = 'p_fisheye_admin';
	}

	public function __wakeup() {
		return parent::__wakeup();
	}

	public function __sleep() {
		return array_merge( parent::__sleep(), [ 'mGalleryId' ] );
	}

	public function isValid() {
		return @$this->verifyId( $this->mGalleryId ) || @$this->verifyId( $this->mContentId );
	}

	public static function lookup( $pLookupHash, $pLoadFromCache=true ) {
		global $gBitDb;
		$ret = null;

		$lookupContentId = null;
		if (!empty($pLookupHash['gallery_id']) && is_numeric($pLookupHash['gallery_id'])) {
			if( $lookup = $gBitDb->getRow( "SELECT lc.`content_id`, lc.`content_type_guid` FROM `".BIT_DB_PREFIX."fisheye_gallery` fg INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON(lc.`content_id`=fg.`content_id`) WHERE `gallery_id`=?", [ $pLookupHash['gallery_id'] ] ) ) {
				$lookupContentId = $lookup['content_id'];
				$lookupContentGuid = $lookup['content_type_guid'];
			}
		} elseif (!empty($pLookupHash['content_id']) && is_numeric($pLookupHash['content_id'])) {
			$lookupContentId = $pLookupHash['content_id'];
			$lookupContentGuid = null;
		}

		if( static::verifyId( $lookupContentId ) ) {
			$ret = parent::getLibertyObject( $lookupContentId, $lookupContentGuid, $pLoadFromCache );
		}

		return $ret;
	}

	public function load( $pContentId = null, $pPluginParams = null ) {
		global $gBitSystem;
		$bindVars = [];
		$selectSql = $joinSql = $whereSql = '';

		if( $this->verifyId( $this->mGalleryId ) ) {
			$whereSql = " WHERE fg.`gallery_id` = ?";
			$bindVars = [ $this->mGalleryId ];
		} elseif ( $this->verifyId( $this->mContentId ) ) {
			$whereSql = " WHERE fg.`content_id` = ?";
			$bindVars = [ $this->mContentId ];
		} else {
			$whereSql = null;
		}

		if ($whereSql) {	// If we have some way to know what fisheye_gallery row to load...
			$this->getServicesSql( 'content_load_sql_function', $selectSql, $joinSql, $whereSql, $bindVars, $this );

			$query = "SELECT fg.*, lc.* $selectSql
						, uue.`login` AS modifier_user, uue.`real_name` AS `modifier_real_name`
						, uuc.`login` AS creator_user, uuc.`real_name` AS `creator_real_name`
					FROM `".BIT_DB_PREFIX."fisheye_gallery` fg
						INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (fg.`content_id` = lc.`content_id`) $joinSql
						LEFT JOIN `".BIT_DB_PREFIX."users_users` uue ON (uue.`user_id` = lc.`modifier_user_id`)
						LEFT JOIN `".BIT_DB_PREFIX."users_users` uuc ON (uuc.`user_id` = lc.`user_id`)
					$whereSql";
					$rs = $this->mDb->getRow($query, $bindVars);
			if( !empty($rs) ) {
				$this->mInfo = $rs;
				$this->mContentId = $rs['content_id'];
				LibertyContent::load();
				if( @$this->verifyId($this->mInfo['gallery_id'] ) ) {

					$this->mGalleryId = $this->mInfo['gallery_id'];
					$this->mContentId = $this->mInfo['content_id'];

					$this->mInfo['creator'] = $rs['creator_real_name'] ?? $rs['creator_user'];
					$this->mInfo['editor'] = $rs['modifier_real_name'] ?? $rs['modifier_user'];

					// Set some basic defaults for how to display a gallery if they're not already set
					if (empty($this->mInfo['thumbnail_size'])) {
						$this->mInfo['thumbnail_size'] = $this->getPreference( 'fisheye_gallery_default_thumbnail_size', null );
					}
					if (empty($this->mInfo['rows_per_page'])) {
						$this->mInfo['rows_per_page'] = $this->getPreference('fisheye_gallery_default_rows_per_page', FISHEYE_DEFAULT_ROWS_PER_PAGE);
					}
					if (empty($this->mInfo['cols_per_page'])) {
						$this->mInfo['cols_per_page'] = $this->getPreference('fisheye_gallery_default_cols_per_page', FISHEYE_DEFAULT_COLS_PER_PAGE);
					}
					if (empty($this->mInfo['access_answer'])) {
						$this->mInfo['access_answer'] = '';
					}
					if (  $this->getPreference( 'gallery_pagination' ) == FISHEYE_PAGINATION_GALLERIFFIC and empty($this->mInfo['galleriffic_style'])) {
						$this->mInfo['galleriffic_style'] = $this->getPreference('galleriffic_style', 1);
					}

					$this->mInfo['num_images'] = $this->getImageCount();
					if( $this->getPreference( 'gallery_pagination' ) == FISHEYE_PAGINATION_POSITION_NUMBER ) {
						$this->mInfo['num_pages'] = $this->mDb->getOne( "SELECT COUNT( distinct( floor(`item_position`) ) ) FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` WHERE gallery_content_id=?", [ $this->mContentId ] );
					} else {
						$pagination = $this->getPreference( 'gallery_pagination' );
						if( $pagination == FISHEYE_PAGINATION_GALLERIFFIC ) {
							$this->mInfo['images_per_page'] = (int)$this->getPreference( 'galleriffic_num_thumbs', 30 );
						} elseif( in_array( $pagination, [ FISHEYE_PAGINATION_AUTO_FLOW, FISHEYE_PAGINATION_SIMPLE_LIST, FISHEYE_PAGINATION_MATTEO ] ) ) {
							$this->mInfo['images_per_page'] = (int)$this->getPreference( 'total_per_page', $this->mInfo['rows_per_page'] );
						} else {
							$this->mInfo['images_per_page'] = $this->mInfo['cols_per_page'] * $this->mInfo['rows_per_page'];
						}
						$this->mInfo['num_pages'] = (int)$this->mInfo['num_images'] / $this->mInfo['images_per_page'] + ($this->mInfo['num_images'] % $this->mInfo['images_per_page'] == 0 ? 0 : 1);
					}

				} else {
					unset( $this->mContentId );
					unset( $this->mGalleryId );
				}
			}
		}

		return !empty( $this->mInfo );
	}

	public function loadCurrentImage( $pCurrentImageId ) {
		if( $this->isValid() && @$this->verifyId( $pCurrentImageId ) ) {
			// this code sucks but works - XOXO spiderr
			$query = "SELECT fgim.*, fi.`image_id`, lf.`file_name`, lf.`user_id`, lf.`mime_type`, la.`attachment_id`
					FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim
						INNER JOIN `".BIT_DB_PREFIX."fisheye_image` fi ON ( fi.`content_id`=fgim.`item_content_id` )
						INNER JOIN `".BIT_DB_PREFIX."liberty_attachments` la ON ( fi.`content_id`=la.`content_id` )
						INNER JOIN `".BIT_DB_PREFIX."liberty_files` lf ON ( lf.`file_id`=la.`foreign_id` )
					WHERE fgim.`gallery_content_id` = ?
					ORDER BY fgim.`item_position`, fi.`content_id` ";
			if( $rows = $this->mDb->getAll($query, [ $this->mContentId ] ) ) {
				$tempImage = new FisheyeImage();
				for( $i = 0; $i < count( $rows ); $i++ ) {
					if( $rows[$i]['image_id'] == $pCurrentImageId ) {
						if( $i > 0 ) {
							$this->mInfo['previous_image_id'] = $rows[$i-1]['image_id'];
							$this->mInfo['previous_image_avatar'] = \Bitweaver\Liberty\liberty_fetch_thumbnail_url( [
								'file_name' => $rows[$i-1]['file_name'],
								'source_file'	=> $tempImage->getSourceFile( $rows[$i-1] ),
								'mime_image'   => true,
								'size'         => 'avatar',
							] );
						}
						if( $i + 1  < count( $rows ) ) {
							$this->mInfo['next_image_id'] = $rows[$i+1]['image_id'];
							$this->mInfo['next_image_avatar'] = \Bitweaver\Liberty\liberty_fetch_thumbnail_url( [
								'file_name' => $rows[$i+1]['file_name'],
								'source_file'	=> $tempImage->getSourceFile( $rows[$i+1] ),
								'mime_image'   => true,
								'size'         => 'avatar',
							] );
						}
					}
				}
			}
		}
	}

	public function loadImages( &$pListHash = [] ) {
		global $gLibertySystem, $gBitSystem, $gBitUser;
		if( !$this->isValid() ) {
			return null;
		}

		$pListHash['cant'] = $this->mInfo['num_images'];
		LibertyContent::prepGetList( $pListHash );

		if( empty( $this->mItems ) || !empty( $pListHash['refresh'] ) ) {
			$bindVars = [ $this->mContentId ];
			$whereSql = $selectSql = $joinSql = $orderSql = '';
			$offset = $pListHash['offset'];
			$rowCount = 0;
			$this->getServicesSql( 'content_list_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );

			$orderSql = $gBitSystem->isFeatureActive( 'fisheye_gallery_default_sort_mode' )
				? ", ".$this->mDb->convertSortmode( $gBitSystem->getConfig( 'fisheye_gallery_default_sort_mode' ) )
				: ", fgim.`item_content_id`";

			// load for just a single page
			if( $pListHash['page'] != -1 ) {
				if( $this->getLayout() == FISHEYE_PAGINATION_POSITION_NUMBER ) {
					$query = "SELECT DISTINCT(FLOOR(`item_position`))
							  FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map`
							  WHERE gallery_content_id=?
							  ORDER BY floor(item_position)";
					$mantissa = $this->mDb->getOne( $query, [ $this->mContentId ], 1, $pListHash['page'] - 1 );
					// gallery image order with no positions set will have null mantissa, and all images will be shown
					if( !is_null( $mantissa ) ) {
						$whereSql .= " AND floor(item_position)=? ";
						array_push( $bindVars, $mantissa );
					}
				} elseif( in_array( $this->getLayout(), [ FISHEYE_PAGINATION_FIXED_GRID, FISHEYE_PAGINATION_FILM_GRID, FISHEYE_PAGINATION_PROGRAM_GRID, FISHEYE_PAGINATION_MUSIC_GRID ], true ) ) {
					$rowCount = ($this->mInfo['rows_per_page'] ?? 3) * ($this->mInfo['cols_per_page'] ?? 3);
					$offset = $rowCount * ( (int) $pListHash['page'] - 1);
				} else {
					$rowCount = $pListHash['max_records'];
					$offset = $rowCount * ( (int) $pListHash['page'] - 1);
				}
			}
			if( empty($rowCount) ) $rowCount = $pListHash['max_records'] ?? 10;
			$this->mItems = [];

			$query = "SELECT fgim.*, lc.`user_id`, lct.*, ufm.`favorite_content_id` AS is_favorite $selectSql
					FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim
						INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON ( lc.`content_id`=fgim.`item_content_id` )
						INNER JOIN `".BIT_DB_PREFIX."liberty_content_types` lct ON ( lct.`content_type_guid`=lc.`content_type_guid` )
						$joinSql
						LEFT OUTER JOIN `".BIT_DB_PREFIX."users_favorites_map` ufm ON ( ufm.`favorite_content_id`=lc.`content_id` AND lc.`user_id`=ufm.`user_id` )
					WHERE fgim.`gallery_content_id` = ? $whereSql
					ORDER BY fgim.`item_position` $orderSql";
			$rows = $this->mDb->query($query, $bindVars, $rowCount, $offset);
			foreach ($rows as $row) {
				$pass = true;
				if( $gBitSystem->isPackageActive( 'gatekeeper' ) ) {
					$pass = $gBitUser->hasPermission( 'p_fisheye_admin' ) || !@$this->verifyId( $row['security_id'] ) || ( $row['user_id'] == $gBitUser->mUserId ) || @$this->verifyId( $_SESSION['gatekeeper_security'][$row['security_id']] );
				}
				if( $pass ) {
					if( $item = parent::getLibertyObject( $row['item_content_id'], $row['content_type_guid'], $this->isCacheableObject() ) ) {
						$item->loadThumbnail( $this->mInfo['thumbnail_size'] ?? 'small' );
						$item->setGalleryPath( $this->mGalleryPath.'/'.$this->mGalleryId );
						$item->mInfo['item_position'] = $row['item_position'];
						$this->mItems[$row['item_content_id']] = $item;
					}
				}
			}
		}

		LibertyContent::postGetList( $pListHash );

		return \count ( $this->mItems ) > 0;
	}

	public function getImageList() {
		global $gLibertySystem, $gBitSystem, $gBitUser;
		$ret = null;
		if( $this->isValid() ) {
			$bindVars = [ $this->mContentId ];
			$whereSql = $selectSql = $joinSql = $orderSql = '';
			$rows = $offset = null;
			$this->getServicesSql( 'content_list_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );

			$orderSql = $gBitSystem->isFeatureActive( 'fisheye_gallery_default_sort_mode' )
				? ", ".$this->mDb->convertSortmode( $gBitSystem->getConfig( 'fisheye_gallery_default_sort_mode' ) )
				: ", fgim.`item_content_id`";

			$this->mItems = [];

			$query = "SELECT lc.`content_id` AS `has_key`, fgim.*, lc.*, lct.*, fi.`image_id`, ufm.`favorite_content_id` AS is_favorite $selectSql
					FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim
						INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON ( lc.`content_id`=fgim.`item_content_id` )
						INNER JOIN `".BIT_DB_PREFIX."liberty_content_types` lct ON ( lct.`content_type_guid`=lc.`content_type_guid` )
						LEFT OUTER JOIN `".BIT_DB_PREFIX."fisheye_image` fi ON ( fgim.`item_content_id`=fi.`content_id` )
						$joinSql
						LEFT OUTER JOIN `".BIT_DB_PREFIX."users_favorites_map` ufm ON ( ufm.`favorite_content_id`=lc.`content_id` AND lc.`user_id`=ufm.`user_id` )
					WHERE fgim.`gallery_content_id` = ? $whereSql
					ORDER BY fgim.`item_position` $orderSql";
			$ret = $this->mDb->getAssoc($query, $bindVars, $rows, $offset);
		}
		return $ret;
	}

	public function exportHash( $pPaginate = false ) {
		if( $ret = parent::exportHash() ) {
			$ret['type'] = $this->getContentType();
			if( $this->loadImages() ) {
				foreach( array_keys( $this->mItems ) as $key ) {
					if( $pPaginate ) {
						if( $exp = $this->mItems[$key]->exportHash( $pPaginate ) ) {
							$ret['content']['page'][$this->getItemPage($key)][] = $exp;
						}
					} else {
						$ret['content'][] = $this->mItems[$key]->exportHash( $pPaginate );
					}
				}
			}
		}
		return $ret;
	}

	public function getItemPage( $pItemContentId ) {
		$ret = null;
		if( empty( $this->mPaginationLookup ) ) {
			$this->mPaginationLookup = $this->mDb->getAssoc( "SELECT `item_content_id`, floor(`item_position`) FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` WHERE `gallery_content_id`=?", [ $this->mContentId ] );
		}
		if( !empty( $this->mPaginationLookup[$pItemContentId] ) ) {
			$ret = $this->mPaginationLookup[$pItemContentId];
		}
		return $ret;
	}

	public function getPreviewHash() {
		$ret = [];
		if( !empty( $this->mInfo['preview_content'] ) ) {
			$ret =  $this->mInfo['preview_content']->mInfo;
		}
		// override  $this->mInfo['preview_content']->mInfo['display_url'] so we don't drive directly to the image
		$ret['display_url'] = $this->getDisplayUrl();
		return $ret;
	}

	public function getImageCount() {
		$ret = 0;

		if ($this->mGalleryId) {
			$bindVars = [ $this->mContentId ];
			$whereSql = $selectSql = $joinSql = $orderSql = '';
			$rows = $offset = null;
			$paramHash['no_fatal'] = true;
			$this->getServicesSql( 'content_list_sql_function', $selectSql, $joinSql, $whereSql, $bindVars, null, $paramHash );
			$query = 'SELECT COUNT(*) AS "count"
					FROM `'.BIT_DB_PREFIX."fisheye_gallery_image_map` fgim
					INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON ( lc.`content_id`=fgim.`item_content_id` )
					$joinSql WHERE `gallery_content_id` = ? $whereSql";
			$rs = $this->mDb->getRow($query, $bindVars);
			$ret = $rs['count'];
		}
		return $ret;
	}

	public function verifyGalleryData(&$pParamHash) {
		global $gBitSystem;

		if (empty($pParamHash['rows_per_page'])) {
			$pParamHash['rows_per_page'] = $gBitSystem->getConfig('fisheye_gallery_default_rows_per_page', !empty($this->mInfo['rows_per_page']) ? $this->mInfo['rows_per_page'] : FISHEYE_DEFAULT_ROWS_PER_PAGE);
		}

		if (empty($pParamHash['cols_per_page'])) {
			$pParamHash['cols_per_page'] = $gBitSystem->getConfig('fisheye_gallery_default_cols_per_page', !empty($this->mInfo['cols_per_page']) ? $this->mInfo['cols_per_page'] : FISHEYE_DEFAULT_COLS_PER_PAGE);
		}

		if (empty($pParamHash['thumbnail_size'])) {
			$pParamHash['thumbnail_size'] = $gBitSystem->getConfig('fisheye_gallery_default_thumbnail_size', !empty($this->mInfo['thumbnail_size']) ? $this->mInfo['thumbnail_size'] : 'small');
		}

		if (empty($pParamHash['title'])) {
			$this->mErrors[] = "You must specify a title for this image gallery";
		}

		$pParamHash['content_type_guid'] = $this->getContentType();

		return count($this->mErrors) == 0;
	}

	public function generateGalleryThumbnails(): void {
		if( $this->isValid() ) {
			set_time_limit(0);
			if( $this->loadImages() ) {
				foreach( array_keys( $this->mItems ) as $key ) {
					$this->mItems[$key]->generateThumbnails();
				}
			}
		}
	}

	public function getThumbnailContentId() {
		if( !$this->getField( 'thumbnail_content_id' ) ) {
			$this->getThumbnailImage();
		}
		return $this->getField( 'thumbnail_content_id' );
	}

	public function getThumbnailUri( $pSize='small', $pInfoHash = null ) {
		if( empty( $this->mInfo['preview_content'] ) ) {
			$this->loadThumbnail();
		}

		if( !empty( $this->mInfo['preview_content'] ) && is_object( $this->mInfo['preview_content'] ) ) {
			return $this->mInfo['preview_content']->getThumbnailUri( $pSize );
		}
	}

	public function getThumbnailUrl( string $pSize = 'small', ?array $pInfoHash = null, ?int $pSecondaryId = null, ?int $pDefault = null ): string|null {
		if( empty( $this->mInfo['preview_content'] ) ) {
			$this->loadThumbnail();
		}

		if( is_object( $this->mInfo['preview_content'] ) ) {
			return $this->mInfo['preview_content']->getThumbnailUrl( $pSize );
		}
		return '';
	}

	public function getThumbnailImage( $pContentId=null, $pThumbnailContentId=null, $pThumbnailContentType=null ) {
		global $gLibertySystem, $gBitUser;
		$ret = null;

		if( !@$this->verifyId( $pContentId ) && !empty( $this->mContentId ) ) {
			$pContentId = $this->mContentId;
		}

		if( !@$this->verifyId( $pThumbnailContentId ) ) {
			if( @$this->verifyId( $this->mInfo['preview_content_id'] ?? 0 ) ) {
				$pThumbnailContentId = $this->mInfo['preview_content_id'];
			} else {
				if( $this->mDb->isAdvancedPostgresEnabled() ) {
					$whereSql = '';
					$bindVars = [ $pContentId ];
					if( !$gBitUser->isAdmin() ) {
						$whereSql = "  AND (cgm.`security_id` IS null OR lc.`user_id`=?) ";
						$bindVars[] = $gBitUser->mUserId;
					}
					$query =   "SELECT COALESCE( fg.`preview_content_id`, lc.`content_id` ) AS `content_id`, lc.`content_type_guid`
								FROM connectby('`".BIT_DB_PREFIX."fisheye_gallery_image_map`', '`item_content_id`', '`gallery_content_id`', ?, 0, '/') AS t(`cb_item_content_id` int, `cb_parent_content_id` int, `level` int, `branch` text)
								INNER JOIN liberty_content lc ON(content_id=cb_item_content_id)
								LEFT OUTER JOIN `".BIT_DB_PREFIX."gatekeeper_security_map` cgm ON (cgm.`content_id`=lc.`content_id`), `".BIT_DB_PREFIX."fisheye_gallery` fg
								WHERE `cb_parent_content_id`=fg.`content_id` $whereSql "; //  ORDER BY RANDOM() is DOG slow (seq scans)
					if( $row = $this->mDb->getRow( $query, $bindVars ) ) {
						$pThumbnailContentType = $row['content_type_guid'];
						$pThumbnailContentId = $row['content_id'];
					}
				} else {
					$query = "SELECT fgim.`item_content_id`, lc.`content_type_guid`
							FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON ( fgim.`item_content_id`=lc.`content_id` )
							WHERE fgim.`gallery_content_id` = ?
							ORDER BY ".$this->mDb->convertSortmode('random');
					$rs = $this->mDb->getRow($query, [ $pContentId ], 1);
					if( !empty( $rs ) ) {
						$pThumbnailContentId = $rs['item_content_id'];
						$pThumbnailContentType = $rs['content_type_guid'];
					}
				}
			}
		}

		if( @$this->verifyId( $pThumbnailContentId ) ) {
			$ret = parent::getLibertyObject( $pThumbnailContentId, $pThumbnailContentType, $this->isCacheableObject() );
			if( is_a( $ret, '\Bitweaver\Fisheye\FisheyeGallery' ) ) {
				//recurse down in to find the first image
				if( $ret = $ret->getThumbnailImage() ) {
					$this->mInfo['thumbnail_content_id'] = $ret->getField( 'content_id' );
				}
			} else {
				$this->mInfo['thumbnail_content_id'] = $pThumbnailContentId;
			}
		}
		return $ret;
	}

	public function loadThumbnail( $pSize='small', $pContentId=null ) {
		if( $this->mPreviewImage = $this->getThumbnailImage( $pContentId ) ) {
			$this->mInfo['preview_content'] = &$this->mPreviewImage;
			$this->mInfo['image_file'] = &$this->mPreviewImage->mInfo['image_file'];
		}
	}

	public function storeGalleryThumbnail($pContentId = null) {
		$ret = false;
		if ($pContentId && !$this->isInGallery( $this->mContentId, $pContentId ) ) {
			return false;
		}
		if ($this->mGalleryId) {
			if (!$pContentId)
				$pContentId = null;
			$query = "UPDATE `".BIT_DB_PREFIX."fisheye_gallery` SET `preview_content_id` = ? WHERE `gallery_id`= ?";
			$rs = $this->mDb->getOne($query, [ $pContentId, $this->mGalleryId ] );
			$this->mInfo['preview_content_id'] = $pContentId;
			$ret = true;
		}
		return $ret;
	}

	public function store( array &$pParamHash ): bool {
		if ($this->verifyGalleryData($pParamHash)) {
			$this->StartTrans();
			if( LibertyContent::store($pParamHash)) {
				$this->mContentId = $pParamHash['content_id'];
				$this->mInfo['content_id'] = $this->mContentId;
				if ($this->galleryExistsInDatabase()) {
					$query = "UPDATE `".BIT_DB_PREFIX."fisheye_gallery`
							SET `rows_per_page` = ?, `cols_per_page` = ?, `thumbnail_size` = ?
							WHERE `gallery_id` = ?";
					$bindVars = [ $pParamHash['rows_per_page'], $pParamHash['cols_per_page'], $pParamHash['thumbnail_size'], $this->mGalleryId ];
				} else {
					$this->mGalleryId = $this->mDb->GenID('fisheye_gallery_id_seq');
					$this->mInfo['gallery_id'] = $this->mGalleryId;
					$query = "INSERT INTO `".BIT_DB_PREFIX."fisheye_gallery` (`gallery_id`, `content_id`, `rows_per_page`, `cols_per_page`, `thumbnail_size`) VALUES (?,?,?,?,?)";
					$bindVars = [ $this->mGalleryId, $this->mContentId, $pParamHash['rows_per_page'], $pParamHash['cols_per_page'], $pParamHash['thumbnail_size'] ];
				}
				$rs = $this->mDb->getOne($query, $bindVars);
				$this->CompleteTrans();
			} else {
				$this->mDb->RollbackTrans();
				$this->mErrors[] = "There were errors while attempting to save this gallery";
			}
		} else {
			$this->mErrors[] = "There were errors while attempting to save this gallery";
		}

		return count($this->mErrors) == 0;
	}

	public function removeItem( $pContentId ) {
		$ret = false;
		if( $this->isValid() && @$this->verifyId( $pContentId ) ) {
			$query = "DELETE FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map`
					  WHERE `item_content_id`=? AND `gallery_content_id`=?";
			$rs = $this->mDb->getOne($query, [ $pContentId, $this->mContentId ] );
			$ret = true;
		}
		return $ret;
	}

	/**
	* Adds a new item (image or gallery) to this gallery. We check to make sure we are not a member
	* of this gallery and this gallery is not a member of the new item to avoid infinite recursion scenarios
	* @return bool wheter or not the item was added
	*/
	public function addItem( $pContentId, $pPosition=null ) {
		global $gBitSystem;
		$ret = false;
		if( @$this->verifyId( $this->mContentId ) && @$this->verifyId( $pContentId ) && ( $this->mContentId != $pContentId ) && !$this->isInGallery( $this->mContentId, $pContentId  )  && !$this->isInGallery( $pContentId, $this->mContentId ) ) {
			$query = "INSERT INTO `".BIT_DB_PREFIX."fisheye_gallery_image_map` (`item_content_id`, `gallery_content_id`, `item_position`) VALUES (?,?,?)";
			$rs = $this->mDb->getOne($query, [ $pContentId, $this->mContentId, $pPosition ] );
			$query = "UPDATE `".BIT_DB_PREFIX."liberty_content` SET `last_modified`=? WHERE `content_id`=?";
			$rs = $this->mDb->getOne( $query, [ $gBitSystem->getUTCTime(), $this->mContentId ] );
			$ret = true;
		}
		return $ret;
	}

	public function expunge(): bool {
		if( $this->isValid() ) {
			$this->StartTrans();

			if( $this->loadImages() ) {
				foreach( array_keys( $this->mItems ) as $key ) {
// TODO Recersive delete needs another implementation
//					if( !empty($pRecursiveDelete) ) {
//						$this->mItems[$key]->expunge( $pRecursiveDelete );
//					} else
					if( is_a( $this->mItems[$key], '\Bitweaver\Fisheye\FisheyeGallery' ) ) {
						// make sure we have a valid content_id before we exec
						if( is_numeric( $this->mItems[$key]->mContentId ) ) {
							$query = "SELECT COUNT(`item_content_id`) AS `other_gallery`
									  FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map`
									  WHERE `item_content_id`=? AND `gallery_content_id`!=?";
							if( !($inOtherGallery = $this->mDb->getOne($query, [ $this->mItems[$key]->mContentId, $this->mContentId ] )) ) {
								$this->mItems[$key]->expunge();
							}
						}
					}
				}
			}

			$query = "DELETE FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` WHERE `gallery_content_id`=?";
			$rs = $this->mDb->getOne($query, [ $this->mContentId ] );
			$query = "DELETE FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` WHERE `item_content_id`=?";
			$rs = $this->mDb->getOne($query, [ $this->mContentId ] );
			$query = "DELETE FROM `".BIT_DB_PREFIX."fisheye_gallery` WHERE `content_id`=?";
			$rs = $this->mDb->getOne($query, [ $this->mContentId ] );
			if( LibertyContent::expunge() ) {
				$this->CompleteTrans();
			} else {
				$this->mDb->RollbackTrans();
				error_log( "Error expunging fisheye gallery: " . \Bitweaver\vc($this->mErrors ) );
			}
		}
		return true;
	}

	public function galleryExistsInDatabase() {
		$ret = false;

		if( @$this->verifyId( $this->mGalleryId ) ) {
			$query = "SELECT COUNT(`gallery_id`) AS `gcount`
					FROM `".BIT_DB_PREFIX."fisheye_gallery`
					WHERE `gallery_id` = ?";
			$rs = $this->mDb->getOne($query, [ $this->mGalleryId ] );
			if ($rs > 0)
				$ret = true;
		}

		return $ret;
	}

	/**
	* Returns the layout of the gallery accounting for various defaults
	* @return string the layout string preference
	*/
	public function getLayout() {
		global $gBitSystem;
		return $this->getPreference( 'gallery_pagination', $gBitSystem->getConfig( 'default_gallery_pagination', FISHEYE_PAGINATION_GALLERIFFIC ) );
	}

	public static function getAllLayouts() {
		return [
			FISHEYE_PAGINATION_GALLERIFFIC     => 'Galleriffic',
			FISHEYE_PAGINATION_FIXED_GRID      => 'Fixed Grid',
			FISHEYE_PAGINATION_FILM_GRID       => 'Film Grid',
			FISHEYE_PAGINATION_PROGRAM_GRID    => 'TV Show Grid',
			FISHEYE_PAGINATION_MUSIC_GRID      => 'Music Grid',
			FISHEYE_PAGINATION_AUTO_FLOW       => 'Auto-Flow Images',
			FISHEYE_PAGINATION_POSITION_NUMBER => 'Image Order Page Number',
			FISHEYE_PAGINATION_SIMPLE_LIST     => 'Simple List',
//			FISHEYE_PAGINATION_MATTEO		   => 'Matteo',
		];
	}

	/**
	* Returns include file that will setup the object for rendering
	* @return string the fully specified path to file to be included
	*/
	public function getRenderFile() {
		return FISHEYE_PKG_INCLUDE_PATH.'display_fisheye_gallery_inc.php';
	}

	/**
	* Returns template file used for display
	* @return string the fully specified path to file to be included
	*/
	public function getRenderTemplate() {
		return 'bitpackage:fisheye/view_gallery.tpl';
	}

	/**
	 * Resolve a top-level pool gallery's id by its own title (e.g. "Films", "TV Shows", "Music") -
	 * the real source of truth for the "bodge, acknowledged" LOAD_FILM_TOP_GALLERY_ID /
	 * LOAD_PROGRAM_TOP_GALLERY_ID sentinels load_film.php/load_program.php each used to hardcode
	 * as a literal 1/2, install-order-dependent and only ever "confirmed live" by eyeballing this
	 * one install's data (Lester, 2026-09-03: "should possibly be part of a media info structure"
	 * rather than a magic number per file). Plain title lookup, same query shape
	 * FisheyeProgram::registerFromDisk() already runs to find "TV Shows" to link a new show into -
	 * this just gives every caller one shared place to ask the same question instead of
	 * hardcoding (or re-deriving) the answer themselves.
	 *
	 * @param string $pTitle  the pool gallery's own title, e.g. "Films" or "TV Shows"
	 * @return int|null  null if no gallery with that title exists
	 */
	public static function getTopGalleryId( string $pTitle ): ?int {
		global $gBitDb;
		$galleryId = $gBitDb->getOne(
			"SELECT fg.gallery_id FROM `".BIT_DB_PREFIX."fisheye_gallery` fg
			 JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.content_id = fg.content_id
			 WHERE lc.title = ?",
			[ $pTitle ]
		);
		return $galleryId ? (int)$galleryId : null;
	}

	/**
	* Function that returns link to display a piece of content
	* @param array pGalleryId id of gallery to link
	* @return string the url to display the gallery.
	*/
	public static function getDisplayUrlFromHash( &$pParamHash ) {
		$path = null;
		$ret = '';
		if( BitBase::verifyIdParameter( $pParamHash, 'gallery_id' ) ) {
			$ret = FISHEYE_PKG_URL;
			global $gBitSystem;
			if( $gBitSystem->isFeatureActive( 'pretty_urls' ) ) {
				$ret .= 'gallery'.$path.'/'.$pParamHash['gallery_id'];
			} else {
				$ret .= 'view.php?gallery_id='.$pParamHash['gallery_id'];
				if( !empty( $pHash['path'] ) ) {
					$ret .= '&gallery_path='.$pParamHash['path'];
				}
			}
		} elseif( BitBase::verifyId( $pParamHash['content_id'] ?? 0 ) ) {
			$ret = FISHEYE_PKG_URL.'view.php?content_id='.$pParamHash['content_id'];
		}
		return $ret;
	}

	public function getTree( $pListHash ) {
		global $gBitDb;

		$ret = [];
		if( $this->mDb->isAdvancedPostgresEnabled() ) {
			$bindVars = [];
			$containVars = [];
			$selectSql = '';
			$joinSql = '';
			$whereSql = '';
			if( !empty( $pListHash['contain_item'] ) ) {
				$selectSql = " , tfgim3.`item_content_id` AS `in_gallery` ";
				$joinSql .= " LEFT OUTER JOIN  `".BIT_DB_PREFIX."fisheye_gallery_image_map` tfgim3 ON (tfgim3.`gallery_content_id`=lc.`content_id`) AND tfgim3.`item_content_id`=? ";
				$bindVars[] = $pListHash['contain_item'];
				$containVars[] = $pListHash['contain_item'];
			}
			if( isset( $pListHash['contain_item'] ) ) {
				// contain item might have squeaked in as 0, clear our from pListHash
				unset( $pListHash['contain_item'] );
			}
			foreach( $pListHash as $key=>$val ) {
				$whereSql .= " $key=? AND ";
				$bindVars[] = $val;
			}

			$query =   "SELECT lc.`content_id` AS `hash_key`, fg.*, lc.* $selectSql
						FROM `".BIT_DB_PREFIX."fisheye_gallery` fg
							INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON(fg.`content_id`=lc.`content_id`)
							$joinSql
						WHERE $whereSql NOT EXISTS (SELECT gallery_content_id FROM fisheye_gallery_image_map tfgim2 WHERE tfgim2.item_content_id=lc.content_id)
						ORDER BY lc.title";
			$rootContent = $gBitDb->GetAssoc( $query, $bindVars );

			foreach( array_keys( $rootContent ) as $conId ) {
				$splitVars = [];
				$query = "SELECT branch AS hash_key, * $selectSql
						  FROM connectby('`".BIT_DB_PREFIX."fisheye_gallery_image_map`', '`item_content_id`', '`gallery_content_id`', ?, 0, '/') AS t(cb_item_content_id int,cb_gallery_content_id int, level int, branch text)
							INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery` fg ON (fg.`content_id`=cb_item_content_id)
							INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON(lc.`content_id`=fg.`content_id`)
							$joinSql
						  ORDER BY branch, lc.`title`";
				$splitVars[] = $conId;
				if( !empty( $containVars ) ) {
					$splitVars[] = $containVars[0];
				}

				FisheyeGallery::splitConnectByTree( $ret, $gBitDb->GetAssoc( $query, $splitVars ) );
				FisheyeGallery::getTreeSort( $ret );
			}
		} else if ( $this->mDb->mType == 'firebird' || $this->mDb->mType == 'pdo' ) {
			$bindVars = [];
			$containVars = [];
			$selectSql = '';
			$joinSql = '';
			$whereSql = '';

			if( !empty( $pListHash['contain_item'] ) ) {
				$selectSql = " , tfgim3.`item_content_id` AS `in_gallery` ";
				$joinSql .= " LEFT OUTER JOIN  `".BIT_DB_PREFIX."fisheye_gallery_image_map` tfgim3 ON (tfgim3.`gallery_content_id`=lc.`content_id`) AND tfgim3.`item_content_id`=? ";
				$bindVars[] = $pListHash['contain_item'];
				$containVars[] = $pListHash['contain_item'];
			}
			$this->getServicesSql( 'content_list_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );

			if( isset( $pListHash['contain_item'] ) ) {
				// contain item might have squeaked in as 0, clear our from pListHash
				unset( $pListHash['contain_item'] );
			}
			foreach( $pListHash as $key=>$val ) {
				$whereSql .= " AND lc.$key=? ";
				$bindVars[] = $val;
			}

			$splitVars = [];
					$query = "WITH RECURSIVE
								GALLERY_TREE AS (
								SELECT B.`content_id` AS gallery_content_id, B.`content_id` AS item_content_id, 0 AS BLEVEL, CAST( lcp.`title` AS VARCHAR(255) ) AS BRANCH, 0 AS gallery_parent_id
								FROM `".BIT_DB_PREFIX."fisheye_gallery` B
								INNER JOIN `".BIT_DB_PREFIX."liberty_content` lcp ON(lcp.`content_id`=B.`content_id`)
								WHERE NOT EXISTS (SELECT gallery_content_id FROM fisheye_gallery_image_map tfgim2 WHERE tfgim2.item_content_id=B.content_id)

								UNION ALL

								SELECT G1.`item_content_id` AS gallery_content_id, G1.`item_content_id`, G.BLEVEL + 1, G.BRANCH || '/' || G1.`item_content_id` AS BRANCH, G1.`gallery_content_id` AS gallery_parent_id
								FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` G1
								JOIN GALLERY_TREE G
								ON G1.`gallery_content_id` = G.`item_content_id`
								INNER JOIN `".BIT_DB_PREFIX."liberty_content` lcg1 ON(lcg1.`content_id`=G1.`item_content_id`) and lcg1.`content_type_guid` = 'fisheyegallery'
							)
							SELECT T.BRANCH AS hash_key, T.BLEVEL, fg.*, lc.* $selectSql
							FROM GALLERY_TREE T
							INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery` fg ON (fg.`content_id`=T.`gallery_content_id`)
							INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (lc.`content_id`=T.`item_content_id`)
							LEFT OUTER JOIN  `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgimo ON (fgimo.`gallery_content_id`=T.gallery_parent_id) AND fgimo.`item_content_id`=T.gallery_content_id
							$joinSql
							WHERE lc.`content_type_guid` = 'fisheyegallery' $whereSql
						  ORDER BY T.BRANCH, fgimo.`item_position`";

			if( !empty( $bindVars ) ) {
				FisheyeGallery::splitConnectByTree( $ret, $gBitDb->GetAssoc( $query, $bindVars ) );
			} else {
				FisheyeGallery::splitConnectByTree( $ret, $gBitDb->GetAssoc( $query ) );
			}

		} else {
// this needs replacing with a more suitable list query ...
			$pListHash['show_empty'] = true;
			$galList = $this->getList( $pListHash );
			// index by content_id
			foreach( $galList as $galId => $gal ) {
				$ret[$gal['content_id']] = $gal;
			}
			FisheyeGallery::splitConnectByTree( $ret, $ret );
			FisheyeGallery::getTreeSort( $ret );
		}
		return $ret;
	}

	public function getTreeSort( &$pTree ) {
		if( $pTree ) {
			foreach( array_keys( $pTree ) as $k ) {
				if( !empty( $pTree[$k]['children'] ) ) {
					FisheyeGallery::getTreeSort( $pTree[$k]['children'] );
				}
			}
			uasort( $pTree, [ '\Bitweaver\Fisheye\FisheyeGallery', 'getTreeSortCmp' ] );
		}
	}

	public static function getTreeSortCmp( $a, $b ) {
		return strcmp( $a['content']['title'], $b['content']['title'] );
	}

	public function splitConnectByTree( &$pRet, $pTreeHash ) {
		if( $pTreeHash ) {
			foreach( array_keys( $pTreeHash ) as $conId ) {
				$path = explode( '/', $conId );
				FisheyeGallery::recurseConnectByPath( $pRet, $pTreeHash[$conId], $path );
			}
		}
	}

	public function recurseConnectByPath( &$pRet, $pTreeHash, $pPath ) {
		$popId = array_shift( $pPath );
		if( count( $pPath ) > 0 ) {
			if( empty( $pRet[$popId]['children'] ) ) {
				$pRet[$popId]['children'] = [];
			}
			FisheyeGallery::recurseConnectByPath( $pRet[$popId]['children'], $pTreeHash, $pPath );
		} else {

			$pRet[$popId]['content'] = $pTreeHash;
		}
	}

	// Generate a nested ul list of listed galleries
	public function generateList( $pListHash, $pOptions, $pLocate = false ) {
		$ret = '';
		if( $hash = FisheyeGallery::getTree( $pListHash ) ) {

			$class = ' structure-toc';
			$ret = "<ul ";
			foreach( [ 'class', 'name', 'id', 'onchange' ] as $key ) {
				if( !empty( $pOptions[$key] ) ) {
					if( $key == 'class' ) {
						$class .= ' '.$pOptions[$key];
					} else {
						$ret .= " $key=\"$pOptions[$key]\" ";
					}
				}
			}
			$ret .= ' class="'.$class.'">';
			$ret .= self::generateListItems( $hash, $pOptions, $pLocate );
			$ret .= "</ul>";
		}
		return $ret;
	}

	// Helper method for generateMenu. See that method. Is Recursive
	public function generateListItems( &$pHash, $pOptions, $pLocate ) {
		$ret = '';
		foreach( array_keys( $pHash ) as $conId ) {
			$class = !empty( $pOptions['radio_checkbox'] ) ? 'checkbox' : '';
			$ret .= '<li id="fisheyegallery'.$pHash[$conId]['content']['gallery_id'].'" gallery_id="'.$pHash[$conId]['content']['gallery_id'].'" ';
			if( !empty( $pOptions['item_attributes'] ) ) {
				foreach( $pOptions['item_attributes'] as $key=>$value ) {
					if( $key == 'class' ) {
						$class .= ' '.$value;
					} else {
						$ret .= " $key=\"$value\" ";
					}
				}
			}
			$ret .= ' class="'.$class.'"><label>';
			if ( $pLocate || $pHash[$conId]['content']['content_id'] != $this->mContentId ) {
				if( !empty( $pOptions['radio_checkbox'] ) ) {
					$ret .= '<input type="checkbox" name="gallery_additions[]" value="'.$pHash[$conId]['content']['gallery_id'].'" ';
					if( !empty( $pHash[$conId]['content']['in_gallery'] ) || $pHash[$conId]['content']['content_id'] == $this->mContentId ) {
						$ret .=	' checked="checked" ';
					}
					$ret .= '/>';
				}
			}
			if ( $pHash[$conId]['content']['content_id'] == $this->mContentId
				or ( isset( $pHash[$conId]['content']['in_gallery'] ) and $pHash[$conId]['content']['in_gallery'] ) ) {
				$ret .= '<span class="active">'.htmlspecialchars( $pHash[$conId]['content']['title'] ).'</span>';
			} else {
				$ret .= htmlspecialchars( $pHash[$conId]['content']['title'] );
			}
			$ret .= '</label></li>';
			if( !empty( $pHash[$conId]['children'] ) ) {
				$ret .= '<li><ul>'.FisheyeGallery::generateListItems( $pHash[$conId]['children'], $pOptions, $pLocate ).'</ul></li>';
			}
		}
		return $ret;
	}

	// Generate a select drop menu of listed galleries
	public function generateMenu( $pListHash, $pOptions, $pLocate=null ) {
		$ret = "<select class='form-control' ";
		foreach( [ 'class', 'name', 'id', 'onchange' ] as $key ) {
			if( !empty( $pOptions[$key] ) ) {
				$ret .= " $key=\"$pOptions[$key]\" ";
			}
		}
		$ret .= ">";
		$ret .= !empty( $pOptions['first_option'] ) ? $pOptions['first_option'] : '';
		if( $hash = FisheyeGallery::getTree( $pListHash ) ) {
			$ret .= FisheyeGallery::generateMenuOptions( $hash, $pOptions, $pLocate );
		}
		$ret .= "</select>";
		return $ret;
	}

	// Helper method for generateMenu. See that method. Is Recursive
	public function generateMenuOptions( &$pHash, $pOptions, $pLocate, $pPrefix='' ) {
		$ret = '';
		foreach( array_keys( $pHash ) as $conId ) {
			$ret .= '<option gallery_id="'.$pHash[$conId]['content']['gallery_id'].'" value="'.$pHash[$conId]['content']['gallery_id'].'"';
			if( !empty( $pOptions['item_attributes'] ) ) {
				foreach( $pOptions['item_attributes'] as $key=>$value ) {
					$ret .= " $key=\"$value\" ";
				}
			}
			if ( $pLocate && $pLocate == $pHash[$conId]['content']['gallery_id'] ) {
				$ret .=	' selected="selected" ';
			}
			$ret .= ' >'.($pPrefix?$pPrefix.'&raquo; ':'').htmlspecialchars( $pHash[$conId]['content']['title'] ).'</option>';

			if( !empty( $pHash[$conId]['children'] ) ) {
				$ret .= FisheyeGallery::generateMenuOptions( $pHash[$conId]['children'], $pOptions, $pLocate, $pPrefix.'-' );
			}
		}
		return $ret;
	}

	public function getList( &$pListHash ) {
		global $gBitUser,$gBitSystem, $gBitDbType;

		$pListHash['valid_sort_modes'] = [ 'real_name', 'login', 'hits', 'title', 'created', 'last_modified', 'last_hit', 'event_time', 'ip' ];

		LibertyContent::prepGetList( $pListHash );
		$bindVars = [];
		$selectSql = $joinSql = $whereSql = $sortSql = '';

		if( $gBitDbType == 'mysql' ) {
			// loser mysql without subselects
			if( !empty( $pListHash['root_only'] ) ) {
				$joinSql .= " LEFT OUTER JOIN  `".BIT_DB_PREFIX."fisheye_gallery_image_map` tfgim2 ON (tfgim2.`item_content_id`=lc.`content_id`)";
				$whereSql .= ' AND tfgim2.`item_content_id` IS null ';
			}
		}

		if( !empty( $pListHash['contain_item'] ) ) {
			$selectSql = " , tfgim3.`item_content_id` AS `in_gallery` ";
			$joinSql .= " LEFT OUTER JOIN  `".BIT_DB_PREFIX."fisheye_gallery_image_map` tfgim3 ON (tfgim3.`gallery_content_id`=lc.`content_id`) AND tfgim3.`item_content_id`=? ";
			$bindVars[] = $pListHash['contain_item'];
		}

		if( @$this->verifyId( $pListHash['user_id'] ?? 0 ) ) {
			$whereSql .= " AND lc.`user_id` = ? ";
			$bindVars[] = (int)$pListHash['user_id'];
		}

		if( !empty( $pListHash['find'] ) ) {
			$whereSql .= " AND UPPER( lc.`title` ) LIKE ? ";
			$bindVars[] = '%'.strtoupper( $pListHash['find'] ).'%';
		}

		if( !empty( $pListHash['show_public'] ) ) {
			$joinSql .= " LEFT OUTER JOIN  `".BIT_DB_PREFIX."liberty_content_prefs` lcp ON( lcp.`content_id`=lc.`content_id` )";
			$whereSql .= " OR  ( lcp.`pref_name`=? AND lcp.`pref_value`=? ) ";
			$bindVars[] = 'is_public';
			$bindVars[] = 'y';
		}

		$mapJoin = "";
		if( $gBitDbType != 'mysql' ) {
			// weed out empty galleries if we don't need them. DO NOT get clever and change the IN and EXISTS choices here.
			if( empty( $pListHash['show_empty'] ) ) {
				$whereSql .= " AND fg.`content_id` IN (SELECT `gallery_content_id` FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim WHERE fgim.`gallery_content_id`=fg.`content_id`)";
			}
			if( !empty( $pListHash['root_only'] ) ) {
				$whereSql .= " AND NOT EXISTS (SELECT `gallery_content_id` FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` tfgim2 WHERE tfgim2.`item_content_id`=lc.`content_id`)";
			}
		} else {
			// weed out empty galleries if we don't need them
			if( empty( $pListHash['show_empty'] ) ) {
				$mapJoin = "INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim ON (fgim.`gallery_content_id`=lc.`content_id`)";
			}
		}

		if ( !empty( $pListHash['sort_mode'] ) ) {
			//converted in prepGetList()
			$sortSql .= " ORDER BY ".$this->mDb->convertSortmode( $pListHash['sort_mode'] )." ";
		}
		// Putting in the below hack because mssql cannot select distinct on a text blob column.
		$selectSql .= $gBitDbType == 'mssql' ? " ,CAST(lc.`data` AS VARCHAR(250)) as `data` " : " ,lc.`data` ";

		$this->getServicesSql( 'content_list_sql_function', $selectSql, $joinSql, $whereSql, $bindVars );

		if( !empty( $whereSql ) ) {
			$whereSql = substr_replace( $whereSql, ' WHERE ', 0, 4 );
		}

		$query = "SELECT fg.`gallery_id` AS `hash_key`, fg.*,
					lc.`user_id`, lc.`modifier_user_id`, lc.`created`, lc.`last_modified`,
					lc.`content_type_guid`, lc.`format_guid`, lch.`hits`, lch.`last_hit`, lc.`event_time`, lc.`version`,
					lc.`lang_code`, lc.`title`, lc.`ip`, uu.`login`, uu.`real_name`, plc.`content_type_guid` AS `preview_content_type_guid`
					$selectSql
				FROM `".BIT_DB_PREFIX."fisheye_gallery` fg
					INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (fg.`content_id` = lc.`content_id`)
					INNER JOIN `".BIT_DB_PREFIX."users_users` uu ON (uu.`user_id` = lc.`user_id`)
					LEFT JOIN `".BIT_DB_PREFIX."liberty_content_hits` lch ON (lch.`content_id` = lc.`content_id`)
					$mapJoin $joinSql
					LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content` plc ON (fg.`preview_content_id` = plc.`content_id`)
				$whereSql $sortSql";
			if( $data = $this->mDb->GetAssoc( $query, $bindVars, $pListHash['max_records'], $pListHash['offset'] ) ) {
			if( empty( $pListHash['no_thumbnails'] ) ) {
				$thumbsize = !empty( $pListHash['thumbnail_size'] ) ? $pListHash['thumbnail_size'] : 'small';
				foreach( array_keys( $data ) as $galleryId ) {
				$data[$galleryId]['display_url'] = static::getDisplayUrlFromHash( $data[$galleryId] );
					$data[$galleryId]['display_uri'] = static::getDisplayUriFromHash( $data[$galleryId] );
					if( $thumbImage = $this->getThumbnailImage( $data[$galleryId]['content_id'], $data[$galleryId]['preview_content_id'], $data[$galleryId]['preview_content_type_guid'] ) ) {
						$data[$galleryId]['thumbnail_url'] = $thumbImage->getThumbnailUrl( $thumbsize );
						$data[$galleryId]['thumbnail_uri'] = $thumbImage->getThumbnailUri( $thumbsize );
					} elseif( !empty( $pListHash['show_empty'] ) ) {
						$data[$galleryId]['thumbnail_url'] = FISHEYE_PKG_URL.'image/no_image.png';
					} else {
						unset( $data[$galleryId] );
					}
				}
			}
		}

		// count galleries
		$query_c = "SELECT COUNT( fg.`gallery_id` )
					FROM `".BIT_DB_PREFIX."fisheye_gallery` fg
						INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (fg.`content_id` = lc.`content_id` )
						INNER JOIN `".BIT_DB_PREFIX."users_users` uu ON (uu.`user_id` = lc.`user_id`)
					LEFT OUTER JOIN `".BIT_DB_PREFIX."liberty_content` ptc ON( fg.`preview_content_id`=ptc.`content_id` )
				$mapJoin $joinSql
				$whereSql";
		$cant = $this->mDb->getOne( $query_c, $bindVars );

		// add all pagination info to $ret
		$pListHash['cant'] = $cant;
		LibertyContent::postGetList( $pListHash );
		return $data;
	}

	public function download(){
		if($this->isValid()){
			$zip = new \ZipArchive();

			$filename = tempnam(TEMP_PKG_PATH,"galleryzip");
			chmod($filename, 0666);
			$path = '/';

			if( $zip->open ($filename, \ZIPARCHIVE::OVERWRITE) !== true ){
				$this->mErrors['download'] = "Unable to create zip file";
			}else{
				addGalleryRecursive( $this->mGalleryId, $zip, $path);
			}
			$zip->close();

			//escape backslashes
			$outputFileTitle = str_replace("\\",'\\\\',$this->getTitle() ?? '');
			//escape double quotes
			$outputFileTitle = str_replace('"','\\"',$outputFileTitle);

			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			Header ("Content-disposition: attachment; filename=\"".$outputFileTitle.".zip\"");
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			$filesize = filesize($filename);
			Header ("Content-Length: ".$filesize );
			ob_end_flush();
			readfile($filename);
			unlink($filename);
		}
	}

	public static function getServiceIcon() {
		return '<i class="fa fal fa-camera"></i>';
	}

	public static function getServiceKey() {
		return 'fisheye';
	}
}

function addGalleryRecursive( $pGalleryId, &$pZip, $pPath = '/' ){

	if( $gallery = FisheyeGallery::lookup( [ 'gallery_id' => $pGalleryId ] ) ) {
		$gallery->load();
		$gallery->loadImages();
		$pPath .= $gallery->getTitle().'/';
		foreach ( $gallery->mItems as $item ){
			if( is_a( $item , '\Bitweaver\Fisheye\FisheyeImage' ) ){
				$sourcePath = $item->getSourceFile();
				$title = $item->getTitle();
				$pZip->addFile($sourcePath, $pPath.$title.substr($sourcePath,strrpos($sourcePath,'.')) );
			} elseif ( is_a( $item , '\Bitweaver\Fisheye\FisheyeGallery' ) ) {
				addGalleryRecursive( $item->mGalleryId, $pZip ,$pPath );
			}
		}
	}
}
