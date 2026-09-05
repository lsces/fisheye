<?php
/**
 * Music album — extends FisheyeImage with content_type_guid='fisheyealbum'.
 *
 * The real, folder-leaf content_id in the artist->album->track tree (an "artist"/"composer"
 * itself has no content_id at all - it's a computed, FoodDay-pattern browsing level over its
 * albums, not yet built - see fisheye.md's 2026-09-02 entry). Ring-fences album-level metadata
 * (artist/composer, MusicBrainz+Discogs links) plus the TRACK xref item away from plain
 * fisheyeimage photo rows and from FisheyeFilm/FisheyeSeason's own item sets - no other
 * behavioural difference from FisheyeImage, same pattern as Contact/ContactPerson/ContactBusiness.
 *
 * Track (mirrors Season's own Episode) is a raw 'track' liberty_xref row - xkey_ext is the file's
 * own path relative to fisheye_disk_storage_root, not a real LibertyMime attachment (there's no
 * per-track file management need beyond streaming it, same reasoning as episodes). The album's
 * own cover image DOES use a real thumbnail attachment though (attachThumbnail() below, same
 * private method FisheyeSeason/FisheyeProgram each already carry their own copy of) - unlike a
 * season, an album commonly already has a real cover.jpg/folder.jpg sitting in its own folder, so
 * this reads directly off disk rather than needing a Plex-fetch round trip first.
 *
 * Embedded per-track tags (real ffprobe format_tags - TITLE/ARTIST/ALBUM/track/disc, and for a
 * well-tagged classical release, full MUSICBRAINZ_* IDs) are the primary metadata source, not
 * Plex - confirmed live 2026-09-05 against a Classic Composers release that Plex's own matching
 * had mishandled (composer/performer attribution) despite the files themselves being properly
 * tagged via MusicBrainz already. Plex is a supplementary cross-reference only here, not the
 * source of truth the Film/TV side treats it as.
 *
 * @package fisheye
 */
namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

// mime_film_get_storage_root() below is only auto-loaded via the LibertyMime attachment-plugin
// dispatch, which never fires for an album (no LibertyMime attachment of its own - see this
// class's own docblock). Same fix as FisheyeProgram.php/FisheyeSeason.php/FisheyeFilm.php.
require_once dirname( __DIR__, 3 ).'/liberty/plugins/mime.film.php';

define( 'FISHEYEALBUM_CONTENT_TYPE_GUID', 'fisheyealbum' );

const FISHEYEALBUM_TRACK_EXTENSIONS = [ 'mp3', 'flac', 'm4a', 'ogg', 'wav' ];
const FISHEYEALBUM_COVER_NAMES = [ 'cover.jpg', 'folder.jpg', 'front.jpg', 'cover.png', 'folder.png' ];

class FisheyeAlbum extends FisheyeImage {

	public function __construct( $pImageId = null, $pContentId = null ) {
		parent::__construct( $pImageId, $pContentId );
		$this->mContentTypeGuid = FISHEYEALBUM_CONTENT_TYPE_GUID;
		$this->registerContentType( FISHEYEALBUM_CONTENT_TYPE_GUID, [
			'content_type_guid' => FISHEYEALBUM_CONTENT_TYPE_GUID,
			'content_name'      => 'Music Album',
			'handler_class'     => 'FisheyeAlbum',
			'handler_package'   => 'fisheye',
			'handler_file'      => 'FisheyeAlbum.php',
			'maintainer_url'    => 'https://www.bitweaver.org',
		] );
		// mPackageGuid='fisheye' is set automatically by registerContentType()
		// because handler_package('fisheye') != content_type_guid('fisheyealbum').
	}

	/**
	 * The storage root this album's own 'track' xref rows (xkey_ext) live relative to -
	 * play_track.php calls this generically via method_exists(), same convention FisheyeSeason's
	 * own version already established. Unlike a season (A-M/N-Z per-show split), an album's
	 * tracks all live under the one plain fisheye_disk_storage_root - no per-title resolution
	 * needed, same simple case Films themselves would use if they needed this method at all.
	 *
	 * @return string empty string if the config is unset
	 */
	public function getImageStorageRoot(): string {
		return \Bitweaver\Liberty\mime_film_get_storage_root();
	}

