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
	 * Locate this film in the local Plex library, matched by its real absolute file path
	 * (Plex's own media_parts.file, not fisheye's root-relative convention — realpath() bridges
	 * the fisheye_disk_storage_root symlink, e.g. /media3/, back to what Plex actually stored,
	 * e.g. /home/media1/). Shared by reloadPlexMetadata() and reloadPlexImages() so the file-
	 * matching logic (and its 'no fisheye_plex_db_path configured'/'no match found' silent-skip
	 * behaviour) exists in exactly one place.
	 *
	 * @return array{db:\PDO,id:int}|null  null if unconfigured or no match found
	 */
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

		$stmt = $plexDb->prepare( "SELECT content_rating, duration FROM metadata_items WHERE id = ?" );
		$stmt->execute( [ $metadataItemId ] );
		$plexRow = $stmt->fetch( \PDO::FETCH_ASSOC );
		if( !$plexRow ) {
			return $summary;
		}
		$summary['matched'] = true;

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
	 * via Plex's authenticated local API). Idempotent by skipping entirely if this film already
	 * has any 'image' xref rows — re-running is for a film that's never been fetched, not a way
	 * to add more/refresh; expunge the existing rows first (and their files) to force a re-fetch.
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

		$this->loadXrefInfo();
		if( $this->mXrefInfo->findByItem( 'image' ) ) {
			$summary['items'][] = 'Already has stored images - not re-fetched (expunge the existing image xref rows first to force a re-fetch).';
			return $summary;
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
		// segment for one of these avoids downloading/storing full-resolution (1-4MB, largely
		// wasted on what's only ever shown as a thumbnail here) or doing any local resizing.
		// w342/w780 are real TMDB poster/backdrop size names, not arbitrary numbers - see this
		// method's own docblock for why these particular ones.
		$thumbSizes = [ 'poster' => 'w342', 'art' => 'w780' ];

		$xorder = 1;
		foreach( [ 'poster' => 'posters', 'art' => 'arts' ] as $type => $endpoint ) {
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
				$xrefParamHash = [ 'content_id' => $this->mContentId, 'item' => 'image', 'xkey_ext' => $relativePath, 'xorder' => $xorder ];
				$this->storeXref( $xrefParamHash );
				$summary['items'][] = "$type: $relativePath";
				$xorder++;
			}
		}

		return $summary;
	}
}
