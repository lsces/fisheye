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
	 * A show's own selected thumbnail. Fixed properly 2026-09-02 (second attempt - see
	 * fisheye.md's same-dated "real attachment" entry): FisheyeGallery descends from LibertyMime
	 * just like a real photo does, so this show has its own unused attachment slot -
	 * reloadPlexImages() stores a real image attachment there via attachThumbnail(), same as a
	 * normal upload would. Read here via LibertyMime's own storage-based lookup (explicit class
	 * scoping, since FisheyeGallery's own getThumbnailUrl() override always bubbles to a member
	 * instead) rather than the earlier xref-based approach, which (a) never generated an actual
	 * small thumbnail, just linked to the same file shown in the Images tab, and (b) broke for a
	 * genuinely anonymous visitor - every xref group in media.php, images included, is
	 * role_id=3 ('Registered'), so loadXrefInfo() silently returned nothing for a guest even
	 * though the file itself was already public.
	 *
	 * @return string
	 */
	public function getThumbnailUri( $pSize = 'small', $pInfoHash = null ) {
		return $this->getThumbnailUrl( $pSize ) ?: '';
	}

	public function getThumbnailUrl( string $pSize = 'small', ?array $pInfoHash = null, ?int $pSecondaryId = null, ?int $pDefault = null ): string|null {
		if( $this->isValid() ) {
			// Explicit class scoping, not $this->load() - FisheyeGallery::load() shortcuts
			// straight to LibertyContent::load() (same shortcut its own store() override takes),
			// so mStorage is never populated via the normal load() path at all. Found live
			// 2026-09-02: attachThumbnail() had stored a real attachment correctly, but this
			// method still read back empty because $this->load() never touched mStorage.
			\Bitweaver\Liberty\LibertyMime::load();
			$url = \Bitweaver\Liberty\LibertyMime::getThumbnailUrl( $pSize, $pInfoHash, $pSecondaryId, $pDefault );
			if( !empty( $url ) ) {
				return $url;
			}
		}
		return null;
	}

	/**
	 * Short-circuits FisheyeGallery::getThumbnailImage()'s own recursion, which treats ANY
	 * nested FisheyeGallery-family member as "just another gallery to bubble down through" via
	 * `is_a($ret, FisheyeGallery)` - since FisheyeProgram itself extends FisheyeGallery, a parent
	 * gallery's own getThumbnailImage() (e.g. 'TV Shows', resolving a random member) would
	 * otherwise recurse straight past this show's own real attachment and back into whichever
	 * season it happens to contain, undoing the entire point of this class existing - found live
	 * 2026-09-02, immediately after Reload Images populated this show's own images and the
	 * top-level galleries listing *still* showed the season's poster instead.
	 *
	 * Falls back to the normal inherited behaviour if this show has no attachment of its own yet
	 * (e.g. before Reload Images has ever been run) rather than returning nothing.
	 *
	 * @return static|mixed
	 */
	public function getThumbnailImage( $pContentId=null, $pThumbnailContentId=null, $pThumbnailContentType=null ) {
		if( $this->isValid() ) {
			// Explicit class scoping - see getThumbnailUrl()'s identical comment above.
			\Bitweaver\Liberty\LibertyMime::load();
			if( !empty( $this->mStorage ) ) {
				return $this;
			}
		}
		return parent::getThumbnailImage( $pContentId, $pThumbnailContentId, $pThumbnailContentType );
	}

	/**
	 * Store real image bytes (fetched from a URL, or read from a local file - file_get_contents()
	 * handles both transparently) as this show's own single image attachment - see
	 * FisheyeSeason::attachThumbnail()'s identical docblock for the mechanism detail (synthetic
	 * `_files_override` fed to LibertyMime's own upload path, explicit class scoping to bypass
	 * FisheyeGallery's own store() override, which never touches attachments at all).
	 *
	 * Reuses the existing attachment slot if one's already there - see FisheyeSeason::
	 * attachThumbnail()'s identical comment for why (a real bug found live 2026-09-02).
	 *
	 * @param string $pSourcePathOrUrl
	 * @return bool
	 */
	private function attachThumbnail( string $pSourcePathOrUrl ): bool {
		$imageData = @file_get_contents( $pSourcePathOrUrl );
		if( empty( $imageData ) ) {
			return false;
		}
		$tmpFile = tempnam( sys_get_temp_dir(), 'fisheye_thumb_' );
		file_put_contents( $tmpFile, $imageData );

		// Explicit class scoping - see getThumbnailUrl()'s comment for why $this->load() alone
		// wouldn't populate mStorage here (FisheyeGallery::load() shortcuts past it).
		\Bitweaver\Liberty\LibertyMime::load();
		$existingAttachmentId = array_key_first( $this->mStorage ) ?: null;
		$upload = [ 'name' => 'thumbnail.jpg', 'type' => 'image/jpeg', 'tmp_name' => $tmpFile, 'error' => 0, 'size' => filesize( $tmpFile ) ];
		if( $existingAttachmentId ) {
			$upload['attachment_id'] = $existingAttachmentId;
		}
		$pParamHash = [
			'content_id' => $this->mContentId,
			'skip_content_store' => true,
			'_files_override' => [ $upload ],
		];
		$ret = \Bitweaver\Liberty\LibertyMime::store( $pParamHash );
		@unlink( $tmpFile );
		if( $ret ) {
			// Explicit class scoping - see getThumbnailUrl()'s comment for why $this->load()
			// alone wouldn't refresh mStorage here.
			\Bitweaver\Liberty\LibertyMime::load();
		}
		return $ret;
	}

	/**
	 * Promote one of this show's already-downloaded 'image' xref alternates (a local file under
	 * the TV storage root's images/ folder) into the real, single thumbnail attachment - the
	 * manual "change it" action Lester asked for, since the auto-picked (Plex's own currently-
	 * selected poster) default is sometimes not the best of the available alternates.
	 *
	 * @param string $pRelativePath  an 'image' xref row's own xkey_ext value
	 * @return bool
	 */
	public function promoteImageToThumbnail( string $pRelativePath ): bool {
		$root = $this->getImageStorageRoot();
		if( empty( $root ) || !is_file( $root.$pRelativePath ) ) {
			return false;
		}
		return $this->attachThumbnail( $root.$pRelativePath );
	}

	/**
	 * The storage root this show's own 'image' xref rows live relative to - the TV-specific
	 * per-show root (A-M/N-Z split), resolved directly from this show's own title (it already IS
	 * the real show name, unlike a season). NOT the same root as a plain film's
	 * fisheye_disk_storage_root - see FisheyeSeason::getImageStorageRoot()'s identical docblock
	 * for why edit_xref.php calls this generically rather than assuming one shared root.
	 *
	 * @return string empty string if the config is unset
	 */
	public function getImageStorageRoot(): string {
		return \Bitweaver\Liberty\mime_film_get_tvshow_storage_root( $this->getTitle() );
	}

	/**
	 * Generic file-lifecycle hook liberty/edit_xref.php calls (via method_exists()) when a file
	 * is uploaded to replace an xref row's own referenced file - see FisheyeFilm::
	 * replaceXrefFile()'s identical docblock for the fuller reasoning (same method, same shape,
	 * just this show's own storage root).
	 *
	 * @param string $pItem
	 * @param string $pXkeyExt
	 * @param string $pTmpPath  the uploaded file's own tmp_name
	 * @return bool
	 */
	public function replaceXrefFile( string $pItem, string $pXkeyExt, string $pTmpPath ): bool {
		if( $pItem !== 'image' || empty( $pXkeyExt ) ) {
			return false;
		}
		$root = $this->getImageStorageRoot();
		if( empty( $root ) ) {
			return false;
		}
		return move_uploaded_file( $pTmpPath, $root.$pXkeyExt );
	}

	/**
	 * Generic file-lifecycle hook liberty/edit_xref.php calls (via method_exists()) on a real
	 * hard-delete (expunge=3) of an xref row - see FisheyeFilm::deleteXrefFile()'s identical
	 * docblock for the fuller reasoning.
	 *
	 * @param string $pItem
	 * @param string $pXkeyExt
	 * @return bool
	 */
	public function deleteXrefFile( string $pItem, string $pXkeyExt ): bool {
		if( $pItem !== 'image' || empty( $pXkeyExt ) ) {
			return false;
		}
		$root = $this->getImageStorageRoot();
		if( empty( $root ) || !is_file( $root.$pXkeyExt ) ) {
			return false;
		}
		return @unlink( $root.$pXkeyExt );
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

		// Auto-pick the real thumbnail attachment (once only - see FisheyeSeason::
		// reloadPlexImages()'s identical block for the fuller reasoning) from Plex's own
		// currently-selected poster rather than just grabbing whichever alternate comes first.
		if( empty( $this->mStorage ) ) {
			$postersXml = @file_get_contents( "http://localhost:32400/library/metadata/$metadataItemId/posters?X-Plex-Token=".urlencode( $plexToken ) );
			if( $postersXml !== false && preg_match_all( '#<Photo\b[^>]*/>#', $postersXml, $tagMatches ) ) {
				foreach( $tagMatches[0] as $tag ) {
					if( str_contains( $tag, 'selected="1"' ) && preg_match( '#\bthumb="([^"]+)"#', $tag, $m ) ) {
						$thumb = html_entity_decode( $m[1] );
						$thumbUrl = str_starts_with( $thumb, '/' )
							? "http://localhost:32400$thumb".( str_contains( $thumb, '?' ) ? '&' : '?' )."X-Plex-Token=".urlencode( $plexToken )
							: $thumb;
						if( $this->attachThumbnail( $thumbUrl ) ) {
							$summary['items'][] = 'thumbnail: attached from Plex\'s own selected poster';
						}
						break;
					}
				}
			}
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
				$tmpFile = tempnam( sys_get_temp_dir(), 'fisheye_alt_' );
				file_put_contents( $tmpFile, $imageData );
				$resized = self::resizeImageFile( $tmpFile, $imagesDir.$fileName, 400 );
				@unlink( $tmpFile );
				if( !$resized ) {
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