	/**
	 * FisheyeImage's own generic getDisplayUrl() only ever routes to view_film.php or
	 * view_image.php (branches on attachment_plugin_guid==='mimefilm') - an album has no
	 * attachment plugin of its own at all, so it would silently fall through to view_image.php,
	 * the wrong page. Same fix FisheyeSeason/FisheyeProgram each already needed for themselves.
	 *
	 * @return string
	 */
	public function getDisplayUrl( $pContentId = null, $pMixed = null ) {
		$contentId = \Bitweaver\BitBase::verifyId( $pContentId ) ? $pContentId : $this->mContentId;
		return FISHEYE_PKG_URL.'view_album.php?content_id='.$contentId;
	}

	/**
	 * Override LibertyContent::getEditUrl()'s generic '<package>/edit.php' default - same fatal-
	 * error bug FisheyeFilm/Season/Program each already hit ("Call to undefined method
	 * ...::getAllLayouts()"), fisheye's own edit.php being the GALLERY edit page, not an album's.
	 * See FisheyeSeason::getEditUrl()'s identical override.
	 *
	 * @param int|null $pContentId
	 * @param array|null $pMixed
	 * @return string
	 */
	public function getEditUrl( $pContentId = null, $pMixed = null ) {
		$contentId = \Bitweaver\BitBase::verifyId( $pContentId ) ? $pContentId : $this->mContentId;
		$ret = FISHEYE_PKG_URL.'edit_album.php?content_id='.$contentId;
		foreach( (array)$pMixed as $key => $value ) {
			if( $key !== 'content_id' ) {
				$ret .= '&'.$key.'='.$value;
			}
		}
		return $ret;
	}

	/**
	 * Override of FisheyeBase's own getImageStorageRoot()-relative default - an album's own
	 * downloaded Plex alternates live in storage/attachments/<branch>/, not the external music
	 * library tree, same fix FisheyeFilm got 2026-09-04 (see getImageStorageBranchPath()'s own
	 * docblock) and Album now needs too, since it copied Season's now-outdated shared images/
	 * folder approach when it was first built.
	 */
	public function getExtraImagePath( string $pRelativePath ): string {
		return $this->getImageStorageBranchPath().$pRelativePath;
	}

	/**
	 * This album's own storage/attachments/<branch>/ path - home for its downloaded Plex image
	 * alternates and any manual uploads, same convention FisheyeFilm::getImageStorageBranchPath()
	 * already established ("storage/attachments/<branch>/ has always been used as home for
	 * extras like the plex images and any manual uploads" - Lester, 2026-09-04). Always
	 * nginx-writable by construction, unlike the external music library tree.
	 *
	 * @return string
	 */
	private function getImageStorageBranchPath(): string {
		return STORAGE_PKG_PATH.\Bitweaver\Liberty\liberty_mime_get_storage_branch( [ 'attachment_id' => $this->mContentId ] );
	}

