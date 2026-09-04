<?php
/**
 * Film — extends FisheyeImage with content_type_guid='fisheyefilm'.
 *
 * Exists purely to ring-fence film-specific xref_group/item registrations (genre/director/
 * writer/star/content_rating/duration, IMDB link) away from plain fisheyeimage photo rows and
 * from FisheyeSeason/FisheyeAlbum's own item sets — no behavioural difference from FisheyeImage
 * otherwise, same pattern as Contact/ContactPerson/ContactBusiness. See liberty.md's 2026-09-01
 * entries for the wider film/TV/music design, and fisheye.md's 2026-09-02 entry for this split.
 *
 * @package fisheye
 */
namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

define( 'FISHEYEFILM_CONTENT_TYPE_GUID', 'fisheyefilm' );

class FisheyeFilm extends FisheyeImage {

	public function __construct( $pImageId = null, $pContentId = null ) {
		parent::__construct( $pImageId, $pContentId );
		$this->mContentTypeGuid = FISHEYEFILM_CONTENT_TYPE_GUID;
		$this->registerContentType( FISHEYEFILM_CONTENT_TYPE_GUID, [
			'content_type_guid' => FISHEYEFILM_CONTENT_TYPE_GUID,
			'content_name'      => 'Film',
			'handler_class'     => 'FisheyeFilm',
			'handler_package'   => 'fisheye',
			'handler_file'      => 'FisheyeFilm.php',
			'maintainer_url'    => 'https://www.bitweaver.org',
		] );
		// mPackageGuid='fisheye' is set automatically by registerContentType()
		// because handler_package('fisheye') != content_type_guid('fisheyefilm').
	}

	/**
	 * Override LibertyContent::getEditUrl()'s generic '<package>/edit.php' default - fisheye's
	 * own edit.php is the GALLERY edit page (FisheyeGallery::getAllLayouts() etc.), not a film's.
	 * Without this, liberty/edit_xref.php's post-save/post-delete redirect (which calls
	 * getEditUrl() on whatever content type the xref belongs to) sent a film back to the wrong
	 * page entirely - a fatal error hit live 2026-09-02 ("Call to undefined method
	 * FisheyeFilm::getAllLayouts()") the moment an image xref row was actually deleted.
	 *
	 * @param int|null $pContentId
	 * @param array|null $pMixed  extra query params to append (content_id itself is skipped)
	 * @return string
	 */
	public function getEditUrl( $pContentId = null, $pMixed = null ) {
		$contentId = \Bitweaver\BitBase::verifyId( $pContentId ) ? $pContentId : $this->mContentId;
		$ret = FISHEYE_PKG_URL.'edit_film.php?content_id='.$contentId;
		foreach( (array)$pMixed as $key => $value ) {
			if( $key !== 'content_id' ) {
				$ret .= '&'.$key.'='.$value;
			}
		}
		return $ret;
	}

	/**
	 * The storage root this film's own 'image' xref rows live relative to - the plain
	 * fisheye_disk_storage_root (no A-M/N-Z split, unlike TV). Exists so edit_xref.php can call
	 * this generically (via method_exists()) without needing to know 'image' means anything
	 * fisheye-specific, or which root function applies to which content type - see
	 * FisheyeSeason::getImageStorageRoot()'s docblock for the fuller reasoning (found live
	 * 2026-09-02 as a real bug: edit_xref.php previously hardcoded this exact function for every
	 * content type, silently wrong for Season/Program on any deployment where the TV root
	 * genuinely differs from the film root).
	 *
	 * @return string empty string if the config is unset
	 */
	public function getImageStorageRoot(): string {
		return \Bitweaver\Liberty\mime_film_get_storage_root();
	}

