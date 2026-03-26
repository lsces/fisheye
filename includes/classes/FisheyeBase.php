<?php
/**
 * @package fisheye
 */

/**
 * required setup
 */
namespace Bitweaver\Fisheye;
use Bitweaver\Liberty\LibertyMime;		// FisheyeGallery base class
use Bitweaver\Liberty\LibertyContent;

define('FISHEYEGALLERY_CONTENT_TYPE_GUID', 'fisheyegallery' );

/**
 * @package fisheye
 */
#[\AllowDynamicProperties]
abstract class FisheyeBase extends LibertyMime
{
	// Path of gallery images to get breadcrumbs
	public $mGalleryPath;
	public $mGalleryId;

	abstract public static function getServiceKey();

	public function __sleep() {
		return array_merge( parent::__sleep(), [ 'mGalleryPath' ] );
	}

	public function __construct() {
		$this->mGalleryPath = '';
		parent::__construct();
	}

	// regular expression to determine if the title was computer generated
	public function isMachineName( $pString ) {
		if ( !empty($pString) ) {
			return preg_match( '/(^[0-9][-0-9 ]*$)|(^[-0-9 ]*(img|dsc|dscn|pict|htg|dscf|p)[-0-9 ][-0-9 ]*.*$)/i', trim( $pString ) );
		} else {
			return '';
		}
	}

	// Gets a list of galleries which this item is attached to
	public function getParentGalleries( $pContentId=null ) {
		if( !$this->verifyId( $pContentId ) ) {
			$pContentId = $this->mContentId;
		}
		$ret = null;

		if( is_numeric( $pContentId ) ) {
			$sql = "SELECT fg.`gallery_id` AS `hash_key`, fg.*, lc.`title`
					FROM `".BIT_DB_PREFIX."fisheye_gallery` fg, `".BIT_DB_PREFIX."liberty_content` lc, `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim
					WHERE fgim.`item_content_id` = ? AND fgim.`gallery_content_id`=fg.`content_id` AND fg.`content_id`=lc.`content_id`";
			$ret = $this->mDb->getAssoc( $sql, [ $pContentId  ] );
		}
		if ( $ret ) {
			$parents = current( $ret );
			$sql = "WITH TREE AS
				( SELECT fgim.`item_content_id` AS gallery_content_id,
				LAG( fgim.`item_content_id`) OVER (ORDER BY fgim.`item_position`) AS PREVIOUS,
				LEAD( fgim.`item_content_id` ) OVER (ORDER BY fgim.`item_position`) AS NEXT
				FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim
				WHERE fgim.`gallery_content_id` = ?
				order by fgim.`item_position` )
				SELECT pr.PREVIOUS, prec.`content_type_guid` AS PRE_T, pr.NEXT, posc.`content_type_guid` AS NEXT_T FROM TREE pr
				LEFT JOIN `".BIT_DB_PREFIX."liberty_content` prec ON prec.`content_id` = pr.PREVIOUS
				LEFT JOIN `".BIT_DB_PREFIX."liberty_content` posc ON posc.`content_id` = pr.NEXT
				WHERE pr.`gallery_content_id` = ?";
			if( $parents = $this->mDb->getRow($sql, [ $parents['content_id'], $pContentId ] ) ) {
				if ( $parents['pre_t'] == FISHEYEGALLERY_CONTENT_TYPE_GUID ) {
					$ret['previous_gallery_id'] = $parents['previous'];
				} else {
					$ret['previous_image_id'] = $parents['previous'];
				}
				if ( $parents['next_t'] == FISHEYEGALLERY_CONTENT_TYPE_GUID ) {
					$ret['next_gallery_id'] = $parents['next'];
				}else {
					$ret['next_image_id'] = $parents['previous'];
				}
			}
		}
		return $ret;
	}

	public function loadParentGalleries() {
		if( $this->isValid() ) {
			$this->mInfo['parent_galleries'] = $this->getParentGalleries();
		}
	}

	public function updatePosition($pGalleryContentId, $newPosition = null) {
		if( $pGalleryContentId && $newPosition && $this->verifyId($this->mContentId) ) {
			// SQL optimization to prevent stupid updates of identical data
			if( $radixPosition = strpos( $newPosition, '.' ) ) {
				// clean out newPosition to be a valid float, and nuke all extra . or extra crap
				$significand = substr( $newPosition, 0, $radixPosition );
				$mantissa = preg_replace( '/[^0-9]/', '', substr( $newPosition, $radixPosition + 1 ) );
				$newPosition = $significand.'.'.$mantissa;
			}
			$cleanPosition = preg_replace( '/\./', '', $newPosition );
			$sql = "UPDATE `".BIT_DB_PREFIX."fisheye_gallery_image_map` SET `item_position` = ?
					WHERE `item_content_id` = ? AND `gallery_content_id` = ? AND (`item_position` IS null OR `item_position`!=?)";
			$rs = $this->mDb->getOne($sql, [ $newPosition, $this->mContentId, $pGalleryContentId, $newPosition ] );
		}
	}

	public function setGalleryPath( $pPath ) {
		$this->setField( 'gallery_path', rtrim( $pPath, '/' ) );
	}

	public function getThumbnailContentId() {
		// PURE VIRTUAL
	}

	public function loadThumbnail( $pSize='small', $pContentId=null ) {
		// Default does nothing
	}

	// Possible derived read-only object such as Facebook, Instagram, etc.. default is true
	public function isEditable() {
		return true;
	}

	// THis is a function that creates a mack daddy function to get a breadcrumb path with a single query.
	// Do not muck with this query unless you really, truly understand what is going on.
/*
not ready for primetime
	public function getPaths() {
		global $gBitDb;

		$ret = null;
		if( $this->isValid() ) {
			if( $this->mDb->isAdvancedPostgresEnabled() ) {
				$bindVars = [];
				$containVars = [];
				$selectSql = '';
				$joinSql = '';
				$whereSql = '';

				$query = "SELECT fg.gallery_id, branch
						  FROM connectby('`".BIT_DB_PREFIX."fisheye_gallery_image_map`', '`gallery_content_id`', '`item_content_id`', ?, 0, '/') AS t(cb_item_content_id int,cb_gallery_content_id int, level int, branch text)
							INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery` fg ON (fg.`content_id`=cb_item_content_id)
							INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON(lc.`content_id`=fg.`content_id`)
						  ORDER BY level DESC, branch, lc.`title`";
				if( $ret = $gBitDb->GetAssoc( $query, [ $this->mContentId  ] ) ) {
				}
			}
		}
		return $ret;
	}
