<?php
/**
 * TV season — extends FisheyeImage with content_type_guid='fisheyeseason'.
 *
 * The real, folder-leaf content_id in the show->season->episode tree (a "show" itself has no
 * content_id at all - it's a computed, FoodDay-pattern browsing level over its seasons, not yet
 * built - see fisheye.md's 2026-09-02 entry). Ring-fences season-level metadata (genre/director/
 * writer/star/content_rating/duration, IMDB+TheTVDB links) plus the EPISODE xref item away from
 * plain fisheyeimage photo rows and from FisheyeFilm/FisheyeAlbum's own item sets - no other
 * behavioural difference from FisheyeImage, same pattern as Contact/ContactPerson/ContactBusiness.
 *
 * @package fisheye
 */
namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

define( 'FISHEYESEASON_CONTENT_TYPE_GUID', 'fisheyeseason' );

class FisheyeSeason extends FisheyeImage {

	public function __construct( $pImageId = null, $pContentId = null ) {
		parent::__construct( $pImageId, $pContentId );
		$this->mContentTypeGuid = FISHEYESEASON_CONTENT_TYPE_GUID;
		$this->registerContentType( FISHEYESEASON_CONTENT_TYPE_GUID, [
			'content_type_guid' => FISHEYESEASON_CONTENT_TYPE_GUID,
			'content_name'      => 'TV Season',
			'handler_class'     => 'FisheyeSeason',
			'handler_package'   => 'fisheye',
			'handler_file'      => 'FisheyeSeason.php',
			'maintainer_url'    => 'https://www.bitweaver.org',
		] );
		// mPackageGuid='fisheye' is set automatically by registerContentType()
		// because handler_package('fisheye') != content_type_guid('fisheyeseason').
	}

	/**
	 * Override LibertyContent::getEditUrl()'s generic '<package>/edit.php' default - same fatal-
	 * error bug FisheyeFilm hit live 2026-09-02 ("Call to undefined method ...::getAllLayouts()"),
	 * fisheye's own edit.php being the GALLERY edit page, not a season's. See FisheyeFilm's own
	 * identical override for the fuller explanation.
	 *
	 * @param int|null $pContentId
	 * @param array|null $pMixed
	 * @return string
	 */
	public function getEditUrl( $pContentId = null, $pMixed = null ) {
		$contentId = \Bitweaver\BitBase::verifyId( $pContentId ) ? $pContentId : $this->mContentId;
		$ret = FISHEYE_PKG_URL.'edit_season.php?content_id='.$contentId;
		foreach( (array)$pMixed as $key => $value ) {
			if( $key !== 'content_id' ) {
				$ret .= '&'.$key.'='.$value;
			}
		}
		return $ret;
	}