	/**
	 * Generic file-lifecycle hook liberty/edit_xref.php calls (via method_exists()) when a file
	 * is uploaded to replace an xref row's own referenced file - deliberately generic (item name
	 * + its xkey_ext + the uploaded tmp path) so the shared controller never needs to know this
	 * is fisheye-specific or what 'image' means; this class decides internally which items it
	 * actually applies to (only 'image' - "replace what's in this slot", not "point this row at
	 * a different file", so xkey_ext itself never changes).
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
	 * hard-delete (expunge=3) of an xref row - see replaceXrefFile()'s docblock for why this is
	 * generic rather than fisheye-specific in the controller. Only 'image' rows have a disposable
	 * local file worth cleaning up (a local copy of a Plex/TMDB download); every other item type
	 * (e.g. 'episode', whose xkey_ext is the real, precious video file) must never be touched here.
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
	 * Promote one of this film's already-downloaded 'image' xref alternates into its actual
	 * displayed thumbnail - unlike FisheyeSeason/FisheyeProgram (which have no attachment of
	 * their own and gained a brand new real attachment slot for this), a film's single
	 * attachment is already occupied by the video itself (mime.film.php), so there's no second
	 * slot to store a thumbnail in. Reuses mime_film_get_thumbnail_url()'s own existing sidecar
	 * convention instead: copies the chosen alternate to `<video basename>-poster.jpg` next to
	 * the source video (the same filename that function already checks FIRST, before falling
	 * back to an ffmpeg frame-grab), then clears the cached thumbs so the next load regenerates
	 * from it rather than keeping whatever was cached before.
	 *
	 * @param string $pRelativePath  an 'image' xref row's own xkey_ext value
	 * @return bool
	 */
	public function promoteImageToThumbnail( string $pRelativePath ): bool {
		$root = $this->getImageStorageRoot();
		if( empty( $root ) || !is_file( $root.$pRelativePath ) ) {
			return false;
		}
		$this->load();
		$sourceFile = $this->mStorage[$this->mContentId]['source_file'] ?? '';
		if( empty( $sourceFile ) ) {
			return false;
		}
		$sidecarPath = preg_replace( '/\.[^.\/]+$/', '', $sourceFile ).'-poster.jpg';
		if( !copy( $root.$pRelativePath, $sidecarPath ) ) {
			return false;
		}
		$destBranch = \Bitweaver\Liberty\liberty_mime_get_storage_branch( [ 'attachment_id' => $this->mContentId ] );
		foreach( glob( STORAGE_PKG_PATH.$destBranch.'thumbs/*' ) ?: [] as $oldThumb ) {
			@unlink( $oldThumb );
		}
		$this->load();
		return true;
	}

	/**
	 * Locate this film in the local Plex library, matched by its real absolute file path
	 * (Plex's own media_parts.file, not fisheye's root-relative convention — realpath() bridges
	 * the fisheye_disk_storage_root symlink, e.g. /media3/, back to what Plex actually stored,
	 * e.g. /home/media1/). Shared by reloadPlexMetadata() and reloadPlexImages() so the file-
	 * matching logic (and its 'no fisheye_plex_db_path configured'/'no match found' silent-skip
	 * behaviour) exists in exactly one place.
	 *
	 * @return array{db:\PDO,id:int}|null  null if unconfigured or no match found
	 */
	/**
	 * Register an already-on-disk film (no-copy attachment via mime.film.php), link it into a
	 * gallery, and backfill Plex metadata - the one real per-film registration sequence, shared
	 * by admin_import_film.php (single film) and load_film.php (bulk selection) so it exists in
	 * exactly one place. Caller is responsible for validating $pRelativePath is a real file under
	 * the configured storage root first - this only re-checks for an existing registration
	 * (idempotent against being called twice for the same file).
	 *
	 * $pGalleryTitle defaults to 'Films' (the flat top-level pool) - a collection is just another
	 * gallery whose own title matches the on-disk subfolder name a film was found in (see
	 * load_film.php's own folder-scoping doc), so passing that title here is all a caller needs
	 * to do to link into it instead. No matching gallery (a folder browsed before its collection
	 * gallery exists yet) degrades the same way 'Films' missing always has - $linked stays false,
	 * not an error.
	 *
	 * $pFetchImages additionally calls reloadPlexImages() per film - off by default. Deliberately
	 * opt-in, not folded into the metadata backfill above: downloading N posters/backdrops is the
	 * heavier of the two Plex operations, and a bulk caller (load_film.php) importing many films
	 * at once needs to be able to choose that cost explicitly rather than always paying it.
	 *
	 * @return array 'already'=>content_id if already registered, or 'created'/'linked'/'plex'
	 *               (/'images' if $pFetchImages) on success, or 'error'=>string on failure - same
	 *               shape either caller can render.
	 */
	public static function registerFromDisk( string $pRelativePath, ?string $pTitle = null, bool $pFetchImages = false, string $pGalleryTitle = 'Films' ): array {
		global $gBitDb;

		$title = trim( (string)$pTitle ) ?: pathinfo( $pRelativePath, PATHINFO_FILENAME );

		$existingContentId = $gBitDb->getOne(
			"SELECT la.content_id FROM liberty_attachments la INNER JOIN liberty_files lf ON lf.file_id = la.foreign_id WHERE la.attachment_plugin_guid = 'mimefilm' AND lf.file_name = ?",
			[ $pRelativePath ]
		);
		if( $existingContentId ) {
			return [ 'already' => $existingContentId ];
		}

		$film = new FisheyeFilm();
		$pParamHash = [
			'title' => $title,
			'mimeplugin' => [
				'mimefilm' => [ 'file_name' => $pRelativePath ],
			],
		];
		if( !$film->store( $pParamHash ) ) {
			return [ 'error' => implode( '; ', $film->mErrors ) ];
		}

		$galleryContentId = $gBitDb->getOne(
			"SELECT lc.content_id FROM liberty_content lc INNER JOIN fisheye_gallery fg ON fg.content_id = lc.content_id WHERE lc.content_type_guid = 'fisheyegallery' AND lc.title = ?",
			[ $pGalleryTitle ]
		);
		$linked = false;
		if( $galleryContentId ) {
			$gallery = new FisheyeGallery( null, $galleryContentId );
			$gallery->load();
			$linked = $gallery->addItem( $film->mContentId );
		}
		$plexMeta = $film->reloadPlexMetadata();
		$featurettes = $film->registerFeaturettesFromDisk( $pRelativePath );

		$ret = [ 'created' => $film->mContentId, 'linked' => $linked, 'plex' => $plexMeta, 'featurettes' => $featurettes ];
		if( $pFetchImages ) {
			$ret['images'] = $film->reloadPlexImages();
		}
		return $ret;
	}

