<?php
/**
 * Admin page for registering an already-on-disk film via mime.film.php's no-copy attachment path
 * - the real, permanent replacement for hand-editing import_disk_test.php's $toRegister array and
 * running it via CLI. Runs as a genuine authenticated admin request (php-fpm as nginx), so
 * thumbnail generation under storage/attachments/ works correctly with no permission workarounds -
 * see fisheye.md's 2026-09-02 "through bitweaver, not hacks" entry for why that matters.
 *
 * @package fisheye
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

require_once '../../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitDb, $gLibertySystem;

$gBitSystem->verifyPermission( 'p_fisheye_admin' );

require_once dirname( __DIR__ ).'/../liberty/plugins/mime.film.php';
if( !$gLibertySystem->isPluginActive( 'mimefilm' ) ) {
	$gLibertySystem->setActivePlugin( 'mimefilm' );
}

$root = \Bitweaver\Liberty\mime_film_get_storage_root();
$result = null;

if( !empty( $_REQUEST['fImport'] ) ) {
	$relativePath = trim( $_REQUEST['relative_path'] ?? '' );
	$title = trim( $_REQUEST['title'] ?? '' );
	if( empty( $title ) ) {
		// Sensible default only - admin can always override before submitting.
		$title = pathinfo( $relativePath, PATHINFO_FILENAME );
	}

	if( empty( $root ) ) {
		$result = [ 'error' => KernelTools::tra( 'fisheye_disk_storage_root is not configured - set it on the General Settings tab first.' ) ];
	} elseif( empty( $relativePath ) || !is_file( $root.$relativePath ) ) {
		$result = [ 'error' => KernelTools::tra( 'File not found under the configured storage root: ' ).$relativePath ];
	} else {
		// Idempotent - check whether this exact file is already registered before creating a
		// duplicate (a real gap in the earlier one-off scripts, found the hard way 2026-09-02).
		$existingContentId = $gBitDb->getOne(
			"SELECT la.content_id FROM liberty_attachments la INNER JOIN liberty_files lf ON lf.file_id = la.foreign_id WHERE la.attachment_plugin_guid = 'mimefilm' AND lf.file_name = ?",
			[ $relativePath ]
		);
		if( $existingContentId ) {
			$result = [ 'already' => $existingContentId ];
		} else {
			$film = new FisheyeFilm();
			$pParamHash = [
				'title' => $title,
				'mimeplugin' => [
					'mimefilm' => [ 'file_name' => $relativePath ],
				],
			];
			if( $film->store( $pParamHash ) ) {
				$galleryContentId = $gBitDb->getOne(
					"SELECT lc.content_id FROM liberty_content lc INNER JOIN fisheye_gallery fg ON fg.content_id = lc.content_id WHERE lc.content_type_guid = 'fisheyegallery' AND lc.title = ?",
					[ 'Films' ]
				);
				$linked = false;
				if( $galleryContentId ) {
					$gallery = new FisheyeGallery( null, $galleryContentId );
					$gallery->load();
					$linked = $gallery->addItem( $film->mContentId );
				}
				$plexMeta = fisheye_import_film_plex_backfill( $film, $root.$relativePath );
				$result = [ 'created' => $film->mContentId, 'linked' => $linked, 'plex' => $plexMeta ];
			} else {
				$result = [ 'error' => implode( '; ', $film->mErrors ) ];
			}
		}
	}
}

$gBitSmarty->assign( 'storageRoot', $root );
$gBitSmarty->assign( 'result', $result );

$gBitSystem->display( 'bitpackage:fisheye/admin_import_film.tpl', KernelTools::tra( 'Import Film' ), [ 'display_mode' => 'admin' ] );

/**
 * Best-effort metadata backfill from the local Plex library, matched by the film's real absolute
 * file path (Plex's own media_parts.file, not fisheye's root-relative convention - realpath()
 * bridges the fisheye_disk_storage_root symlink, e.g. /media3/, back to what Plex actually
 * stored, e.g. /home/media1/). Plex's own library db is world-readable (confirmed 2026-09-02, no
 * permission workaround needed) so genre/director/writer/star/content_rating/duration are always
 * available with no config beyond fisheye_plex_db_path; imdb/tmdb need fisheye_plex_token too
 * (Plex's own Preferences.xml, where that lives, is NOT world-readable - has to be copied into
 * kernel_config by hand once). All text values go into xkey_ext, not xkey (view_film.php reads
 * xkey_ext - see fisheye.md's 2026-09-02 "wrong xref field" entry for why that distinction
 * matters), duration is stored as Plex's own raw milliseconds. Silently does nothing if
 * fisheye_plex_db_path isn't configured or the file has no Plex match - metadata entry always
 * remains possible by hand either way.
 *
 * @return array Summary of what was found, for the result template.
 */