*/

	public function getBreadcrumbLinks( $pIncludeSelf = false ) {
		global $gBitSystem;
		//$ret['fisheye'] = $gBitSystem->getConfig('site_title');
		$ret = [];
		if( !$this->getField( 'gallery_path' ) ) {
			if( $this->isValid() && $parents = $this->getParentGalleries() ) {
				$gal = current( $parents );
				$this->setGalleryPath( '/'.$gal['gallery_id'] );
			}
		}
		if( $this->getField( 'gallery_path' ) ) {
			$path = explode( '/', ltrim( $this->getField( 'gallery_path' ), '/' ) );
			$p = 0;
			$c = 1;
			$joinSql = '';
			$selectSql = '';//AS title$g, fg$g.gallery_id AS gallery_id$g";
			$whereSql = '';
			$bindVars = [];
			// We need to get min_content_status_id
			$pListHash = [];
			LibertyContent::prepGetList($pListHash);
			foreach( $path as $galleryId ) {
				if( $galleryId ) {
					$p++; $c++;
					$selectSql .= " lc$p.`title` AS `title$p`, fg$p.`gallery_id` AS `gallery_id$p`,";
					$joinSql .= " `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim$p
						INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc$p ON(fgim$p.`gallery_content_id`=lc$p.`content_id`)
						INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery` fg$p ON(fg$p.`content_id`=lc$p.`content_id`),";
					$whereSql .= " fg$p.`gallery_id`=? AND fgim$p.`item_content_id`=lc$c.`content_id` AND lc$p.`content_status_id` > ? AND";
					array_push( $bindVars, $galleryId );
					array_push( $bindVars, $pListHash['min_content_status_id']);
				}
			}
//			$selectSql .= " lc$c.title AS title$c ";//AS title$g, fg$g.gallery_id AS gallery_id$g";
			$joinSql .= " `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim$c
				INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc$c ON(fgim$c.`item_content_id`=lc$c.`content_id`) ";
			$whereSql .= " lc$c.`content_id`=?  AND fgim$c.`gallery_content_id`=lc$p.`content_id` ";
			array_push( $bindVars, $this->mContentId );
			$rs = $this->mDb->getRow( "SELECT ".rtrim( $selectSql, ',')." FROM ".rtrim( $joinSql, ',')." WHERE $whereSql", $bindVars );
			if( !empty( $rs ) ) {
				for( $i = 1; $i <= (count( $rs ) / 2); $i++ ) {
					$ret[$rs['gallery_id'.$i]] = $rs['title'.$i];
				}
			}
		}

		if( $this->isValid() && $pIncludeSelf && is_a( $this, '\Bitweaver\Fisheye\FisheyeGallery' ) ) {
			$ret[$this->mGalleryId] = $this->getTitle();
		}

		return $ret;
	}


	public function addToGalleries( $pGalleryArray ) {
		global $gBitSystem;
		if( $this->isValid() ) {
			$inGalleries = $this->mDb->getAssoc( "SELECT `gallery_id`,`gallery_content_id` FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery` fg ON (fgim.`gallery_content_id`=fg.`content_id`) WHERE `item_content_id` = ?", [ $this->mContentId  ] );
			$galleries = [];
			if( is_array( $pGalleryArray ) && count( $pGalleryArray ) ) {
				foreach( $pGalleryArray as $galleryId ) {
					// image has been requested to be put in a new gallery
					if( !is_numeric( $galleryId ) ) {
						switch( $galleryId ) {
							case 'newest':
								$galleryId = $this->mDb->getAssoc( "SELECT `gallery_id` FROM `".BIT_DB_PREFIX."fisheye_gallery` fg INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON (fg.`content_id`=lg.`content_id`) WHERE `user_id` = ? ORDER BY gallery_id DESC", [ $this->getField( 'user_id' ) ] );
								break;
												
						}
					}
					if( empty( $inGalleries[$galleryId] ) ) {
						if( empty( $galleries[$galleryId] ) ) {
							if( $galleries[$galleryId] = FisheyeGallery::lookup( [ 'gallery_id' => $galleryId ] ) ) {
								$galleries[$galleryId]->load();
							}
						}
						if( $galleries[$galleryId] && $galleries[$galleryId]->isValid() ) {
							if( $galleries[$galleryId]->hasUserPermission( 'p_fisheye_upload', true, false ) || $galleries[$galleryId]->isPublic() ) {
								if( $gBitSystem->isFeatureActive( 'fisheye_gallery_default_sort_mode' ) ) {
									$pos = null;
								} else {
									$query = "SELECT MAX(`item_position`)
											  FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` fgim
												INNER JOIN `".BIT_DB_PREFIX."fisheye_gallery` fg ON(fgim.`gallery_content_id`=fg.`content_id`)
											  WHERE fg.`gallery_id`=?";
									$pos = $this->mDb->getOne( $query, [ $galleryId ] ) + 10;
								}

								$galleries[$galleryId]->addItem( $this->mContentId, $pos );
							} else {
								$this->mErrors[] = "You do not have permission to attach ".$this->getTitle()." to ".$galleries[$galleryId]->getTitle();
							}
						}
					} else {
						// image already in an existing gallery.
						unset( $inGalleries[$galleryId] );
					}
				}
			}
			if( count( $inGalleries ) ) {
				// if we have any left over in the inGalleries array, we should delete them. these were the "unchecked" boxes
				foreach( $inGalleries as $galleryId ) {
					$sql = "DELETE FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` WHERE `gallery_content_id` = ? AND `item_content_id` = ?";
					$rs = $this->mDb->getOne($sql, [ $galleryId, $this->mContentId ] );
				}
			}
		}
	}

	public function isPublic() {
		if( $this->isValid() ) {
			return $this->getPreference( 'is_public' ) == 'y';
		}
		return false;
	}

	public function isInGallery( $pGalleryContentId, $pItemContentId = null) {
		if( !$this->verifyId( $pItemContentId ) ) {
			$pItemContentId = $this->mContentId;
		}
		$ret = false;
		if ( is_numeric( $this->mGalleryId ) && is_numeric( $pGalleryContentId ) ) {

			if( $this->mDb->isAdvancedPostgresEnabled() ) {
				global $gBitDb, $gBitSmarty;
				// This code pulls all branches for the current node and determines if there is a path from this content to the root
				// without hitting a security_id. If there is clear path it returns true. If there is a security_id, then
				// it determines if the current user has permission
				$query = "SELECT branch,level,cb_item_content_id,cb_gallery_content_id
						  FROM connectby('`".BIT_DB_PREFIX."fisheye_gallery_image_map`', '`gallery_content_id`', '`item_content_id`', ?, 0, '/') AS t(`cb_gallery_content_id` int,`cb_item_content_id` int, `level` int, `branch` text)
						  WHERE `cb_gallery_content_id`=?
						  ORDER BY branch
						";
				if ( $this->mDb->getOne($query, [  $pItemContentId, $pGalleryContentId ] ) ) {
					$ret = true;
				}
			} else {
				$sql = "SELECT count(`item_content_id`) as `item_count`
						FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map`
						WHERE `gallery_content_id` = ? AND `item_content_id` = ?";
				$rs = $this->mDb->getRow($sql, [ $pGalleryContentId, $pItemContentId ] );
				if ($rs['item_count'] > 0) {
					$ret = true;
				}
			}
		}
		return $ret;
	}

}