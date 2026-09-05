<?php
/**
 * Streams one episode's (or film Featurette's - "Featurettes/ is no different to Season/",
 * Lester, 2026-09-04, same shape one level shallower: a bonus xref row living on its parent
 * content's own content_id) own video file, for the "play button" on view_season.php's episode
 * grid (Lester, 2026-09-02) and view_film.php's Featurettes section. Same "no nginx location for
 * that tree yet, no LibertyMime attachment either" situation view_extra_image.php is already in -
 * neither an 'episode' nor a 'featurette' xref row's xkey_ext is a liberty_files attachment, so
 * there is no existing serving route for either. Kept this name despite covering both - Lester
 * explicitly chose not to rename it once the episode/Featurette parallel was clear.
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
	"SELECT content_id, xkey_ext FROM `".BIT_DB_PREFIX."liberty_xref` WHERE xref_id = ? AND item IN ('episode', 'featurette')",
	[ $xrefId ]
) : null;
if( !$row ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such video' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}

$gContent = FisheyeImage::lookup( [ 'content_id' => $row['content_id'] ] );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such video' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}
$gContent->verifyViewPermission();

$root = method_exists( $gContent, 'getImageStorageRoot' ) ? $gContent->getImageStorageRoot() : '';
$path = $root.$row['xkey_ext'];
if( empty( $root ) || !is_file( $path ) ) {
	$gBitSystem->fatalError( KernelTools::tra( 'Video file not found' ), null, null, HttpStatusCodes::HTTP_NOT_FOUND );
}

// Experimental "VLC" button fallback for content the browser's own <video> can't play (e.g. a
// not-yet mpeg2_tidy.php'd original) - a one-line .m3u pointing back at this same URL (minus
// &vlc=1), rather than the raw video bytes. Browsers don't handle audio/x-mpegurl inline, so this
// downloads and hands off to whatever's registered for .m3u locally (VLC, by default on this
// desktop) - sidesteps the Firefox "Open with VLC" addon entirely (it only offers its context
// menu on links ending in a recognised media extension, which this endpoint's query-string URL
// never did).
if( !empty( $_REQUEST['vlc'] ) ) {
	$scheme = ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
	$streamUrl = $scheme.'://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?xref_id='.$xrefId;
	// video/vnd.mpegurl, not the more common audio/x-mpegurl - this desktop's own mimeapps.list
	// maps that exact string to vlc.desktop (confirmed live); audio/x-mpegurl falls back to
	// brasero here instead, which is why the button downloaded fine but launched nothing.
	header( 'Content-Type: video/vnd.mpegurl' );
	header( 'Content-Disposition: attachment; filename="'.pathinfo( $path, PATHINFO_FILENAME ).'.m3u"' );
	echo "#EXTM3U\n#EXTINF:-1,".$gContent->getTitle()."\n".$streamUrl."\n";
	exit;
}

$mimeType = $gBitSystem->verifyMimeType( $path );
header( 'Content-Disposition: inline; filename="'.basename( $path ).'"' );
\Bitweaver\Liberty\liberty_serve_range_file( $path, $mimeType );
