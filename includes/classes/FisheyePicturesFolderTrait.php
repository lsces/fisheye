<?php
/**
 * An item's pictures folder: a plain fisheye gallery, Images / <Types> / <title>, holding every picture of the item that is not one of its
 * own small tile images. Nothing is stored on the item - the folder carries a preference (<type>_content_id) pointing back at it, so
 * the lookup is the folder's, and the folder can be moved, renamed or protected like any gallery. Any LibertyContent that is shown
 * with pictures can use this: media items (via FisheyeMediaTrait) and contacts alike.
 *
 * @package fisheye
 */
namespace Bitweaver\Fisheye;

trait FisheyePicturesFolderTrait {

	/** The title the item's own folder is given - the item's title, unless a type words it differently. */
	public function picturesFolderTitle(): string {
		return (string)$this->getTitle();
	}

	/**
	 * The title of the gallery, under 'Images', that holds the folders of this kind of item: "Books" for a book, "Films" for a film. A type
	 * whose guid does not read well as a plural (a contact) overrides it.
	 */
	public function picturesFolderTypes(): string {
		return ucfirst( preg_replace( '/^fisheye/', '', (string)$this->getContentType() ) ).'s';
	}

	/**
	 * The preference a pictures folder carries to point back at this item - the type's guid without the 'fisheye'
	 * prefix, so 'book_content_id' for a book, 'film_content_id' for a film (an author's gallery carries
	 * 'author_contact_id' the same way).
	 */
	public function picturesFolderPreference(): string {
		return preg_replace( '/^fisheye/', '', (string)$this->getContentType() ).'_content_id';
	}

	/**
	 * This item's pictures folder - a plain fisheye gallery, Images / <Types> / <title>, holding every other picture
	 * of it (the 'image' xref rows stay the small pictures the item's own page uses). Found by the preference the
	 * folder carries, so there is nothing to keep in step on the item itself.
	 *
	 * @return array|null  'content_id' and 'gallery_id' of the folder, null when there is none yet
	 */
	public function getPicturesFolder(): ?array {
		if( !$this->mContentId ) {
			return null;
		}
		$row = $this->mDb->getRow(
			"SELECT g.`content_id`, g.`gallery_id` FROM `".BIT_DB_PREFIX."liberty_content_prefs` p
			 JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.`content_id` = p.`content_id` AND lc.`content_type_guid` = 'fisheyegallery'
			 JOIN `".BIT_DB_PREFIX."fisheye_gallery` g ON g.`content_id` = p.`content_id`
			 WHERE p.`pref_name` = ? AND p.`pref_value` = ?",
			[ $this->picturesFolderPreference(), (string)$this->mContentId ]
		);
		return $row ? [ 'content_id' => (int)$row['content_id'], 'gallery_id' => (int)$row['gallery_id'] ] : null;
	}

	/**
	 * This item's pictures folder, made (with the Images and Images / <Types> galleries above it) when it is not
	 * there yet. The folder takes the fisheye default layout.
	 *
	 * @return array  'content_id' and 'gallery_id', plus 'created' => true when it was made now, or 'error'
	 */
	public function ensurePicturesFolder(): array {
		if( $existing = $this->getPicturesFolder() ) {
			return $existing;
		}
		$types = $this->picturesFolderTypes();
		$parentContentId = null;
		foreach( [ 'Images', $types ] as $title ) {
			$found = $this->findOrMakePicturesGallery( $title, $parentContentId );
			if( !$found ) {
				return [ 'error' => 'The '.$title.' gallery could not be made.' ];
			}
			$parentContentId = $found;
		}
		$folder = $this->findOrMakePicturesGallery( $this->picturesFolderTitle(), $parentContentId, true );
		if( !$folder ) {
			return [ 'error' => 'The pictures folder could not be made.' ];
		}
		$gallery = new FisheyeGallery( null, $folder );
		$gallery->load();
		$gallery->storePreference( $this->picturesFolderPreference(), (string)$this->mContentId );
		return [ 'content_id' => $folder, 'gallery_id' => (int)$gallery->mGalleryId, 'created' => true ];
	}

	/**
	 * The images in this item's pictures folder, for the Images tab to list beside the item's own 'image' rows.
	 * Each: 'content_id', 'title', 'thumb' and 'url' (the image's own page). Nothing is stored on the item for them.
	 *
	 * @return array[]  at most $pLimit, in the folder's order
	 */
	public function getPicturesFolderImages( int $pLimit = 60 ): array {
		$folder = $this->getPicturesFolder();
		if( !$folder ) {
			return [];
		}
		$rows = $this->mDb->getAll(
			"SELECT lc.`content_id`, lc.`title` FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` m
			 JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.`content_id` = m.`item_content_id`
			 WHERE m.`gallery_content_id` = ? AND lc.`content_type_guid` <> 'fisheyegallery'
			 ORDER BY m.`item_position`, lc.`content_id`",
			[ $folder['content_id'] ]
		) ?: [];
		$images = [];
		foreach( array_slice( $rows, 0, $pLimit ) as $row ) {
			$image = FisheyeImage::lookup( [ 'content_id' => (int)$row['content_id'] ] );
			if( $image && $image->isValid() ) {
				$images[] = [ 'content_id' => (int)$row['content_id'], 'title' => (string)$row['title'], 'thumb' => $image->getThumbnailUrl( 'small' ), 'url' => $image->getDisplayUrl() ];
			}
		}
		return $images;
	}

	/** content_id of the plain gallery with this title under a parent (null = top level), made when it is not there. */
	protected function findOrMakePicturesGallery( string $pTitle, ?int $pParentContentId, bool $pAlwaysNew = false ): ?int {
		$contentId = null;
		if( !$pAlwaysNew ) {
			if( $pParentContentId ) {
				$contentId = $this->mDb->getOne(
					"SELECT lc.`content_id` FROM `".BIT_DB_PREFIX."fisheye_gallery_image_map` m
					 JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.`content_id` = m.`item_content_id`
					 WHERE m.`gallery_content_id` = ? AND lc.`title` = ? AND lc.`content_type_guid` = 'fisheyegallery'",
					[ $pParentContentId, $pTitle ]
				);
			} elseif( $topId = FisheyeGallery::getTopGalleryId( $pTitle ) ) {
				$contentId = $this->mDb->getOne( "SELECT `content_id` FROM `".BIT_DB_PREFIX."fisheye_gallery` WHERE `gallery_id` = ?", [ $topId ] );
			}
		}
		if( $contentId ) {
			return (int)$contentId;
		}
		$new = new FisheyeGallery();
		$storeHash = [ 'title' => $pTitle ];
		if( !$new->store( $storeHash ) ) {
			return null;
		}
		if( $pParentContentId ) {
			$new->addToGalleries( [ (int)$this->mDb->getOne( "SELECT `gallery_id` FROM `".BIT_DB_PREFIX."fisheye_gallery` WHERE `content_id` = ?", [ $pParentContentId ] ) ] );
		}
		return (int)$new->mContentId;
	}
}
