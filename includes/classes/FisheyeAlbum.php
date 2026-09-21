<?php
/**
 * Music album — extends FisheyeImage with content_type_guid='fisheyealbum'.
 *
 * The real, folder-leaf content_id in the artist->album->track tree (an "artist"/"composer"
 * itself has no content_id at all - it's a computed browsing level over its
 * albums, not a stored one, not yet built). Ring-fences album-level metadata
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
// A box set's own per-release subfolder naming - CDxx for a set of otherwise-anonymous discs
// (Stravinsky's "Works of Igor Stravinsky", CD01..CD22), or Volume/Vol. N when each one already
// has a real distinguishing name of its own (Pachelbel's "Joseph Payne - 10 CD" - despite the
// parent folder's own "10 CD" name, each "The Complete Organ Works, Volume N" is its own distinct
// MusicBrainz release with its own date, same as Beethoven's "Complete Beethoven Edition Vol. N"
// set turned out to be - not one shared box, confirmed by checking real tag data before assuming
// "Volume" always means this).
// Not anchored to the start - real folder names commonly lead with the release/performer name
// instead ("Alkan - Organ Works, Vol. 1 - Bowyer", "The Complete Organ Works, Volume 1"), not
// "CD01"-style bare disc numbering alone. Checked against real album titles that must NOT match
// ("CD Pool - Dance Hits...", "100 Hits Christmas") - the required digit immediately after CD/Vol
// keeps this from false-firing on ordinary prose.
const FISHEYEALBUM_DISC_FOLDER_PATTERN = '/(CD|Vol(ume)?\.?)\s*\d+/i';
// Neither album-level nor worth keeping per-track - dropped outright rather than promoted:
// ID3V2_PRIV.* are opaque binary loudness-normalization frames (MP3Gain/ReplayGain-adjacent), not
// human-readable metadata at all; TLEN (ID3v2 track length in ms) just duplicates the file's own
// real duration, already probed separately; SCRIPT is just the writing-system code (e.g. "Latn"),
// no display value; ALBUM duplicates the album object's own already-known title; TSO2/
// ALBUMARTISTSORT (raw ID3v2 frame and Vorbis spellings of the same "album artist sort order"
// concept) duplicate the value the 'artist' xref item already promotes - unlike ARTIST/ARTISTS/
// ARTISTSORT, which hold genuinely different per-credit information left as-is for now (see
// FISHEYEALBUM_COMMON_TAG_ALTERNATES's own 'artist' entry); TRACKTOTAL/TOTALTRACKS and DISCTOTAL/
// TOTALDISCS (again two spellings each for the same two concepts) are just counts already implicit
// in how many track xrefs actually got stored, not real metadata to display.
const FISHEYEALBUM_IGNORED_TAG_KEYS = [
	'ID3V2_PRIV_PEAKVALUE', 'ID3V2_PRIV_AVERAGELEVEL', 'TLEN', 'SCRIPT', 'ALBUM', 'TSO2', 'ALBUMARTISTSORT',
	'TRACKTOTAL', 'TOTALTRACKS', 'DISCTOTAL', 'TOTALDISCS',
];

// Embedded tag name -> xref item, for tags that only ever make sense at album/disc level, not
// per-track. Only promoted to a real xref if the value is identical across every track on the
// album (see extractCommonTags()) - one that genuinely varies per track (an artist id on a
// various-artists compilation, a disc id on a multi-disc set) stays out of these and in each
// track's own data instead.
const FISHEYEALBUM_COMMON_TAG_MAP = [
	'GENRE'                      => 'genre',
	'CATALOGNUMBER'              => 'catalog_number',
	'BARCODE'                    => 'barcode',
	'MUSICBRAINZ_ALBUMID'        => 'mbid',
	'MUSICBRAINZ_RELEASEGROUPID' => 'mb_releasegroupid',
	'MUSICBRAINZ_DISCID'         => 'mb_discid',
	'ASIN'                       => 'asin',
	'COMPILATION'                => 'compilation',
];
// A handful of xref items have more than one possible tag name (taggers disagree between Vorbis-
// style, ID3v2/MusicBrainz-Picard-style, and raw ID3v2 4-letter frame IDs for the exact same
// concept, or a fallback makes sense) - first match wins, same preference order FisheyeSeason/
// FisheyeFilm's own Plex metadata already uses elsewhere for "prefer the more specific field".
const FISHEYEALBUM_COMMON_TAG_ALTERNATES = [
	// TORY is the raw ID3v2.3 frame id for "original release year" - same concept as ORIGINALYEAR.
	'release_date'   => [ 'DATE', 'ORIGINALDATE', 'ORIGINALYEAR', 'TORY' ],
	'mb_artistid'    => [ 'MUSICBRAINZ_ALBUMARTISTID', 'MUSICBRAINZ_ARTISTID' ],
	// TMED/IMED are two different taggers' own raw frame ids for "media type" - same concept as MEDIA.
	'format'         => [ 'MEDIA', 'TMED', 'IMED' ],
	// PUBLISHER (ID3v2 TPUB) and LABEL (Vorbis) - same "record label" concept, different naming.
	'label'          => [ 'LABEL', 'PUBLISHER' ],
	'artist'         => [ 'ALBUM_ARTIST', 'ARTIST' ],
	// IMUS is another non-standard tagger's own frame for composer, same concept as COMPOSER.
	'composer'       => [ 'COMPOSER', 'IMUS' ],
	// ICNT is another non-standard tagger's own frame for country, same concept as RELEASECOUNTRY.
	'country'        => [ 'RELEASECOUNTRY', 'MUSICBRAINZ_ALBUM_RELEASE_COUNTRY', 'ICNT' ],
	'release_type'   => [ 'RELEASETYPE', 'MUSICBRAINZ_ALBUM_TYPE' ],
	'release_status' => [ 'RELEASESTATUS', 'MUSICBRAINZ_ALBUM_STATUS' ],
];

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
	 * The root this album's own 'track' xref rows (xkey_ext) live relative to - play_track.php
	 * calls this generically via method_exists(), same convention FisheyeSeason's own version
	 * already established. Unlike a season (A-M/N-Z per-show split, but still one fixed root for
	 * every season), this is genuinely per-album: this album's own real folder
	 * (Music/<gallery>/<title>/), not the bare fisheye_disk_storage_root - so a track's own
	 * xkey_ext only ever needs to store its bare filename (or "CDxx/filename" for an album that
	 * kept its own CD-subfolder layer without being split into a box set), not the whole nested
	 * path down to it. Found necessary live against a real box set - Firebird's xkey_ext column
	 * is capped at 250 characters, and a deeply-nested path (artist/box set/CDxx/long track title)
	 * already overflowed that on its own without this.
	 *
	 * A box set disc's own album sits one level deeper than a normal top-level album though
	 * (Music/<artist>/<box set>/<disc folder>/, not Music/<box set>/<disc folder>/) - walks up the
	 * real gallery chain, trying an extra level each time, until a candidate actually exists on
	 * disk (a box set is never nested more than one level deep, see
	 * FisheyeAlbum::isBoxSetFolder()'s own docblock, so this never needs to go further than that).
	 *
	 * @return string  the deepest-resolved guess if this album isn't linked into any gallery yet
	 *                 (folder can't be resolved) or nothing on disk actually matches, or the bare
	 *                 storage root if the config is unset
	 */
	public function getImageStorageRoot(): string {
		$root = \Bitweaver\Liberty\mime_film_get_storage_root();
		if( empty( $root ) ) {
			return $root;
		}
		$pathSegments = [ $this->getTitle() ];
		$contentId = $this->mContentId;
		for( $i = 0; $i < 3; $i++ ) {
			$candidate = $root.'Music/'.implode( '/', $pathSegments ).'/';
			if( is_dir( $candidate ) ) {
				return $candidate;
			}
			$parentGalleries = $this->getParentGalleries( $contentId );
			if( empty( $parentGalleries ) ) {
				break;
			}
			$contentId = key( $parentGalleries );
			array_unshift( $pathSegments, current( $parentGalleries )['title'] );
		}
		return $root.'Music/'.implode( '/', $pathSegments ).'/';
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
	 * already established. Always
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
		if( $xml === false || !preg_match_all( '#<Photo[^>]*\bkey="([^"]+)"#', $xml, $matches ) ) {
			return $summary;
		}
		$fetched = 0;
		foreach( $matches[1] as $imageUrl ) {
			if( $fetched >= 5 ) {
				break;
			}
			$imageUrl = html_entity_decode( $imageUrl );
			// Plex's own newer agents serve bundled art via a local proxy path rather than a
			// direct https:// URL - no remote size variant to swap in for these, fetch as-is
			// through the local API instead
			$imageUrl = str_starts_with( $imageUrl, '/' )
				? "http://localhost:32400$imageUrl".( str_contains( $imageUrl, '?' ) ? '&' : '?' )."X-Plex-Token=".urlencode( $plexToken )
				: str_replace( '/original/', '/w342/', $imageUrl );
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
	 * @return array<string,string>  normalized tag name => value, empty if ffprobe found none
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
				$tags[self::normalizeTagKey( $parts[0] )] = $parts[1];
			}
		}
		return $tags;
	}

	/**
	 * Same embedded tag, different spelling depending on container/tagger: Vorbis comments (FLAC/
	 * OGG) conventionally get written upper-case-with-underscores (MUSICBRAINZ_ALBUMID), while an
	 * ID3v2 TXXX frame (MP3) has no fixed enum for a MusicBrainz field - it's a free-text
	 * description, and Picard writes the human-readable form there ("MusicBrainz Album Id")
	 * instead. Both are the same tag - stripping every non-alphanumeric character before comparing
	 * collapses both spellings (and any stray case/spacing variant) to one key, rather than the
	 * ID3v2 form silently reading as "not tagged" against FISHEYEALBUM_COMMON_TAG_MAP's Vorbis-
	 * style constants.
	 */
	private static function normalizeTagKey( string $pKey ): string {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $pKey ) );
	}

	/**
	 * Split embedded tags into album-common (identical on every track - real MusicBrainz ids,
	 * label/catalog/barcode/country/etc, genre/composer/artist) vs track-specific (everything
	 * else, including a common-shaped tag that happens to vary per track this time - a various-
	 * artists compilation, or a disc id that only applies within one disc of a multi-disc set).
	 *
	 * @param array $pTrackFiles  registerFromDisk()'s own $trackFiles, each with a 'tags' entry
	 * @return array{0: array<string,string>, 1: array<string,true>}  [ xref item => value,
	 *         normalized tag name => true for every tag that got promoted to an xref OR is on
	 *         FISHEYEALBUM_IGNORED_TAG_KEYS (so the caller can strip exactly those out of each
	 *         track's own data) ]
	 */
	private static function extractCommonTags( array $pTrackFiles ): array {
		$common = [];
		$promotedTagKeys = [];
		foreach( FISHEYEALBUM_IGNORED_TAG_KEYS as $tagKey ) {
			$promotedTagKeys[self::normalizeTagKey( $tagKey )] = true;
		}

		foreach( FISHEYEALBUM_COMMON_TAG_MAP as $tagKey => $xrefItem ) {
			$value = self::commonTagValue( $pTrackFiles, $tagKey );
			if( $value !== null ) {
				$common[$xrefItem] = $value;
				// Keyed by the normalized form, same as $track['tags'] itself (readTrackTags()
				// normalizes on the way in) - the raw constant spelling here is Vorbis-style
				// ('MUSICBRAINZ_ALBUMID'), which never matches an ID3v2-tagged track's own
				// normalized key ('MUSICBRAINZALBUMID') in the array_diff_key() below otherwise,
				// silently leaving every promoted tag sitting in track data anyway.
				$promotedTagKeys[self::normalizeTagKey( $tagKey )] = true;
			}
		}
		foreach( FISHEYEALBUM_COMMON_TAG_ALTERNATES as $xrefItem => $tagKeys ) {
			$matched = false;
			foreach( $tagKeys as $tagKey ) {
				$value = self::commonTagValue( $pTrackFiles, $tagKey );
				if( $value !== null && !$matched ) {
					$common[$xrefItem] = $value;
					$matched = true;
				}
				// Every alternate gets marked as promoted once any one of them wins, not just the
				// winner - a tagger commonly writes more than one of these redundantly (DATE and
				// ORIGINALDATE with the same value, say), and a losing alternate still duplicates
				// exactly what the winner already promoted, so it belongs out of track data too.
				// commonTagValue() already only returns non-null for a value that's identical
				// across every track, so a genuinely track-varying alternate (a various-artists
				// compilation's own per-track MUSICBRAINZ_ARTISTID, say) correctly stays null here
				// and never gets marked - only true album-wide duplicates do.
				if( $value !== null ) {
					$promotedTagKeys[self::normalizeTagKey( $tagKey )] = true;
				}
			}
		}
		return [ $common, $promotedTagKeys ];
	}

	/**
	 * A single tag's value if present and identical across every track, null otherwise (missing
	 * from any track, or differing between tracks - either way, not safe to treat as album-wide).
	 *
	 * @param array $pTrackFiles
	 * @param string $pTagKey  embedded tag name (normalizeTagKey() applied here, so callers can
	 *                         pass FISHEYEALBUM_COMMON_TAG_MAP/_ALTERNATES' own Vorbis-style
	 *                         spelling regardless of how this particular file's tagger wrote it)
	 * @return string|null
	 */
	private static function commonTagValue( array $pTrackFiles, string $pTagKey ): ?string {
		$pTagKey = self::normalizeTagKey( $pTagKey );
		$value = null;
		foreach( $pTrackFiles as $track ) {
			$trackValue = $track['tags'][$pTagKey] ?? null;
			if( $trackValue === null || ( $value !== null && $trackValue !== $value ) ) {
				return null;
			}
			$value = $trackValue;
		}
		return $value;
	}

	/**
	 * Extract a track's own embedded cover art (FLAC METADATA_BLOCK_PICTURE, MP3 APIC, etc - the
	 * same "picture" stream ffprobe already reports as a video/mjpeg stream alongside the real
	 * audio one) into a temp file, for the common single-CD classical case where there's no
	 * standalone cover.jpg/folder.jpg sitting in the album folder at all.
	 *
	 * @param string $pAbsolutePath
	 * @return string|null  temp file path (caller's own to unlink), or null if there's no
	 *                       embedded picture / extraction failed
	 */
	private static function extractEmbeddedCoverArt( string $pAbsolutePath ): ?string {
		$tmpFile = tempnam( sys_get_temp_dir(), 'fisheye_embedded_cover_' );
		$cmd = 'ffmpeg -y -i '.escapeshellarg( $pAbsolutePath ).' -an -c:v copy -update 1 -f image2 '.escapeshellarg( $tmpFile ).' 2>/dev/null';
		shell_exec( $cmd );
		if( is_file( $tmpFile ) && filesize( $tmpFile ) > 0 ) {
			return $tmpFile;
		}
		@unlink( $tmpFile );
		return null;
	}

	/**
	 * Scan one album folder's track files, read each one's embedded tags, and sort into final
	 * (disc, track) order - shared by registerFromDisk() (a brand new album) and reloadTracks()
	 * (re-scanning an already-registered one, e.g. after re-tagging in Picard or a metadata-schema
	 * change like promoting a new common tag).
	 *
	 * @param string $pAbsoluteFolder
	 * @return array  each entry: 'relative' (path relative to $pAbsoluteFolder), 'disc', 'tags',
	 *                'track_num', 'title' - empty if no track files found
	 */
	private static function scanTrackFiles( string $pAbsoluteFolder ): array {
		// Multi-disc sets (Black Sabbath-style CD1/CD2 subfolders) walked one level deep; a flat
		// album folder (Bob Marley/Classic Composers-style) has its track files directly inside -
		// both shapes scanned the same way, disc number just stays 1 for the flat case.
		$trackFiles = []; // [ 'relative' => path relative to $pAbsoluteFolder, 'disc' => int ]
		foreach( scandir( $pAbsoluteFolder ) as $entry ) {
			if( $entry === '.' || $entry === '..' ) {
				continue;
			}
			$entryPath = $pAbsoluteFolder.$entry;
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
			return [];
		}

		// Read tags and sort by (disc, track-number-from-tag-or-filename) - embedded tags take
		// priority, since most tracks already carry their own real metadata; filename order is
		// only the fallback for untagged files (some releases have zero embedded tags at all).
		foreach( $trackFiles as &$track ) {
			$tags = self::readTrackTags( $pAbsoluteFolder.$track['relative'] );
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
			// From the file's own container, not the (culled, unreliable) embedded TLEN tag - same
			// source episodes/featurettes already use for their own duration.
			$track['duration_ms'] = \Bitweaver\Liberty\mime_film_get_duration_ms( $pAbsoluteFolder.$track['relative'] );
		}
		unset( $track );
		usort( $trackFiles, fn( $a, $b ) => [ $a['disc'], $a['track_num'] ] <=> [ $b['disc'], $b['track_num'] ] );

		return $trackFiles;
	}

	/**
	 * Whether a folder under an artist/composer's own directory is a real album worth offering -
	 * used by load_album.php's own candidate scan to skip a same-level folder that isn't one at
	 * all (an "Artwork"/"Videos"/scans-style extras folder sitting alongside real albums), rather
	 * than listing it and only finding out via a failed import ("No track files found in ..."). No
	 * name-based denylist - genuinely checking for real track files handles any such folder by
	 * whatever it happens to be called, not just the ones already seen.
	 *
	 * Deliberately NOT scanTrackFiles() - that reads embedded tags and probes real duration (an
	 * ffprobe spawn each) for every track it finds, fine for actually registering one album but
	 * far too expensive just to answer "does this folder have anything in it at all", run once per
	 * candidate on every load_album.php page view. A 22-disc box set with ~20 tracks each turned a
	 * page load into ~900 ffprobe spawns before this - same file-extension check, stopping at the
	 * first match instead of reading every file found.
	 *
	 * @param string $pAbsoluteFolder
	 * @return bool
	 */
	public static function folderHasTracks( string $pAbsoluteFolder ): bool {
		foreach( scandir( $pAbsoluteFolder ) ?: [] as $entry ) {
			if( $entry === '.' || $entry === '..' ) {
				continue;
			}
			$entryPath = $pAbsoluteFolder.$entry;
			if( is_dir( $entryPath ) ) {
				if( !preg_match( FISHEYEALBUM_DISC_FOLDER_PATTERN, $entry ) ) {
					continue;
				}
				foreach( scandir( $entryPath ) ?: [] as $subEntry ) {
					if( is_file( $entryPath.'/'.$subEntry )
						&& in_array( strtolower( pathinfo( $subEntry, PATHINFO_EXTENSION ) ), FISHEYEALBUM_TRACK_EXTENSIONS, true ) ) {
						return true;
					}
				}
			} elseif( is_file( $entryPath )
				&& in_array( strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ), FISHEYEALBUM_TRACK_EXTENSIONS, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a folder is really a box set of distinct recordings rather than one multi-disc
	 * release - a real CDxx/Discxx subfolder still sitting directly inside it. Deliberately not a
	 * count threshold (">1 disc") - every genuine single-work multi-disc release already got its
	 * CD1/CD2 layer flattened away by hand this same session (tracks carrying their own real DISC
	 * tag need no folder-level grouping at all), so any CDxx folder still surviving now means it
	 * was kept on purpose, however many there are.
	 *
	 * @param string $pAbsoluteFolder
	 * @return bool
	 */
	public static function isBoxSetFolder( string $pAbsoluteFolder ): bool {
		foreach( scandir( $pAbsoluteFolder ) ?: [] as $entry ) {
			if( preg_match( FISHEYEALBUM_DISC_FOLDER_PATTERN, $entry ) && is_dir( $pAbsoluteFolder.$entry ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * One disc's own title within a box set, distinct from the box's overall ALBUM tag (which
	 * FISHEYEALBUM_IGNORED_TAG_KEYS already drops as noise everywhere else, since it's normally
	 * identical to the album's own already-known title) - TSST (ID3v2) / DISCSUBTITLE (Vorbis,
	 * Picard's own equivalent naming) is where a disc's real content actually lives, confirmed
	 * live varying disc-to-disc on a real 22-disc release ("Ballets, Volume 1", "Concertos", ...)
	 * while ALBUM stayed fixed at the box's own title on every single track.
	 *
	 * @param string $pAbsoluteDiscFolder
	 * @return string|null
	 */
	public static function getDiscTitle( string $pAbsoluteDiscFolder ): ?string {
		foreach( scandir( $pAbsoluteDiscFolder ) ?: [] as $entry ) {
			$ext = strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) );
			if( in_array( $ext, FISHEYEALBUM_TRACK_EXTENSIONS, true ) ) {
				$tags = self::readTrackTags( $pAbsoluteDiscFolder.$entry );
				return $tags['TSST'] ?? $tags['DISCSUBTITLE'] ?? null;
			}
		}
		return null;
	}

	/**
	 * Create (or find) a box set's own nested gallery, linked into $pParentGalleryTitle (the
	 * artist/composer's own gallery - nesting a gallery inside another is a safe, already-
	 * anticipated case, see FisheyeGallery::addItem()'s own docblock). Deliberately cheap - no
	 * track scanning at all, same one-off "create the gallery first, cheap/instant" step
	 * load_music.php's own top-level version already establishes for an artist/composer gallery.
	 * Populating it with real per-disc albums is then just a normal load_album.php visit pointed
	 * at this new gallery - CDxx subfolders show up as ordinary candidates there, letting Lester
	 * pick a handful at a time rather than every disc importing (and every one of its few hundred
	 * tracks) in one single request.
	 *
	 * @param string $pRelativeFolderPath  the box set's own folder, relative to
	 *                                     mime_film_get_storage_root() - same shape
	 *                                     registerFromDisk() takes
	 * @param string $pParentGalleryTitle  the artist/composer gallery this box set's own nested
	 *                                     gallery gets linked into
	 * @return array 'gallery_id'=>the box set's own new/existing gallery, or 'error'=>string
	 */
	public static function createBoxSetGallery( string $pRelativeFolderPath, string $pParentGalleryTitle ): array {
		$root = \Bitweaver\Liberty\mime_film_get_storage_root();
		if( empty( $root ) ) {
			return [ 'error' => 'fisheye_disk_storage_root is not configured.' ];
		}
		if( !is_dir( $root.rtrim( $pRelativeFolderPath, '/' ).'/' ) ) {
			return [ 'error' => 'Folder not found under the configured storage root: '.$pRelativeFolderPath ];
		}
		$boxSetTitle = basename( rtrim( $pRelativeFolderPath, '/' ) );

		$result = FisheyeGallery::findOrCreateNestedGallery( $boxSetTitle, $pParentGalleryTitle );
		if( empty( $result['error'] ) && empty( $result['already'] ) ) {
			// Music-grid pagination only makes sense freshly created, not re-applied to a gallery
			// that might already have its own preference set some other way.
			$gallery = new FisheyeGallery( null, $result['gallery_id'] );
			$gallery->load();
			$gallery->storePreference( 'gallery_pagination', FISHEYE_PAGINATION_MUSIC_GRID );
		}
		return $result;
	}

	/**
	 * Re-scan an already-registered album's own folder and refresh its track/common-tag xrefs -
	 * for a re-tag in Picard after the fact, or a metadata-schema change here (a newly-promoted
	 * common tag, like this file's own compilation/release_status additions) that a plain edit
	 * page reload can't retroactively apply to already-registered albums. Cover art is untouched -
	 * only 'track' and whichever common-tag items are currently defined get cleared and re-stored,
	 * same distinction registerFromDisk() itself already draws between the two.
	 *
	 * Folder resolution mirrors load_album.php's own: this album's title is expected to match a
	 * real folder directly under its parent gallery's own folder under Music/ - same one-level
	 * layout load_music.php's candidate scan uses.
	 *
	 * @return array 'tracks'=>count, or 'error'=>string on failure
	 */
	public function reloadTracks(): array {
		if( empty( $this->getParentGalleries() ) ) {
			return [ 'error' => 'This album is not linked into a collection gallery - cannot resolve its folder.' ];
		}
		$absoluteFolder = $this->getImageStorageRoot();
		if( empty( $absoluteFolder ) || !is_dir( $absoluteFolder ) ) {
			return [ 'error' => 'Folder not found under the configured storage root: '.$absoluteFolder ];
		}

		$trackFiles = self::scanTrackFiles( $absoluteFolder );
		if( empty( $trackFiles ) ) {
			return [ 'error' => 'No track files found in '.$absoluteFolder ];
		}
		[ $commonTags, $promotedTagKeys ] = self::extractCommonTags( $trackFiles );

		// 'image' xrefs (cover art) are deliberately left alone - this is a track/tag refresh only.
		$clearableItems = array_unique( array_merge(
			[ 'track' ],
			array_values( FISHEYEALBUM_COMMON_TAG_MAP ),
			array_keys( FISHEYEALBUM_COMMON_TAG_ALTERNATES )
		) );
		\Bitweaver\Liberty\LibertyContent::deleteXrefByItem( $this->mContentId, $clearableItems );

		$xorder = 0;
		foreach( $trackFiles as $track ) {
			// See registerFromDisk()'s own identical block for why this is flattened rather than
			// nested under a 'tags' key, and why TITLE/DISC are excluded here.
			$trackTagsForData = array_diff_key( $track['tags'], $promotedTagKeys, [ 'TITLE' => true, 'DISC' => true ] );
			$xrefHash = [
				'content_id' => $this->mContentId,
				'item'       => 'track',
				// Bare (or "CDxx/filename" for an album keeping its own CD-subfolder layer) -
				// see getImageStorageRoot()'s own docblock for why the album's folder itself is
				// never baked into this.
				'xkey_ext'   => $track['relative'],
				'edit'       => json_encode( array_merge( [ 'title' => $track['title'], 'disc' => $track['disc'], 'duration' => $track['duration_ms'] ], $trackTagsForData ) ),
				'xorder'     => ++$xorder,
			];
			$this->storeXref( $xrefHash );
		}
		foreach( $commonTags as $xrefItem => $value ) {
			$commonXrefHash = [ 'content_id' => $this->mContentId, 'item' => $xrefItem, 'xkey_ext' => $value ];
			$this->storeXref( $commonXrefHash );
		}

		return [ 'tracks' => count( $trackFiles ) ];
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
	 * @param string|null $pTitle          defaults to the folder's own basename - deliberately
	 *                                     never anything else, even when a nicer display name is
	 *                                     known (a box set disc's own TSST-derived title, say):
	 *                                     getImageStorageRoot() resolves this album's real folder
	 *                                     from its title, so the two must always match exactly. A
	 *                                     nicer name belongs in $pDescription instead.
	 * @param string $pGalleryTitle        collection gallery to link this album into (created
	 *                                     separately, same convention as FisheyeFilm)
	 * @param string|null $pDescription    shown on the album's own view page (same content_store
	 *                                     'edit'/description field every other content type uses) -
	 *                                     for a box set disc's own real content (its TSST/
	 *                                     DISCSUBTITLE tag), which the bare "CD01"-style folder
	 *                                     name the title is stuck with never conveys on its own
	 * @return array 'already'=>content_id, or 'created'=>content_id plus 'tracks'/'cover'
	 *               summary info, or 'error'=>string on failure
	 */
	public static function registerFromDisk( string $pRelativeFolderPath, ?string $pTitle = null, string $pGalleryTitle = 'Music', ?string $pDescription = null ): array {
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

		$trackFiles = self::scanTrackFiles( $absoluteFolder );
		if( empty( $trackFiles ) ) {
			return [ 'error' => 'No track files found in '.$folderPath ];
		}

		$album = new FisheyeAlbum();
		[ $commonTags, $promotedTagKeys ] = self::extractCommonTags( $trackFiles );
		$storeHash = [ 'title' => $title ];
		if( $pDescription !== null && $pDescription !== '' ) {
			$storeHash['edit'] = $pDescription;
		}
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
			// Flattened alongside title/disc rather than nested under its own 'tags' key - the
			// generic json-list xref template (view_json-list_item.tpl) just dumps every top-level
			// key as its own row, so nesting only bought a Smarty "Array" render instead of a
			// usable one. TITLE/DISC themselves are excluded here since they're already surfaced
			// as the clean 'title'/'disc' fields - anything identical across every track (real
			// MusicBrainz ids, label/catalog/barcode/country/genre/composer/artist) has already
			// been promoted to a real xref on the album itself instead (see extractCommonTags()),
			// so it isn't duplicated into every single track's own data. A tag that happens to
			// vary per track this time (a various-artists compilation's own per-track ARTIST, a
			// disc id that only applies within one disc of a multi-disc set) stays here.
			$trackTagsForData = array_diff_key( $track['tags'], $promotedTagKeys, [ 'TITLE' => true, 'DISC' => true ] );
			$xrefHash = [
				'content_id' => $album->mContentId,
				'item'       => 'track',
				// Bare (or "CDxx/filename" for an album keeping its own CD-subfolder layer) -
				// see getImageStorageRoot()'s own docblock for why the album's folder itself is
				// never baked into this.
				'xkey_ext'   => $track['relative'],
				'edit'       => json_encode( array_merge( [ 'title' => $track['title'], 'disc' => $track['disc'], 'duration' => $track['duration_ms'] ], $trackTagsForData ) ),
				'xorder'     => ++$xorder,
			];
			$album->storeXref( $xrefHash );
		}

		foreach( $commonTags as $xrefItem => $value ) {
			$commonXrefHash = [ 'content_id' => $album->mContentId, 'item' => $xrefItem, 'xkey_ext' => $value ];
			$album->storeXref( $commonXrefHash );
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
		// No standalone cover file - a single-CD classical release commonly embeds its own cover
		// art directly in the track (FLAC/MP3 METADATA_BLOCK_PICTURE/APIC) instead, extractable
		// via ffmpeg the same way ffprobe already reads the rest of a track's own tags.
		if( !$coverAttached ) {
			$embeddedCover = self::extractEmbeddedCoverArt( $absoluteFolder.$trackFiles[0]['relative'] );
			if( $embeddedCover ) {
				if( $album->attachThumbnail( $embeddedCover ) ) {
					$coverAttached = 'embedded';
					// Also kept as a real 'image' xref alternate (same storage/attachments/<branch>/
					// home as a Plex-fetched alternate) - attachThumbnail() above already deleted its
					// own copy of the original once thumbs/ existed, so without this there would be no
					// way back to the embedded art if the primary thumbnail later gets changed to
					// something else (a Plex poster, a manual upload).
					$embeddedFileName = 'embedded-cover.jpg';
					copy( $embeddedCover, $album->getImageStorageBranchPath().$embeddedFileName );
					$embeddedXrefHash = [ 'content_id' => $album->mContentId, 'item' => 'image', 'xkey_ext' => $embeddedFileName, 'xorder' => 1 ];
					$album->storeXref( $embeddedXrefHash );
				}
				@unlink( $embeddedCover );
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
