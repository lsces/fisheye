<?php
/**
 * Streams one episode's own video file, for the "play button" on view_season.php's episode grid
 * (Lester, 2026-09-02). Same "no nginx location for that tree yet, no LibertyMime attachment
 * either" situation view_extra_image.php is already in - an 'episode' xref row's xkey_ext is a
 * raw filesystem path, not a liberty_files attachment, so there is no existing serving route.
 *
 * Takes xref_id only, never a raw path - same no-path-traversal-surface reasoning as
 * view_extra_image.php.
 *
 * Basic HTTP Range support (single range only, the common case for a browser's own <video>
 * seek bar) - without it, an HTML5 <video> element generally can't seek and some browsers won't
 * even start playback of a large file. Deliberately not a <video> tag on the calling template
 * though - this library is a mix of .mp4 (plays natively) and .mkv (no reliable native browser
 * support in Chrome/Firefox), so the play button links straight here and lets the browser/OS
 * decide whether to play inline or hand off to an external player, rather than silently failing
 * inside a <video> tag for every .mkv episode.
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
	"SELECT content_id, xkey_ext FROM `".BIT_DB_PREFIX."liberty_xref` WHERE xref_id = ? AND item = 'episode'",
	[ $xrefId ]
) : null;
if( !$row ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such episode' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}

$gContent = FisheyeImage::lookup( [ 'content_id' => $row['content_id'] ] );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such episode' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}
$gContent->verifyViewPermission();

$root = method_exists( $gContent, 'getImageStorageRoot' ) ? $gContent->getImageStorageRoot() : '';
$path = $root.$row['xkey_ext'];
if( empty( $root ) || !is_file( $path ) ) {
	$gBitSystem->fatalError( KernelTools::tra( 'Episode file not found' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}

$fileSize = filesize( $path );
$mimeType = $gBitSystem->verifyMimeType( $path );
$start = 0;
$end = $fileSize - 1;
$status = HttpStatusCodes::HTTP_OK;

header( 'Accept-Ranges: bytes' );
header( 'Content-Type: '.$mimeType );
header( 'Content-Disposition: inline; filename="'.basename( $path ).'"' );

if( !empty( $_SERVER['HTTP_RANGE'] ) && preg_match( '/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m ) ) {
	if( $m[1] === '' && $m[2] !== '' ) {
		// suffix range, e.g. 'bytes=-500' - last 500 bytes.
		$start = max( 0, $fileSize - (int)$m[2] );
	} else {
		if( $m[1] !== '' ) {
			$start = (int)$m[1];
		}
		if( $m[2] !== '' ) {
			$end = (int)$m[2];
		}
	}
	$end = min( $end, $fileSize - 1 );
	if( $start > $end ) {
		header( 'Content-Range: bytes */'.$fileSize );
		http_response_code( HttpStatusCodes::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE );
		exit;
	}
	$status = HttpStatusCodes::HTTP_PARTIAL_CONTENT;
	header( 'Content-Range: bytes '.$start.'-'.$end.'/'.$fileSize );
}

http_response_code( $status );
header( 'Content-Length: '.( $end - $start + 1 ) );

while( ob_get_level() > 0 ) {
	ob_end_clean();
}

$handle = fopen( $path, 'rb' );
fseek( $handle, $start );
$bytesRemaining = $end - $start + 1;
while( $bytesRemaining > 0 && !feof( $handle ) ) {
	$chunk = min( 8192, $bytesRemaining );
	echo fread( $handle, $chunk );
	$bytesRemaining -= $chunk;
	flush();
}
fclose( $handle );
