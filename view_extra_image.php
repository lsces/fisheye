<?php
/**
 * Streams one of a film's own alternate images (FisheyeFilm::reloadPlexImages()'s shared
 * fisheye_disk_storage_root/images/ folder) - a small PHP-mediated server, same "no nginx
 * location for that tree yet" situation mime_film_download() is already in (see mime.film.php's
 * own note on this).
 *
 * Takes xref_id only, never a raw path - the file actually served is always exactly what's
 * already stored as that xref row's own xkey_ext, resolved server-side after confirming the row
 * is really an 'image' item, so there is no path-traversal surface: nothing here ever builds a
 * filesystem path from user-supplied text.
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
	"SELECT content_id, xkey_ext FROM `".BIT_DB_PREFIX."liberty_xref` WHERE xref_id = ? AND item = 'image'",
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

$root = \Bitweaver\Liberty\mime_film_get_storage_root();
$path = $root.$row['xkey_ext'];
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
