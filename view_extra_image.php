<?php
/**
 * Streams one of a film/season's own alternate images (FisheyeFilm/FisheyeSeason::
 * reloadPlexImages()'s shared images/ folder, or a season's own per-episode Plex thumb) - a small
 * PHP-mediated server, same "no nginx location for that tree yet" situation mime_film_download()
 * is already in (see mime.film.php's own note on this).
 *
 * Takes xref_id only, never a raw path - the file actually served is always exactly what's
 * already stored server-side against that xref row, resolved after confirming the row is really
 * an 'image' or 'episode' item, so there is no path-traversal surface: nothing here ever builds a
 * filesystem path from user-supplied text. Storage root resolved via the owning content object's
 * own getImageStorageRoot() (not a hardcoded mime_film_get_storage_root() call) - a season's own
 * images/episode thumbs live under the TV-specific root, not the plain film one; the two only
 * coincide on desktop (both /media3/), a real bug this fixes (see fisheye.md's 2026-09-02
 * "content_id assumption"/"storage root" entries for the same category of mistake found earlier
 * in edit_xref.php).
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;
use Bitweaver\HttpStatusCodes;

require_once '../kernel/includes/setup_inc.php';
global $gBitSystem, $gBitDb;

$gBitSystem->verifyPackage( 'fisheye' );

$xrefId = (int)( $_REQUEST['xref_id'] ?? 0 );
$row = $xrefId ? $gBitDb->getRow(
	"SELECT content_id, item, xkey_ext, data FROM `".BIT_DB_PREFIX."liberty_xref` WHERE xref_id = ? AND item IN ('image','episode')",
	[ $xrefId ]
) : null;
if( !$row ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such image' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}

$gContent = FisheyeImage::lookup( [ 'content_id' => $row['content_id'] ] );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such image' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}
// same viewer permission as the film/season itself - an alternate poster is no more sensitive
// than the film's own primary artwork, but shouldn't bypass a private gallery's access control.
$gContent->verifyViewPermission();

// an 'episode' row's own xkey_ext is its real video file - the image to serve here is the
// per-episode Plex thumb path stashed in its JSON data blob instead (see FisheyeSeason::
// reloadPlexEpisodes()'s 'thumb' key).
if( $row['item'] === 'episode' ) {
	$episodeData = !empty( $row['data'] ) ? json_decode( $row['data'], true ) : [];
	$relativePath = $episodeData['thumb'] ?? null;
} else {
	$relativePath = $row['xkey_ext'];
}
if( empty( $relativePath ) ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such image' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}

$root = method_exists( $gContent, 'getImageStorageRoot' ) ? $gContent->getImageStorageRoot() : '';
$path = $root.$relativePath;
if( empty( $root ) || !is_file( $path ) ) {
	$gBitSystem->fatalError( KernelTools::tra( 'Image file not found' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}

header( 'Content-Type: '.$gBitSystem->verifyMimeType( $path ) );
header( 'Content-Length: '.filesize( $path ) );
header( 'Cache-Control: private, max-age=86400' );
while( ob_get_level() > 0 ) {
	ob_end_clean();
}
readfile( $path );