	/**
	 * A season has no mime attachment of its own at all (no single file - it's a pure metadata
	 * container over its episodes' own xref rows, see fisheye.md's 2026-09-01/02 entries) so
	 * FisheyeImage::getThumbnailUrl()'s mStorage-derived thumbnail_url is never set, and the
	 * inherited LibertyContent::getThumbnailUri() falls through to its generic package-icon
	 * fallback (empty here - fisheye has no icons/pkg_fisheye.png) - found live 2026-09-02:
	 * the 'Inspector Morse' show gallery showed no thumbnail at all in the TV Shows gallery,
	 * traced to this season (its only member) having nothing to pass up the chain via
	 * FisheyeGallery::getThumbnailUri()'s preview_content bubbling.
	 *
	 * Overridden here (not on FisheyeImage/FisheyeFilm, which already have working mime-derived
	 * thumbnails) to use this season's own first 'image' xref row instead - lowest xorder, i.e.
	 * the primary poster (see reloadPlexImages()'s xorder convention). Empty until Reload Images
	 * has actually been run once.
	 *
	 * @return string
	 */
	public function getThumbnailUri( string $pSize = 'small' ): string {
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
	 * Locate this season in the local Plex library. A season has no file of its own to match by
	 * (unlike a film) - matched instead via one of its own episodes' file path (an 'episode'
	 * xref row's xkey_ext), walking Plex's own metadata_items.parent_id from that episode
	 * (metadata_type=4) up to its season (metadata_type=3) in a single join. Confirmed against
	 * real data 2026-09-02: Inspector Morse S01E01's episode metadata_item (id 1870) has
	 * parent_id 1869, which is exactly the 'Series 1' season row under the show (id 1832).
	 *
	 * Resolves the TV-specific per-show storage root (mime_film_get_tvshow_storage_root(),
	 * A-M/N-Z split) via the show's own title - found by walking this season's parent gallery
	 * (the show-level FisheyeGallery it's linked into, e.g. 'Inspector Morse' - see fisheye.md's
	 * Collections entry) rather than any field on the season itself, which doesn't carry the
	 * show's name directly.
	 *
	 * @return array{db:\PDO,id:int,root:string}|null  null if unconfigured or no match found
	 */
	private function matchPlexSeasonMetadataItem(): ?array {
		global $gBitSystem;

		$dbPath = $gBitSystem->getConfig( 'fisheye_plex_db_path', '' );
		if( empty( $dbPath ) || !is_file( $dbPath ) ) {
			return null;
		}

		$this->loadXrefInfo();
		$episodeXref = $this->mXrefInfo ? $this->mXrefInfo->findRowByItem( 'episode' ) : null;
		if( !$episodeXref || empty( $episodeXref['xkey_ext'] ) ) {
			return null;
		}

		$parents = $this->getParentGalleries();
		if( empty( $parents ) ) {
			return null;
		}
		$showTitle = current( $parents )['title'] ?? '';
		if( empty( $showTitle ) ) {
			return null;
		}

		$root = \Bitweaver\Liberty\mime_film_get_tvshow_storage_root( $showTitle );
		if( empty( $root ) ) {
			return null;
		}

		$realPath = realpath( $root.$episodeXref['xkey_ext'] );
		if( empty( $realPath ) ) {
			return null;
		}

		try {
			$plexDb = new \PDO( 'sqlite:'.$dbPath );
		} catch( \Exception $e ) {
			return null;
		}

		$stmt = $plexDb->prepare(
			"SELECT mi.parent_id FROM media_parts mp
			 JOIN media_items mi2 ON mi2.id = mp.media_item_id
			 JOIN metadata_items mi ON mi.id = mi2.metadata_item_id
			 WHERE mp.file = ? AND mi.metadata_type = 4"
		);
		$stmt->execute( [ $realPath ] );
		$seasonMetadataItemId = $stmt->fetchColumn();
		if( !$seasonMetadataItemId ) {
			return null;
		}

		return [ 'db' => $plexDb, 'id' => (int)$seasonMetadataItemId, 'root' => $root ];
	}

	/**
	 * Fetch alternate poster/backdrop images from Plex for this season, same shape as
	 * FisheyeFilm::reloadPlexImages() (per-type idempotency, w342/w780 TMDB sizes, 5-per-type
	 * cap, xref-based storage - see that method's own docblock and fisheye.md's 2026-09-02
	 * entries for the fuller reasoning, not repeated here). Differences specific to a season:
	 * matched via matchPlexSeasonMetadataItem() (no file of its own to match by directly), and
	 * the shared `images/` folder lives under the TV-specific per-show root rather than
	 * fisheye_disk_storage_root, since a season has no single source file to derive a filename
	 * basename from - this season's own title is used instead (e.g. 'Inspector Morse - Series 1').
	 *
	 * @return array Summary of what was found/stored, for the calling page's result display.
	 */
	public function reloadPlexImages(): array {
		global $gBitSystem;
		$summary = [ 'matched' => false, 'items' => [] ];

		$plexMatch = $this->matchPlexSeasonMetadataItem();
		if( !$plexMatch ) {
			return $summary;
		}
		$summary['matched'] = true;
		$metadataItemId = $plexMatch['id'];
		$root = $plexMatch['root'];

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