	/**
	 * Generic file-lifecycle hook liberty/edit_xref.php calls (via method_exists()) when a file
	 * is uploaded to replace an xref row's own referenced file - see FisheyeFilm::
	 * replaceXrefFile()'s identical docblock for the fuller reasoning, same method, same shape.
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
		return move_uploaded_file( $pTmpPath, $this->getImageStorageBranchPath().$pXkeyExt );
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
		$path = $this->getImageStorageBranchPath().$pXkeyExt;
		if( !is_file( $path ) ) {
			return false;
		}
		return @unlink( $path );
	}

	/**
	 * Promote one of this album's already-downloaded 'image' xref alternates into its actual
	 * displayed thumbnail - same shape as FisheyeFilm::promoteImageToThumbnail() (regenerates
	 * thumbs/ directly from the chosen alternate, already sitting in the same branch as the
	 * thumbs themselves, rather than a full re-store via attachThumbnail()).
	 *
	 * @param string $pRelativePath  an 'image' xref row's own xkey_ext value (a bare filename)
	 * @return bool
	 */
	public function promoteImageToThumbnail( string $pRelativePath ): bool {
		$branchPath = $this->getImageStorageBranchPath();
		$sourcePath = $branchPath.$pRelativePath;
		if( !is_file( $sourcePath ) ) {
			return false;
		}
		foreach( glob( $branchPath.'thumbs/*' ) ?: [] as $oldThumb ) {
			@unlink( $oldThumb );
		}
		$fileHash = [ 'type' => 'image/jpeg', 'source_file' => $sourcePath, 'dest_branch' => \Bitweaver\Liberty\liberty_mime_get_storage_branch( [ 'attachment_id' => $this->mContentId ] ) ];
		$ok = \Bitweaver\Liberty\liberty_generate_thumbnails( $fileHash );
		$this->load();
		return $ok;
	}

	/**
	 * Locate this album in the local Plex library, matched via one of its own 'track' xref rows'
	 * file path (same approach as FisheyeSeason::matchPlexSeasonMetadataItem() matching via an
	 * 'episode' xref) - Plex's music schema: track=metadata_type 10, its parent_id is the album
	 * (metadata_type 9), whose own parent_id is the artist (metadata_type 8). Only the album level
	 * is needed here.
	 *
	 * @return array{db:\PDO,id:int,root:string}|null
	 */
	private function matchPlexAlbumMetadataItem(): ?array {
		global $gBitSystem;

		$dbPath = $gBitSystem->getConfig( 'fisheye_plex_db_path', '' );
		if( empty( $dbPath ) || !is_file( $dbPath ) ) {
			return null;
		}

		$this->loadXrefInfo();
		$trackXref = $this->mXrefInfo ? $this->mXrefInfo->findRowByItem( 'track' ) : null;
		if( !$trackXref || empty( $trackXref['xkey_ext'] ) ) {
			return null;
		}

		$root = $this->getImageStorageRoot();
		if( empty( $root ) ) {
			return null;
		}

		$realPath = realpath( $root.$trackXref['xkey_ext'] );
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
			 WHERE mp.file = ? AND mi.metadata_type = 10"
		);
		$stmt->execute( [ $realPath ] );
		$albumMetadataItemId = $stmt->fetchColumn();
		if( !$albumMetadataItemId ) {
			return null;
		}