	/**
	 * A film living in its own folder alongside a Featurettes/ subfolder (DVD-era bonus content -
	 * "Featurettes/ is no different to Season/", Lester, 2026-09-04: same shape as an episode
	 * living under a season, just one level shallower) gets each Featurettes file registered as a
	 * 'featurette' xref on this film's own content_id - not a separate FisheyeFilm, not a gallery
	 * of its own. Same rebuild-not-diff convention as every other xref-based reload* here.
	 *
	 * A bare single file directly under Films/ (no folder of its own) has nothing to check
	 * against - $pRelativePath's own dirname() is 'Films' itself in that case, whose sibling
	 * 'Featurettes' would only ever be the top-level Films/Featurettes/ that doesn't exist on
	 * this install, so this is a safe no-op for the common case.
	 *
	 * @param string $pRelativePath  this film's own attachment path, relative to
	 *                               getImageStorageRoot() (mime_film_get_storage_root())
	 * @return array  Summary of what was found/stored, for the calling page's result display.
	 */
	public function registerFeaturettesFromDisk( string $pRelativePath ): array {
		$summary = [ 'items' => [] ];
		$root = $this->getImageStorageRoot();
		if( empty( $root ) ) {
			return $summary;
		}
		$featurettesDir = $root.dirname( $pRelativePath ).'/Featurettes/';
		if( !is_dir( $featurettesDir ) ) {
			return $summary;
		}
		self::deleteXrefByItem( $this->mContentId, [ 'featurette' ] );
		$xorder = 0;
		$files = scandir( $featurettesDir );
		natsort( $files );
		foreach( $files as $file ) {
			if( !is_file( $featurettesDir.$file ) ) {
				continue;
			}
			if( !in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), [ 'mkv', 'mp4', 'm4v', 'avi' ], true ) ) {
				continue;
			}
			$xorder++;
			$xrefHash = [
				'content_id' => $this->mContentId,
				'item'       => 'featurette',
				'xkey_ext'   => dirname( $pRelativePath ).'/Featurettes/'.$file,
				'edit'       => json_encode( [ 'title' => pathinfo( $file, PATHINFO_FILENAME ) ] ),
				'xorder'     => $xorder,
			];
			$this->storeXref( $xrefHash );
			$summary['items'][] = $file;
		}
		return $summary;
	}

	private function matchPlexMetadataItem(): ?array {
		global $gBitSystem;

		$dbPath = $gBitSystem->getConfig( 'fisheye_plex_db_path', '' );
		if( empty( $dbPath ) || !is_file( $dbPath ) ) {
			return null;
		}

		// refresh mStorage - needed when called right after store() on a just-created film,
		// whose in-memory object hasn't necessarily loaded its attachment row yet.
		$this->load();
		$sourceFile = $this->mStorage[$this->mContentId]['source_file'] ?? null;
		$realPath = $sourceFile ? realpath( $sourceFile ) : null;
		if( empty( $realPath ) ) {
			return null;
		}

		try {
			$plexDb = new \PDO( 'sqlite:'.$dbPath );
		} catch( \Exception $e ) {
			return null;
		}

		$stmt = $plexDb->prepare(
			"SELECT mi.id FROM media_parts mp
			 JOIN media_items mi2 ON mi2.id = mp.media_item_id
			 JOIN metadata_items mi ON mi.id = mi2.metadata_item_id
			 WHERE mp.file = ? AND mi.metadata_type = 1"
		);
		$stmt->execute( [ $realPath ] );
		$metadataItemId = $stmt->fetchColumn();
		if( !$metadataItemId ) {
			return null;
		}

		return [ 'db' => $plexDb, 'id' => (int)$metadataItemId ];
	}

	/**
	 * Best-effort metadata backfill/refresh from the local Plex library. Moved here from
	 * admin_import_film.php's one-off helper function 2026-09-02 so edit_film.php's
	 * 'Reload Metadata' action (for a film imported before this backfill existed, or re-synced
	 * after a Plex library update) can call the exact same logic as first-import instead of
	 * duplicating it.
	 *
	 * Plex's own library db is world-readable (confirmed 2026-09-02, no permission workaround
	 * needed) so genre/director/writer/star/content_rating/duration are always available with no
	 * config beyond fisheye_plex_db_path; imdb/tmdb need fisheye_plex_token too (Plex's own
	 * Preferences.xml, where that lives, is NOT world-readable — has to be copied into
	 * kernel_config by hand once). All text values go into xkey_ext, not xkey (view_film.php
	 * reads xkey_ext — see fisheye.md's 2026-09-02 "wrong xref field" entry for why that
	 * distinction matters), duration is stored as Plex's own raw milliseconds. Silently does
	 * nothing if fisheye_plex_db_path isn't configured or the file has no Plex match — metadata
	 * entry always remains possible by hand either way via the generic xref table.
	 *
	 * Deliberately separate from reloadPlexImages() (Lester, 2026-09-02) - text metadata and
	 * image fetching are different weight/frequency operations (the former is near-instant,
	 * the latter downloads several image files), so they get their own action/button each
	 * rather than one doing both.
	 *
	 * A second run rebuilds from scratch rather than diffing - every item this method writes
	 * (genre/director/writer/star/content_rating/duration/imdb/tmdb) is deleted for this content_id
	 * via LibertyContent::deleteXrefByItem() *before* re-inserting, since storeXref() always
	 * inserts a fresh row when called without an xref_id (correct for the multiple=1 items, which
	 * have no natural single-row key to update in place) - without the upfront delete, a second
	 * "Reload Metadata" just appended duplicate rows on top of the first run's, found live
	 * 2026-09-02 (Lester: "metadata seems to have been duplicated, not refreshed"). Same
	 * rebuild-not-diff pattern deleteXrefByItem()'s own docblock already documents for health's
	 * RebuildHRDerived.php and food's FoodAssembly::clearItems().
	 *
	 * @return array Summary of what was found/stored, for the calling page's result display.
	 */
	public function reloadPlexMetadata(): array {
		global $gBitSystem;
		$summary = [ 'matched' => false, 'items' => [] ];

		$plexMatch = $this->matchPlexMetadataItem();
		if( !$plexMatch ) {
			return $summary;
		}
		$plexDb = $plexMatch['db'];
		$metadataItemId = $plexMatch['id'];

		$stmt = $plexDb->prepare( "SELECT content_rating, duration, summary FROM metadata_items WHERE id = ?" );
		$stmt->execute( [ $metadataItemId ] );
		$plexRow = $stmt->fetch( \PDO::FETCH_ASSOC );
		if( !$plexRow ) {
			return $summary;
		}
		$summary['matched'] = true;

		// the film's own description - same gap/fix as FisheyeProgram::reloadPlexMetadata() (see
		// its own docblock for the two silent-failure bugs found there: by-reference store() param,
		// and 'edit' not 'data' as the input key for LibertyContent::verify() to pick up). Unlike
		// FisheyeGallery::store(), FisheyeImage::store()'s own verifyImageData() has no equivalent
		// hard requirement for 'title' to be present, so it's safely omitted here (confirmed
		// against edit_film.php's own title-only $gContent->store() calls using this same path).
		if( !empty( $plexRow['summary'] ) ) {
			$descriptionStoreHash = [ 'content_id' => $this->mContentId, 'edit' => $plexRow['summary'] ];
			$this->store( $descriptionStoreHash );
			$summary['items'][] = 'description updated';
		}

		self::deleteXrefByItem(
			$this->mContentId,
			[ 'genre', 'director', 'writer', 'star', 'content_rating', 'duration', 'imdb', 'tmdb' ]
		);

		// tag_type: 1=genre, 4=director, 5=writer, 6=actor(star) - confirmed against real live
		// data 2026-09-02, not documented anywhere by Plex itself.
		$tagTypes = [ 'genre' => 1, 'director' => 4, 'writer' => 5, 'star' => 6 ];
		foreach( $tagTypes as $item => $tagType ) {
			$tagStmt = $plexDb->prepare(
				"SELECT t.tag FROM taggings tg JOIN tags t ON t.id = tg.tag_id WHERE tg.metadata_item_id = ? AND t.tag_type = ? ORDER BY tg.\"index\""
			);
			$tagStmt->execute( [ $metadataItemId, $tagType ] );
			$xorder = 1;
			foreach( $tagStmt->fetchAll( \PDO::FETCH_COLUMN ) as $value ) {
				// 'star' capped at 5 - a long cast list isn't useful on the film-facts summary
				// this feeds (view_film.tpl), and Plex often lists dozens for a well-known film.
				if( $item === 'star' && $xorder > 5 ) { break; }
				$xrefParamHash = [ 'content_id' => $this->mContentId, 'item' => $item, 'xkey_ext' => $value, 'xorder' => $xorder ];
				$this->storeXref( $xrefParamHash );
				$summary['items'][] = "$item: $value";
				$xorder++;
			}
		}

		if( !empty( $plexRow['content_rating'] ) ) {
			// Plex stores e.g. 'gb/12A' - the region prefix isn't useful for display.
			$rating = preg_replace( '#^[a-z]{2}/#i', '', $plexRow['content_rating'] );
			$ratingParamHash = [ 'content_id' => $this->mContentId, 'item' => 'content_rating', 'xkey_ext' => $rating ];
			$this->storeXref( $ratingParamHash );
			$summary['items'][] = "content_rating: $rating";
		}
		if( !empty( $plexRow['duration'] ) ) {
			$durationParamHash = [ 'content_id' => $this->mContentId, 'item' => 'duration', 'xkey_ext' => (string)(int)$plexRow['duration'] ];
			$this->storeXref( $durationParamHash );
			$summary['items'][] = "duration: {$plexRow['duration']}ms";
		}

		$plexToken = $gBitSystem->getConfig( 'fisheye_plex_token', '' );
		if( !empty( $plexToken ) ) {
			$apiUrl = "http://localhost:32400/library/metadata/$metadataItemId?X-Plex-Token=".urlencode( $plexToken );
			$xml = @file_get_contents( $apiUrl );
			if( $xml !== false && preg_match_all( '#<Guid id="(imdb|tmdb)://([^"]+)"#', $xml, $matches, PREG_SET_ORDER ) ) {
				foreach( $matches as $match ) {
					$linkParamHash = [ 'content_id' => $this->mContentId, 'item' => $match[1], 'xkey' => $match[2] ];
					$this->storeXref( $linkParamHash );
					$summary['items'][] = "{$match[1]}: {$match[2]}";
				}
			}
		}

		return $summary;
	}

	/**
	 * Fetch alternate poster/backdrop images from Plex's local API (posters/arts endpoints - see
	 * fisheye.md's 2026-09-02 "'images' xref group" entry for why this is xref-based rather than
	 * a second liberty_attachments row per image) and store real local copies, decoupling from
	 * Plex's continued availability. Deliberately its own action, separate from
	 * reloadPlexMetadata() (Lester, 2026-09-02) - downloading N image files is a heavier,
	 * slower operation than the near-instant text-metadata backfill, so it gets its own
	 * button/action rather than running unconditionally every time metadata is reloaded.
	 *
	 * Needs fisheye_plex_token (the posters/arts endpoints aren't in the world-readable db, only
	 * via Plex's authenticated local API). Idempotent **per type** (poster/art), not globally -
	 * a type is only re-fetched if every existing row of that type has been deleted first;
	 * a global "any image exists at all" check (the original 2026-09-02 shape) meant tidying
	 * down to just the kept images of one type by deleting a whole other type still blocked ever
	 * re-fetching that now-empty type without wiping everything else too, found live the same day
	 * against Casino Royale. A new fetch continues the existing xorder sequence rather than
	 * restarting at 1, so a top-up run doesn't collide with rows the other type still has.
	 *
	 * Storage: a shared `images/` folder directly under fisheye_disk_storage_root, alongside
	 * `Films/` itself (Films/ is currently flat, one file per film, not one directory per film -
	 * see media.php's xref_schemes comment) - files named `<film file's own basename>-poster-N.jpg`
	 * / `-art-N.jpg` to disambiguate between films sharing the one folder. xkey_ext holds the
	 * path relative to fisheye_disk_storage_root (e.g. 'images/Elf (2003)-poster-1.jpg'); xorder
	 * numbers posters first (1 = primary/poster), then backdrops continuing on.
	 *
	 * Capped at 5 of each type - same reasoning as reloadPlexMetadata()'s 5-star cap, a well-known
	 * film's poster/art set from Plex can run into dozens and most are near-duplicates.
	 *
	 * Fetches TMDB's own pre-resized w342 (poster)/w780 (art) sizes, not the 'original' full
	 * resolution (1-4MB apiece, wasted weight for what's only ever shown as a thumbnail on
	 * view_film.tpl - discovered live the first time this actually rendered, Lester 2026-09-02).
	 * w185/w300 (TMDB's next size down) turned out too small once seen rendered - bumped up a
	 * tier the same day. TMDB only offers a fixed size set (no arbitrary width) - w342 is its
	 * closest poster size to a ~400px target, w780 the closest backdrop size above it (nothing
	 * exists between w300 and w780 for backdrops).
	 *
	 * @return array Summary of what was found/stored, for the calling page's result display.
	 */
	public function reloadPlexImages(): array {
		global $gBitSystem;
		$summary = [ 'matched' => false, 'items' => [] ];

		$plexMatch = $this->matchPlexMetadataItem();
		if( !$plexMatch ) {
			return $summary;
		}
		$summary['matched'] = true;
		$metadataItemId = $plexMatch['id'];

		// Per-type, not global - checked below per poster/art rather than skipping the whole
		// method just because *some* image exists. A global check meant that deliberately
		// deleting one entire type as part of tidying (e.g. every poster, keeping the backdrops)
		// still blocked ever re-fetching that now-empty type without wiping everything else too -
		// found live 2026-09-02 against Casino Royale (content_id=4066). Also tracks the current
		// max xorder so a top-up run continues the sequence rather than restarting at 1 and
		// colliding with what's already there.
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

		$root = \Bitweaver\Liberty\mime_film_get_storage_root();
		if( empty( $root ) ) {
			$summary['items'][] = 'fisheye_disk_storage_root is not configured.';
			return $summary;
		}
		$imagesDir = $root.'images/';
		KernelTools::mkdir_p( $imagesDir );

		$sourceFile = $this->mStorage[$this->mContentId]['source_file'] ?? '';
		$baseName = pathinfo( $sourceFile, PATHINFO_FILENAME ) ?: $this->getTitle();

		// TMDB serves the same image at several pre-resized widths from a predictable URL (its
		// own image CDN, not a Plex-specific thing) - swapping the 'key' attribute's '/original/'
		// segment for one of these avoids downloading/storing full-resolution (1-4MB). w342/w780
		// are real TMDB poster/backdrop size names, not arbitrary numbers - see this method's own
		// docblock for why these particular ones. Still resized further via resizeImageFile()
		// below - TMDB's own presets are width-only, no bounding-box option, so a backdrop at
		// w780 landscape is still 780px wide until the local resize step also caps it.
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
