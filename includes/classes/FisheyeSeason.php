<?php
/**
 * TV season — extends FisheyeImage with content_type_guid='fisheyeseason'.
 *
 * The real, folder-leaf content_id in the show->season->episode tree - an episode itself is one
 * level further down still, a plain 'episode' liberty_xref row under its season's own content_id,
 * not a content object of its own. (Stale note this replaces: a show was originally meant to be a
 * computed, FoodDay-pattern browsing level with no content_id of its own - superseded once
 * FisheyeProgram was built, which gives every show a genuine content_id/gallery, see that class's
 * own docblock.) Ring-fences season-level metadata (genre/director/
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
	 * Override FisheyeImage::getDisplayUrl()'s image_id-keyed default - a season is never a plain
	 * photo, so its display page is view_season.php (facts + episode list, no video player of its
	 * own), the "matching pair" to edit_season.php Lester asked for 2026-09-02 alongside
	 * view_program.php/edit_program.php. Previously fell through to FisheyeImage's generic
	 * getDisplayUrl(), which pointed a season's gallery-grid link at view_image.php - wrong page
	 * for a season, just never noticed since nothing linked to a season directly until
	 * view_program.php's season grid was built.
	 *
	 * @return string
	 */
	public function getDisplayUrl( $pContentId = null, $pMixed = null ) {
		$contentId = \Bitweaver\BitBase::verifyId( $pContentId ) ? $pContentId : $this->mContentId;
		return FISHEYE_PKG_URL.'view_season.php?content_id='.$contentId;
	}

	/**
	 * A season has no mime attachment of its own at all (no single file - it's a pure metadata
	 * container over its episodes' own xref rows, see fisheye.md's 2026-09-01/02 entries), so
	 * FisheyeImage's own mime-derived thumbnail is never populated by the normal load path.
	 *
	 * Fixed properly 2026-09-02 (second attempt - see fisheye.md's same-dated "real attachment"
	 * entry): a season DOES have its own unused attachment slot (FisheyeImage descends from
	 * LibertyMime just like any real photo/film does - it just never had anything stored there),
	 * so reloadPlexImages() now stores a real image attachment via attachThumbnail() the same way
	 * a normal upload would. This reads that attachment directly via LibertyMime's own storage-
	 * based lookup (explicit class scoping, since FisheyeImage's own getThumbnailUrl() override
	 * reads a differently-shaped mInfo field that only its own load() populates) rather than the
	 * earlier xref-based approach, which (a) never generated an actual small thumbnail, just
	 * linked to the same file shown in the Images tab, and (b) broke for a genuinely anonymous
	 * visitor - every xref group in media.php, images included, is role_id=3 ('Registered'), so
	 * loadXrefInfo() silently returned nothing for a guest even though the file itself was
	 * already public. Neither problem exists here - a real attachment gets real generated
	 * thumbnails via the standard, already-correct liberty machinery.
	 *
	 * @return string
	 */
	public function getThumbnailUri( string $pSize = 'small' ): string {
		return $this->getThumbnailUrl( $pSize ) ?: '';
	}

	public function getThumbnailUrl( string $pSize = 'small', ?array $pInfoHash = null, ?int $pSecondaryId = null, ?int $pDefault = null ): string|null {
		if( $this->isValid() ) {
			$this->load();
			$url = \Bitweaver\Liberty\LibertyMime::getThumbnailUrl( $pSize, $pInfoHash, $pSecondaryId, $pDefault );
			if( !empty( $url ) ) {
				return $url;
			}
		}
		return null;
	}

	/**
	 * Store real image bytes (fetched from a URL, or read from a local file - file_get_contents()
	 * handles both transparently) as this content's own single image attachment, via the normal
	 * mime.image.php upload path fed a synthetic upload array (`_files_override`, the same
	 * mechanism edit_image.php uses for a real HTTP upload - see LibertyMime::verify()'s own
	 * comment on this). Called explicitly as \Bitweaver\Liberty\LibertyMime::store() (bypassing
	 * FisheyeGallery/FisheyeImage's own store() overrides where relevant) since attachment
	 * processing lives at that level; 'skip_content_store' avoids redundantly re-saving the
	 * content row itself, this call is attachment-only.
	 *
	 * Reuses the existing attachment slot if one's already there (e.g. a manual
	 * promoteImageToThumbnail() after the auto-pick already ran) rather than always creating a
	 * fresh one - without an explicit attachment_id, LibertyMime::store() always takes the
	 * create path, which collides on liberty_files' primary key the second time round (found
	 * live 2026-09-02: mime_image_store() assumes no row exists yet at its computed file_id).
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

		$this->load();
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
			$this->load();
		}
		return $ret;
	}

	/**
	 * Promote one of this season's already-downloaded 'image' xref alternates (a local file
	 * under the TV storage root's images/ folder) into the real, single thumbnail attachment -
	 * the manual "change it" action Lester asked for, since the auto-picked (Plex's own
	 * currently-selected poster) default is sometimes not the best of the available alternates.
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
	 * The storage root this season's own 'image' xref rows (and 'episode' rows) live relative to
	 * - the TV-specific per-show root (A-M/N-Z split), resolved via the show title found by
	 * walking this season's own parent gallery (see matchPlexSeasonMetadataItem()'s identical
	 * lookup - nothing on the season object itself carries the show's name directly). NOT the
	 * same root as a plain film's fisheye_disk_storage_root - edit_xref.php calls this generically
	 * (via method_exists(), same as FisheyeFilm's own version of this method) rather than
	 * assuming every fisheye content type's images share one root, a real bug found 2026-09-02
	 * that desktop's setup happened to mask (both roots point at /media3/ there; they genuinely
	 * differ on srv9).
	 *
	 * @return string empty string if the show title can't be resolved or the config is unset
	 */
	public function getImageStorageRoot(): string {
		$parents = $this->getParentGalleries();
		$showTitle = $parents ? ( current( $parents )['title'] ?? '' ) : '';
		return $showTitle ? \Bitweaver\Liberty\mime_film_get_tvshow_storage_root( $showTitle ) : '';
	}

	/**
	 * Generic file-lifecycle hook liberty/edit_xref.php calls (via method_exists()) when a file
	 * is uploaded to replace an xref row's own referenced file - see FisheyeFilm::
	 * replaceXrefFile()'s identical docblock for the fuller reasoning (same method, same shape,
	 * just this season's own storage root).
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
	/**
	 * Register a season under an already-registered show, seed it with one real episode file (the
	 * one thing matchPlexSeasonMetadataItem() needs to find the right Plex season - it matches by
	 * an existing 'episode' xref's own file path, chicken-and-egg otherwise), then immediately call
	 * reloadPlexEpisodes() to replace that single seed row with the show's real full episode list
	 * from Plex. Mirrors import_episode_test.php's proven manual sequence (the one-off smoke test
	 * this supersedes - see that file's own docblock) rather than reinventing it, with the season
	 * linked into a real FisheyeProgram gallery instead of that script's plain-FisheyeGallery
	 * fallback (FisheyeProgram didn't exist yet when it was written).
	 *
	 * Season title convention: "<show title> - <season folder name>" (e.g. "Inspector Morse -
	 * Season 1", "Inspector Morse - Specials") - the folder name is used verbatim rather than
	 * trying to normalize "Specials" into a numbered series, since it already reads correctly and
	 * Plex's own season match doesn't depend on this title at all (only on the seeded file path).
	 *
	 * @param string $pShowTitle        Real show title, used to resolve the TV storage root and
	 *                                   build the season title.
	 * @param string $pSeasonFolderName Real subfolder name under the show's own folder, e.g.
	 *                                   "Season 1" or "Specials".
	 * @param int    $pShowContentId    The already-registered FisheyeProgram's content_id to link
	 *                                   this season into.
	 * @return array 'already'=>content_id if a real episode was already seeded (re-running just
	 *               re-syncs from Plex), or 'created'/'episodes' on success, or 'error'=>string on
	 *               failure (including "no episode files found in this folder").
	 */
	public static function registerFromDisk( string $pShowTitle, string $pSeasonFolderName, int $pShowContentId ): array {
		global $gBitDb;

		$seasonTitle = $pShowTitle.' - '.$pSeasonFolderName;
		$alreadySeeded = false;

		$existingContentId = $gBitDb->getOne(
			"SELECT content_id FROM liberty_content WHERE content_type_guid = 'fisheyeseason' AND title = ?",
			[ $seasonTitle ]
		);
		if( $existingContentId ) {
			$season = new FisheyeSeason( null, $existingContentId );
			$season->load();
			$alreadySeeded = (bool)$gBitDb->getOne(
				"SELECT xref_id FROM liberty_xref WHERE content_id = ? AND item = 'episode'",
				[ $existingContentId ]
			);
		} else {
			$season = new FisheyeSeason();
			// store() takes its param by reference - can't pass an array literal directly.
			$storeHash = [ 'title' => $seasonTitle ];
			if( !$season->store( $storeHash ) ) {
				return [ 'error' => implode( '; ', $season->mErrors ) ];
			}
		}

		$showGallery = new FisheyeGallery( null, $pShowContentId );
		$showGallery->load();
		if( !$showGallery->isInGallery( $pShowContentId, $season->mContentId ) ) {
			$showGallery->addItem( $season->mContentId );
		}

		if( !$alreadySeeded ) {
			$root = \Bitweaver\Liberty\mime_film_get_tvshow_storage_root( $pShowTitle );
			$seasonDir = $root.'TV Shows/'.$pShowTitle.'/'.$pSeasonFolderName.'/';
			$seedFile = null;
			if( !empty( $root ) && is_dir( $seasonDir ) ) {
				foreach( scandir( $seasonDir ) as $file ) {
					if( !is_file( $seasonDir.$file ) ) {
						continue;
					}
					if( in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), [ 'mkv', 'mp4', 'm4v', 'avi' ], true ) ) {
						$seedFile = $file;
						break;
					}
				}
			}
			if( empty( $seedFile ) ) {
				return [ 'error' => 'No episode files found in '.$seasonDir ];
			}
			// storeXref() also takes its param by reference - same reason as store() above.
			$xrefHash = [
				'content_id' => $season->mContentId,
				'item'       => 'episode',
				'xkey_ext'   => 'TV Shows/'.$pShowTitle.'/'.$pSeasonFolderName.'/'.$seedFile,
				'edit'       => json_encode( [ 'title' => pathinfo( $seedFile, PATHINFO_FILENAME ) ] ),
				'xorder'     => 1,
			];
			$season->storeXref( $xrefHash );
		}

		$episodes = $season->reloadPlexEpisodes();

		return [ 'created' => $season->mContentId, 'episodes' => $episodes ];
	}

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
	 * Fetch this season's full episode list from Plex - the "Load Episodes" action
	 * (Lester, 2026-09-02), superseding the one-off import_episode_test.php script that had
	 * registered only the one episode it was hand-fed. Rebuild-not-diff, same as every other
	 * reload* method here: every 'episode' xref row for this content_id is deleted before
	 * re-inserting, since 'episode' is multiple=1 and storeXref() has no natural key to update
	 * in place.
	 *
	 * There is no season-level facts panel to populate (Lester, 2026-09-02: "Plex DOESN'T put
	 * anything up on a season page... it's the TV that toggles to display a selected episode's
	 * metadata as you select each" - confirmed directly against the local Plex db: the season's
	 * own metadata_item has empty content_rating/duration and no genre/director/writer/star
	 * taggings at all). Real per-episode facts - director(tag_type 4)/writer(5)/star(6, capped at
	 * 5 same reasoning as every other star cap here)/content_rating/duration - DO exist one level
	 * down, on each episode's own metadata_item (metadata_type=4), confirmed against S01E01 (id
	 * 1870). Genre never exists below show level in Plex's own model, so it's not attempted here -
	 * that's what view_program.php's own facts panel already covers, one level up.
	 *
	 * Each episode's full packet (title/summary/air_date/director/writer/star/content_rating/
	 * duration) is JSON-encoded into the xref row's `data` column (via the 'edit' param key -
	 * see import_episode_test.php's own comment on that non-obvious key name), so a single
	 * already-loaded xref row carries everything view_season.php needs to show when that episode
	 * is selected - no per-episode page/request needed, matching Plex's own smart-TV pattern of
	 * a live highlight-swaps-the-detail-panel interaction rather than navigating away.
	 *
	 * @return array Summary of what was found/stored, for the calling page's result display.
	 */
	public function reloadPlexEpisodes(): array {
		global $gBitSystem;
		$summary = [ 'matched' => false, 'items' => [] ];

		$plexMatch = $this->matchPlexSeasonMetadataItem();
		if( !$plexMatch ) {
			return $summary;
		}
		$plexDb = $plexMatch['db'];
		$seasonMetadataItemId = $plexMatch['id'];
		$root = $plexMatch['root'];

		$realRoot = realpath( $root );
		if( empty( $realRoot ) ) {
			return $summary;
		}
		$realRoot = rtrim( $realRoot, '/' ).'/';

		$plexToken = $gBitSystem->getConfig( 'fisheye_plex_token', '' );
		$imagesDir = $root.'images/';
		if( !empty( $plexToken ) ) {
			KernelTools::mkdir_p( $imagesDir );
		}

		$stmt = $plexDb->prepare(
			"SELECT mi.id, mi.\"index\", mi.title, mi.summary, mi.originally_available_at,
			        mi.content_rating, mi.duration, mp.file
			 FROM metadata_items mi
			 JOIN media_items mi2 ON mi2.metadata_item_id = mi.id
			 JOIN media_parts mp ON mp.media_item_id = mi2.id
			 WHERE mi.parent_id = ? AND mi.metadata_type = 4
			 ORDER BY mi.\"index\""
		);
		$stmt->execute( [ $seasonMetadataItemId ] );
		$episodeRows = $stmt->fetchAll( \PDO::FETCH_ASSOC );
		if( empty( $episodeRows ) ) {
			return $summary;
		}
		$summary['matched'] = true;

		self::deleteXrefByItem( $this->mContentId, [ 'episode' ] );

		$tagTypes = [ 'director' => 4, 'writer' => 5, 'star' => 6 ];
		foreach( $episodeRows as $row ) {
			if( !str_starts_with( $row['file'], $realRoot ) || !is_file( $row['file'] ) ) {
				continue;
			}
			$relativePath = substr( $row['file'], strlen( $realRoot ) );

			$episodeData = [
				'title'    => $row['title'],
				'summary'  => $row['summary'],
				'air_date' => !empty( $row['originally_available_at'] ) ? gmdate( 'Y-m-d', (int)$row['originally_available_at'] ) : null,
			];
			foreach( $tagTypes as $tagItem => $tagType ) {
				$tagStmt = $plexDb->prepare(
					"SELECT t.tag FROM taggings tg JOIN tags t ON t.id = tg.tag_id WHERE tg.metadata_item_id = ? AND t.tag_type = ? ORDER BY tg.\"index\""
				);
				$tagStmt->execute( [ $row['id'], $tagType ] );
				$values = $tagStmt->fetchAll( \PDO::FETCH_COLUMN );
				if( $tagItem === 'star' ) {
					$values = array_slice( $values, 0, 5 );
				}
				if( $values ) {
					$episodeData[$tagItem] = $values;
				}
			}
			if( !empty( $row['content_rating'] ) ) {
				$episodeData['content_rating'] = preg_replace( '#^[a-z]{2}/#i', '', $row['content_rating'] );
			}
			if( !empty( $row['duration'] ) ) {
				$episodeData['duration'] = (int)$row['duration'];
			}

			// this episode's own Plex-generated screenshot ("thumb") - a real per-episode still,
			// distinct from the season-level poster/backdrop alternates reloadPlexImages() fetches.
			// Confirmed live 2026-09-02: each episode's own metadata carries a `thumb="..."`
			// attribute (a lowercase-only match so it can't collide with `parentThumb`/
			// `grandparentThumb` on the same element). Stored in the same shared images/ folder as
			// the season's other alternates, named by episode index rather than xorder/basename
			// since a season has no single file of its own to derive a name from.
			if( !empty( $plexToken ) ) {
				$episodeXml = @file_get_contents( "http://localhost:32400/library/metadata/{$row['id']}?X-Plex-Token=".urlencode( $plexToken ) );
				if( $episodeXml !== false && preg_match( '#\bthumb="([^"]+)"#', $episodeXml, $m ) ) {
					$thumbPath = html_entity_decode( $m[1] );
					$thumbUrl = "http://localhost:32400$thumbPath?X-Plex-Token=".urlencode( $plexToken );
					$imageData = @file_get_contents( $thumbUrl );
					if( !empty( $imageData ) ) {
						$tmpFile = tempnam( sys_get_temp_dir(), 'fisheye_ep_thumb_' );
						file_put_contents( $tmpFile, $imageData );
						$fileName = $this->getTitle().' - episode-'.(int)$row['index'].'.jpg';
						if( self::resizeImageFile( $tmpFile, $imagesDir.$fileName, 400 ) ) {
							$episodeData['thumb'] = 'images/'.$fileName;
						}
						@unlink( $tmpFile );
					}
				}
			}

			$xrefParamHash = [
				'content_id' => $this->mContentId,
				'item'       => 'episode',
				'xkey_ext'   => $relativePath,
				'edit'       => json_encode( $episodeData ),
				'xorder'     => (int)$row['index'],
			];
			$this->storeXref( $xrefParamHash );
			$summary['items'][] = "S{$row['index']}: {$row['title']}";
		}

		return $summary;
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

		// Auto-pick the real thumbnail attachment (once only - a later manual promotion via
		// promoteImageToThumbnail() shouldn't be silently overwritten by a later reload) from
		// Plex's own currently-selected poster ('selected="1"' in the /posters listing) rather
		// than just grabbing whichever alternate happens to come first - that "selected" one is
		// Plex's (and, upstream, TheTVDB's) own curated choice, not just one candidate among
		// many. Its 'key' is often an internal 'metadata://...' reference with no directly
		// fetchable URL of its own, but the same <Photo> element's 'thumb' attribute always is -
		// either a Plex.tv proxy URL (external providers) or a local '/library/metadata/.../file?
		// url=...' path (Plex's own cached copy), both fetchable through Plex's local API once
		// resolved. Found live 2026-09-02: without this, only the undifferentiated alternates
		// list was ever fetched, never Plex's own actual pick.
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