		return [ 'db' => $plexDb, 'id' => (int)$albumMetadataItemId, 'root' => $root ];
	}

	/**
	 * Fetch an alternate cover image from Plex for this album - simpler than FisheyeSeason's own
	 * reloadPlexImages() (no separate 'art'/backdrop type for a music album, just one poster per
	 * fetch, no per-type 5-cap loop needed), same 'selected' pick + xref-based storage shape
	 * otherwise. See that method's own docblock for the fuller reasoning not repeated here.
	 *
	 * @return array Summary of what was found/stored, for the calling page's result display.
	 */
	public function reloadPlexImages(): array {
		global $gBitSystem;
		$summary = [ 'matched' => false, 'items' => [] ];

		$plexMatch = $this->matchPlexAlbumMetadataItem();
		if( !$plexMatch ) {
			return $summary;
		}
		$summary['matched'] = true;
		$metadataItemId = $plexMatch['id'];
		$root = $plexMatch['root'];

		$plexToken = $gBitSystem->getConfig( 'fisheye_plex_token', '' );
		if( empty( $plexToken ) ) {
			$summary['items'][] = 'fisheye_plex_token is not configured - the posters endpoint needs it.';
			return $summary;
		}

		// Auto-pick the real thumbnail attachment (once only) from Plex's own currently-selected
		// cover ('selected="1"' in the /posters listing) - see FisheyeSeason::reloadPlexImages()'s
		// identical comment for the fuller reasoning.
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
							$summary['items'][] = 'thumbnail: attached from Plex\'s own selected cover';
						}
						break;
					}
				}
			}
		}

		$existingImagePaths = [];
		$xorder = 0;
		foreach( $this->mXrefInfo->allXrefs() as $xref ) {
			if( $xref['item'] === 'image' ) {
				$existingImagePaths[] = $xref['xkey_ext'];
				$xorder = max( $xorder, (int)$xref['xorder'] );
			}
		}
		if( $existingImagePaths ) {
			$summary['items'][] = 'already has stored images - not re-fetched (delete them first to force a re-fetch).';
			return $summary;
		}

		// Lives in this album's own storage/attachments/<branch>/ - same fix FisheyeFilm got
		// 2026-09-04, not the external music library tree ($root, still used above only to
		// resolve the Plex match via a track's own file path).
		$destBranch = \Bitweaver\Liberty\liberty_mime_get_storage_branch( [ 'attachment_id' => $this->mContentId ] );
		$imagesDir = STORAGE_PKG_PATH.$destBranch;
		KernelTools::mkdir_p( $imagesDir );
		$baseName = $this->getTitle();

		$apiUrl = "http://localhost:32400/library/metadata/$metadataItemId/posters?X-Plex-Token=".urlencode( $plexToken );
		$xml = @file_get_contents( $apiUrl );
		if( $xml === false || !preg_match_all( '#<Photo[^>]*\bkey="(https://[^"]+)"#', $xml, $matches ) ) {
			return $summary;
		}
		$fetched = 0;
		foreach( $matches[1] as $imageUrl ) {
			if( $fetched >= 5 ) {
				break;
			}
			$imageUrl = str_replace( '/original/', '/w342/', html_entity_decode( $imageUrl ) );
			$imageData = @file_get_contents( $imageUrl );
			if( $imageData === false ) {
				continue;
			}
			$fetched++;
			$fileName = "$baseName-poster-$fetched.jpg";
			// xkey_ext is just the bare filename, resolved against this album's own
			// storage/attachments/<branch>/ (see getImageStorageBranchPath()) - no directory
			// component needed, the branch is already per-content_id.
			$tmpFile = tempnam( sys_get_temp_dir(), 'fisheye_alt_' );
			file_put_contents( $tmpFile, $imageData );
			$resized = self::resizeImageFile( $tmpFile, $imagesDir.$fileName, 400 );
			@unlink( $tmpFile );
			if( !$resized ) {
				continue;
			}
			$xrefHash = [ 'content_id' => $this->mContentId, 'item' => 'image', 'xkey_ext' => $fileName, 'xorder' => ++$xorder ];
			$this->storeXref( $xrefHash );
			$summary['items'][] = "image: $fileName";
		}

		return $summary;
	}

	/**
	 * Every real track's embedded format_tags via a single ffprobe call - one process per file
	 * (an album is a few tracks to a couple of dozen, nowhere near mpeg2_tidy.php's whole-library
	 * scale, so the parallel xargs pattern that needs isn't worth the complexity here).
	 *
	 * @param string $pAbsolutePath
	 * @return array<string,string>  uppercased tag name => value, empty if ffprobe found none
	 */
	private static function readTrackTags( string $pAbsolutePath ): array {
		$cmd = 'ffprobe -v error -show_entries format_tags -of default=noprint_wrappers=1 '.escapeshellarg( $pAbsolutePath ).' 2>/dev/null';
		$output = shell_exec( $cmd ) ?? '';
		$tags = [];
		foreach( explode( "\n", $output ) as $line ) {
			if( !str_starts_with( $line, 'TAG:' ) ) {
				continue;
			}
			$parts = explode( '=', substr( $line, 4 ), 2 );
			if( count( $parts ) === 2 ) {
				$tags[strtoupper( $parts[0] )] = $parts[1];
			}
		}
		return $tags;
	}

	/**
	 * Register one album folder - every track file inside becomes a 'track' xref (disc/track
	 * number and title read from embedded tags when present, falling back to filename order and
	 * the bare filename otherwise), and a real cover.jpg/folder.jpg (FISHEYEALBUM_COVER_NAMES)
	 * sitting in the same folder gets attached as the thumbnail directly - no Plex round trip
	 * needed for the common case where the release already carries its own cover art.
	 *
	 * Idempotent the same way FisheyeSeason::registerFromDisk() is - re-running against an
	 * already-registered album just returns 'already' rather than creating a duplicate.
	 *
	 * @param string $pRelativeFolderPath  path relative to mime_film_get_storage_root(), e.g.
	 *                                     'Music Classical/Classic Composers/Vivaldi, Antonio
	 *                                     Lucio - VIVALDI Venetian Splendour (The Classic
	 *                                     Composers - Baroque 1)'
	 * @param string|null $pTitle          defaults to the folder's own basename
	 * @param string $pGalleryTitle        collection gallery to link this album into (created
	 *                                     separately, same convention as FisheyeFilm)
	 * @return array 'already'=>content_id, or 'created'=>content_id plus 'tracks'/'cover'
	 *               summary info, or 'error'=>string on failure
	 */
	public static function registerFromDisk( string $pRelativeFolderPath, ?string $pTitle = null, string $pGalleryTitle = 'Music' ): array {
		global $gBitDb;

		$root = \Bitweaver\Liberty\mime_film_get_storage_root();
		if( empty( $root ) ) {
			return [ 'error' => 'fisheye_disk_storage_root is not configured.' ];
		}
		$folderPath = rtrim( $pRelativeFolderPath, '/' ).'/';
		$absoluteFolder = $root.$folderPath;
		if( !is_dir( $absoluteFolder ) ) {
			return [ 'error' => 'Folder not found under the configured storage root: '.$folderPath ];
		}

		$title = trim( (string)$pTitle ) ?: basename( rtrim( $pRelativeFolderPath, '/' ) );

		$existingContentId = $gBitDb->getOne(
			"SELECT content_id FROM liberty_content WHERE content_type_guid = 'fisheyealbum' AND title = ?",
			[ $title ]
		);
		if( $existingContentId ) {
			return [ 'already' => $existingContentId ];
		}

		// Multi-disc sets (Black Sabbath-style CD1/CD2 subfolders) walked one level deep; a flat
		// album folder (Bob Marley/Classic Composers-style) has its track files directly inside -
		// both shapes scanned the same way, disc number just stays 1 for the flat case.
		$trackFiles = []; // [ 'relative' => path relative to $absoluteFolder, 'disc' => int ]
		foreach( scandir( $absoluteFolder ) as $entry ) {
			if( $entry === '.' || $entry === '..' ) {
				continue;
			}
			$entryPath = $absoluteFolder.$entry;
			if( is_dir( $entryPath ) ) {
				if( !preg_match( '/^CD\s*(\d+)/i', $entry, $discMatch ) ) {
					continue; // not a disc subfolder - e.g. artwork scans sitting alongside
				}
				foreach( scandir( $entryPath ) as $subEntry ) {
					$ext = strtolower( pathinfo( $subEntry, PATHINFO_EXTENSION ) );
					if( is_file( $entryPath.'/'.$subEntry ) && in_array( $ext, FISHEYEALBUM_TRACK_EXTENSIONS, true ) ) {
						$trackFiles[] = [ 'relative' => $entry.'/'.$subEntry, 'disc' => (int)$discMatch[1] ];
					}
				}
			} elseif( is_file( $entryPath ) ) {
				$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
				if( in_array( $ext, FISHEYEALBUM_TRACK_EXTENSIONS, true ) ) {
					$trackFiles[] = [ 'relative' => $entry, 'disc' => 1 ];
				}
			}
		}
		if( empty( $trackFiles ) ) {
			return [ 'error' => 'No track files found in '.$folderPath ];
		}

		// Read tags and sort by (disc, track-number-from-tag-or-filename) - embedded tags take
		// priority per Lester 2026-09-05 ("a lot of tracks DO have their own built in metadata
		// which perhaps should take priority"), filename order is only the fallback for untagged
		// files (e.g. the Bob Marley release found live with zero embedded tags at all).
		foreach( $trackFiles as &$track ) {
			$tags = self::readTrackTags( $absoluteFolder.$track['relative'] );
			$track['tags'] = $tags;
			$trackNum = $tags['TRACK'] ?? $tags['TRACKNUMBER'] ?? null;
			if( $trackNum !== null ) {
				$track['track_num'] = (int)explode( '/', $trackNum )[0];
			} else {
				// Fallback: leading "NN " / "NN - " / "NN." in the filename, same convention
				// mpeg2_tidy's own TV-episode naming already relies on.
				preg_match( '/^(\d+)/', basename( $track['relative'] ), $m );
				$track['track_num'] = isset( $m[1] ) ? (int)$m[1] : 0;
			}
			if( !empty( $tags['DISC'] ) ) {
				$track['disc'] = (int)explode( '/', $tags['DISC'] )[0];
			}
			$track['title'] = $tags['TITLE'] ?? pathinfo( $track['relative'], PATHINFO_FILENAME );
		}
		unset( $track );
		usort( $trackFiles, fn( $a, $b ) => [ $a['disc'], $a['track_num'] ] <=> [ $b['disc'], $b['track_num'] ] );

		$album = new FisheyeAlbum();
		$firstTags = $trackFiles[0]['tags'];
		$storeHash = [ 'title' => $title ];
		if( !$album->store( $storeHash ) ) {
			return [ 'error' => implode( '; ', $album->mErrors ) ];
		}
		$album->load();

		$galleryContentId = $gBitDb->getOne(
			"SELECT lc.content_id FROM liberty_content lc INNER JOIN fisheye_gallery fg ON fg.content_id = lc.content_id WHERE lc.content_type_guid = 'fisheyegallery' AND lc.title = ?",
			[ $pGalleryTitle ]
		);
		$linked = false;
		if( $galleryContentId ) {
			$gallery = new FisheyeGallery( null, $galleryContentId );
			$gallery->load();
			$linked = $gallery->addItem( $album->mContentId );
		}

		$xorder = 0;
		foreach( $trackFiles as $track ) {
			// 'tags' carries every embedded format_tag verbatim (uppercased name => value, as
			// ffprobe returns them) alongside the already-normalised title/disc - so a well-tagged
			// track's own MUSICBRAINZ_*/COMPOSER/PERFORMER/etc. fields are preserved even though
			// nothing reads them yet, rather than being read once by readTrackTags() and discarded.
			$xrefHash = [
				'content_id' => $album->mContentId,
				'item'       => 'track',
				'xkey_ext'   => $folderPath.$track['relative'],
				'edit'       => json_encode( [ 'title' => $track['title'], 'disc' => $track['disc'], 'tags' => $track['tags'] ] ),
				'xorder'     => ++$xorder,
			];
			$album->storeXref( $xrefHash );
		}

		if( !empty( $firstTags['ARTIST'] ) || !empty( $firstTags['ALBUM_ARTIST'] ) ) {
			$artist = $firstTags['ALBUM_ARTIST'] ?? $firstTags['ARTIST'];
			$artistXrefHash = [ 'content_id' => $album->mContentId, 'item' => 'artist', 'xkey_ext' => $artist ];
			$album->storeXref( $artistXrefHash );
		}

		$coverAttached = null;
		foreach( FISHEYEALBUM_COVER_NAMES as $coverName ) {
			if( is_file( $absoluteFolder.$coverName ) ) {
				if( $album->attachThumbnail( $absoluteFolder.$coverName ) ) {
					$coverAttached = $coverName;
				}
				break;
			}
		}

		return [
			'created' => $album->mContentId,
			'linked'  => $linked,
			'tracks'  => count( $trackFiles ),
			'cover'   => $coverAttached,
		];
	}

}
