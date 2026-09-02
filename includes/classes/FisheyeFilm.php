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
	 * Best-effort metadata backfill/refresh from the local Plex library, matched by this film's
	 * real absolute file path (Plex's own media_parts.file, not fisheye's root-relative
	 * convention — realpath() bridges the fisheye_disk_storage_root symlink, e.g. /media3/, back
	 * to what Plex actually stored, e.g. /home/media1/). Moved here from admin_import_film.php's
	 * one-off helper function 2026-09-02 so edit_film.php's 'Reload Metadata' action (for a film
	 * imported before this backfill existed, or re-synced after a Plex library update) can call
	 * the exact same logic as first-import instead of duplicating it.
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
	 * @return array Summary of what was found/stored, for the calling page's result display.
	 */
	public function reloadPlexMetadata(): array {
		global $gBitSystem;
		$summary = [ 'matched' => false, 'items' => [] ];

		$dbPath = $gBitSystem->getConfig( 'fisheye_plex_db_path', '' );
		if( empty( $dbPath ) || !is_file( $dbPath ) ) {
			return $summary;
		}

		// refresh mStorage - needed when called right after store() on a just-created film,
		// whose in-memory object hasn't necessarily loaded its attachment row yet.
		$this->load();
		$sourceFile = $this->mStorage[$this->mContentId]['source_file'] ?? null;
		$realPath = $sourceFile ? realpath( $sourceFile ) : null;
		if( empty( $realPath ) ) {
			return $summary;
		}

		try {
			$plexDb = new \PDO( 'sqlite:'.$dbPath );
		} catch( \Exception $e ) {
			return $summary;
		}

		$stmt = $plexDb->prepare(
			"SELECT mi.id, mi.content_rating, mi.duration FROM media_parts mp
			 JOIN media_items mi2 ON mi2.id = mp.media_item_id
			 JOIN metadata_items mi ON mi.id = mi2.metadata_item_id
			 WHERE mp.file = ? AND mi.metadata_type = 1"
		);
		$stmt->execute( [ $realPath ] );
		$plexRow = $stmt->fetch( \PDO::FETCH_ASSOC );
		if( !$plexRow ) {
			return $summary;
		}
		$summary['matched'] = true;
		$metadataItemId = (int)$plexRow['id'];

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
}
