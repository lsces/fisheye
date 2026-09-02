<?php
/**
 * TV show ("program") — extends FisheyeGallery (not FisheyeImage) with
 * content_type_guid='fisheyeprogram'.
 *
 * A phantom subclass exactly like FisheyeFilm/FisheyeSeason/FisheyeAlbum, just built on
 * FisheyeGallery instead of FisheyeImage - a show is genuinely a gallery (it holds its seasons
 * as real members via addItem()/loadImages(), completely unchanged from plain FisheyeGallery
 * behaviour) but also needs its own metadata (genre/cast/external links) and, critically, its
 * own genuinely selected thumbnail - a plain FisheyeGallery has no metadata of its own and picks
 * its thumbnail by bubbling down into whichever member it happens to land on, which breaks
 * entirely once that member (a FisheyeSeason) has no mime attachment to derive one from. Added
 * 2026-09-02 - see fisheye.md's same-dated "program liberty object" entry for the design
 * discussion that led here (Lester: "The correct way to fix this is to use the program liberty
 * object to store all the program data and a selected thumbnail").
 *
 * The existing 'Inspector Morse' gallery (content_id=4069) was retyped from 'fisheyegallery' to
 * this guid as part of introducing it - same content_id, same fisheye_gallery_image_map rows
 * (season membership untouched), just a different handler class and its own xref items from
 * here on.
 *
 * @package fisheye
 */
namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

define( 'FISHEYEPROGRAM_CONTENT_TYPE_GUID', 'fisheyeprogram' );

class FisheyeProgram extends FisheyeGallery {

	public function __construct( $pGalleryId = null, $pContentId = null ) {
		parent::__construct( $pGalleryId, $pContentId );
		$this->mContentTypeGuid = FISHEYEPROGRAM_CONTENT_TYPE_GUID;
		$this->registerContentType( FISHEYEPROGRAM_CONTENT_TYPE_GUID, [
			'content_type_guid' => FISHEYEPROGRAM_CONTENT_TYPE_GUID,
			'content_name'      => 'TV Show',
			'handler_class'     => 'FisheyeProgram',
			'handler_package'   => 'fisheye',
			'handler_file'      => 'FisheyeProgram.php',
			'maintainer_url'    => 'https://www.bitweaver.org',
		] );
		// mPackageGuid='fisheye' is set automatically by registerContentType()
		// because handler_package('fisheye') != content_type_guid('fisheyeprogram').
	}

	/**
	 * A show gets its own dedicated view page (list_program.php - header facts + a grid of its
	 * own season members) rather than the generic gallery view.php a plain FisheyeGallery uses -
	 * "extending for fisheyeprogram would also slot into directing to list_program as a program
	 * specific 'gallery' of seasons" (Lester, 2026-09-02). Every gallery-grid template links via
	 * getDisplayUrl() generically (see fisheye_fixed_grid_inc.tpl), so overriding this alone is
	 * enough to redirect from the TV Shows gallery grid with no template changes.
	 *
	 * @return string
	 */
	public function getDisplayUrl( $pContentId = null, $pMixed = null ) {
		$contentId = \Bitweaver\BitBase::verifyId( $pContentId ) ? $pContentId : $this->mContentId;
		return FISHEYE_PKG_URL.'list_program.php?content_id='.$contentId;
	}

	/**
	 * A show's own selected thumbnail - its first (lowest-xorder, i.e. primary poster) 'image'
	 * xref row, same convention as FisheyeSeason's own override, rather than FisheyeGallery's
	 * inherited getThumbnailImage()/loadThumbnail() bubbling down into whichever member it
	 * happens to land on (which broke entirely for a season member with no mime attachment -
	 * the root cause found live 2026-09-02, see FisheyeSeason's own override for the fuller
	 * trace). This is the actual point of being a distinct content type at all: a real, directly-
	 * owned thumbnail instead of an inherited/accidental one.
	 *
	 * @return string
	 */
	public function getThumbnailUri( $pSize = 'small', $pInfoHash = null ) {
		if( $this->isValid() ) {
			$this->loadXrefInfo();
			if( $this->mXrefInfo && ( $imageXref = $this->mXrefInfo->findRowByItem( 'image' ) ) ) {
				return FISHEYE_PKG_URL.'view_extra_image.php?xref_id='.$imageXref['xref_id'];
			}
		}
		return '';
	}

	public function getThumbnailUrl( string $pSize = 'small', ?array $pInfoHash = null, ?int $pSecondaryId = null, ?int $pDefault = null ): string|null {
		return $this->getThumbnailUri( $pSize ) ?: null;
	}

	/**
	 * Short-circuits FisheyeGallery::getThumbnailImage()'s own recursion, which treats ANY
	 * nested FisheyeGallery-family member as "just another gallery to bubble down through" via
	 * `is_a($ret, FisheyeGallery)` - since FisheyeProgram itself extends FisheyeGallery, a parent
	 * gallery's own getThumbnailImage() (e.g. 'TV Shows', resolving a random member) would
	 * otherwise recurse straight past this show's own selected image and back into whichever
	 * season it happens to contain, undoing the entire point of this class existing - found live
	 * 2026-09-02, immediately after Reload Images populated this show's own images and the
	 * top-level galleries listing *still* showed the season's poster instead.
	 *
	 * Falls back to the normal inherited behaviour if this show has no image of its own yet
	 * (e.g. before Reload Images has ever been run) rather than returning nothing.
	 *
	 * @return static|mixed
	 */
	public function getThumbnailImage( $pContentId=null, $pThumbnailContentId=null, $pThumbnailContentType=null ) {
		if( $this->isValid() ) {
			$this->loadXrefInfo();
			if( $this->mXrefInfo && $this->mXrefInfo->findRowByItem( 'image' ) ) {
				return $this;
			}
		}
		return parent::getThumbnailImage( $pContentId, $pThumbnailContentId, $pThumbnailContentType );
	}