function fisheye_import_film_plex_backfill( FisheyeFilm $pFilm, string $pAbsolutePath ): array {
	global $gBitSystem;
	$summary = [ 'matched' => false, 'items' => [] ];

	$dbPath = $gBitSystem->getConfig( 'fisheye_plex_db_path', '' );
	if( empty( $dbPath ) || !is_file( $dbPath ) ) {
		return $summary;
	}

	$realPath = realpath( $pAbsolutePath );
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

	// tag_type: 1=genre, 4=director, 5=writer, 6=actor(star) - confirmed against real live data
	// 2026-09-02, not documented anywhere by Plex itself.
	$tagTypes = [ 'genre' => 1, 'director' => 4, 'writer' => 5, 'star' => 6 ];
	foreach( $tagTypes as $item => $tagType ) {
		$tagStmt = $plexDb->prepare(
			"SELECT t.tag FROM taggings tg JOIN tags t ON t.id = tg.tag_id WHERE tg.metadata_item_id = ? AND t.tag_type = ? ORDER BY tg.\"index\""
		);
		$tagStmt->execute( [ $metadataItemId, $tagType ] );
		$xorder = 1;
		foreach( $tagStmt->fetchAll( \PDO::FETCH_COLUMN ) as $value ) {
			// 'star' capped at 5 - a long cast list isn't useful on the film-facts summary this
			// feeds (view_film.tpl), and Plex often lists dozens for a well-known film.
			if( $item === 'star' && $xorder > 5 ) { break; }
			$xrefParamHash = [ 'content_id' => $pFilm->mContentId, 'item' => $item, 'xkey_ext' => $value, 'xorder' => $xorder ];
			$pFilm->storeXref( $xrefParamHash );
			$summary['items'][] = "$item: $value";
			$xorder++;
		}
	}

	if( !empty( $plexRow['content_rating'] ) ) {
		// Plex stores e.g. 'gb/12A' - the region prefix isn't useful for display.
		$rating = preg_replace( '#^[a-z]{2}/#i', '', $plexRow['content_rating'] );
		$ratingParamHash = [ 'content_id' => $pFilm->mContentId, 'item' => 'content_rating', 'xkey_ext' => $rating ];
		$pFilm->storeXref( $ratingParamHash );
		$summary['items'][] = "content_rating: $rating";
	}
	if( !empty( $plexRow['duration'] ) ) {
		$durationParamHash = [ 'content_id' => $pFilm->mContentId, 'item' => 'duration', 'xkey_ext' => (string)(int)$plexRow['duration'] ];
		$pFilm->storeXref( $durationParamHash );
		$summary['items'][] = "duration: {$plexRow['duration']}ms";
	}

	$plexToken = $gBitSystem->getConfig( 'fisheye_plex_token', '' );
	if( !empty( $plexToken ) ) {
		$apiUrl = "http://localhost:32400/library/metadata/$metadataItemId?X-Plex-Token=".urlencode( $plexToken );
		$xml = @file_get_contents( $apiUrl );
		if( $xml !== false && preg_match_all( '#<Guid id="(imdb|tmdb)://([^"]+)"#', $xml, $matches, PREG_SET_ORDER ) ) {
			foreach( $matches as $match ) {
				$linkParamHash = [ 'content_id' => $pFilm->mContentId, 'item' => $match[1], 'xkey' => $match[2] ];
				$pFilm->storeXref( $linkParamHash );
				$summary['items'][] = "{$match[1]}: {$match[2]}";
			}
		}
	}

	return $summary;
}