	/**
	 * Locate this show in the local Plex library by title, matched against Plex's own show-level
	 * entries (metadata_type=2) directly - unlike a film or season, a show's own title (this
	 * object's getTitle()) already IS the real show name, so no file-path matching or walking up
	 * a parent_id chain is needed at all. Reliable enough for a personal single-library
	 * collection; a real title collision across two different shows isn't a case this needs to
	 * handle.
	 *
	 * @return array{db:\PDO,id:int}|null  null if unconfigured or no match found
	 */
	private function matchPlexShowMetadataItem(): ?array {
		global $gBitSystem;

		$dbPath = $gBitSystem->getConfig( 'fisheye_plex_db_path', '' );
		if( empty( $dbPath ) || !is_file( $dbPath ) ) {
			return null;
		}

		try {
			$plexDb = new \PDO( 'sqlite:'.$dbPath );
		} catch( \Exception $e ) {
			return null;
		}

		$stmt = $plexDb->prepare( "SELECT id FROM metadata_items WHERE metadata_type = 2 AND title = ?" );
		$stmt->execute( [ $this->getTitle() ] );
		$showMetadataItemId = $stmt->fetchColumn();
		if( !$showMetadataItemId ) {
			return null;
		}

		return [ 'db' => $plexDb, 'id' => (int)$showMetadataItemId ];
	}

	/**
	 * Fetch alternate poster/backdrop images from Plex for this show, same shape as
	 * FisheyeFilm::reloadPlexImages()/FisheyeSeason::reloadPlexImages() (per-type idempotency,
	 * w342/w780 TMDB sizes, 5-per-type cap, xref-based storage - see FisheyeFilm's own docblock
	 * for the fuller reasoning, not repeated here). Storage root is the TV-specific per-show root
	 * resolved directly from this show's own title (no episode file needed to derive it, unlike
	 * a season); filename basename is this show's own title too.
	 *
	 * @return array Summary of what was found/stored, for the calling page's result display.
	 */
	public function reloadPlexImages(): array {
		global $gBitSystem;
		$summary = [ 'matched' => false, 'items' => [] ];

		$plexMatch = $this->matchPlexShowMetadataItem();
		if( !$plexMatch ) {
			return $summary;
		}
		$summary['matched'] = true;
		$metadataItemId = $plexMatch['id'];

		$this->loadXrefInfo();
		$existingImagePaths = [];
		$xorder = 0;
		foreach( $this->mXrefInfo->allXrefs() as $xref ) {
			if( $xref['item'] === 'image' ) {
				$existingImagePaths[] = $xref['xkey_ext'];
				$xorder = max( $xorder, (int)$xref['xorder'] );
			}
		}

		$plexToken = $gBitSystem->getConfig( 'fisheye_plex_token', '' );
		if( empty( $plexToken ) ) {
			$summary['items'][] = 'fisheye_plex_token is not configured - the posters/arts endpoints need it.';
			return $summary;
		}

		$root = \Bitweaver\Liberty\mime_film_get_tvshow_storage_root( $this->getTitle() );
		if( empty( $root ) ) {
			$summary['items'][] = 'fisheye_tvshow_storage_root is not configured for this show.';
			return $summary;
		}
		$imagesDir = $root.'images/';
		KernelTools::mkdir_p( $imagesDir );

		$baseName = $this->getTitle();
		$thumbSizes = [ 'poster' => 'w342', 'art' => 'w780' ];

		foreach( [ 'poster' => 'posters', 'art' => 'arts' ] as $type => $endpoint ) {
			$alreadyHasType = false;
			foreach( $existingImagePaths as $path ) {
				if( str_contains( $path, "-$type-" ) ) {
					$alreadyHasType = true;
					break;
				}
			}
			if( $alreadyHasType ) {
				$summary['items'][] = "$type: already has stored images - not re-fetched (delete all of this type first to force a re-fetch of it).";
				continue;
			}

			$apiUrl = "http://localhost:32400/library/metadata/$metadataItemId/$endpoint?X-Plex-Token=".urlencode( $plexToken );
			$xml = @file_get_contents( $apiUrl );
			if( $xml === false || !preg_match_all( '#<Photo[^>]*\bkey="(https://[^"]+)"#', $xml, $matches ) ) {
				continue;
			}
			$fetched = 0;
			foreach( $matches[1] as $imageUrl ) {
				if( $fetched >= 5 ) {
					break;
				}
				$imageUrl = str_replace( '/original/', '/'.$thumbSizes[$type].'/', html_entity_decode( $imageUrl ) );
				$imageData = @file_get_contents( $imageUrl );
				if( $imageData === false ) {
					continue;
				}
				$fetched++;
				$fileName = "$baseName-$type-$fetched.jpg";
				$relativePath = 'images/'.$fileName;
				if( file_put_contents( $imagesDir.$fileName, $imageData ) === false ) {
					continue;
				}
				$xorder++;
				$xrefParamHash = [ 'content_id' => $this->mContentId, 'item' => 'image', 'xkey_ext' => $relativePath, 'xorder' => $xorder ];
				$this->storeXref( $xrefParamHash );
				$summary['items'][] = "$type: $relativePath";
			}
		}

		return $summary;
	}
}
