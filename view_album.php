<?php
/**
 * Dedicated view page for a music album (FisheyeAlbum) - same shape as view_film.php, bucketing
 * this album's own xref data into template-friendly arrays rather than hardcoding which x_group
 * an item lives under (see that file's own docblock for why that matters).
 *
 * 'track' xrefs (raw file paths, not real LibertyMime attachments - same reasoning as an
 * episode's own xref, see FisheyeAlbum.php's docblock) are the track listing; each one plays via
 * play_track.php (xref_id only, mirrors play_episode.php).
 *
 * @package fisheye
 * @subpackage functions
 */

namespace Bitweaver\Fisheye;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';
global $gBitSystem, $gBitSmarty;

$gBitSystem->verifyPackage( 'fisheye' );

$gContent = FisheyeImage::lookup( $_REQUEST );
if( !$gContent || !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No album exists with the given ID' ), 'error.tpl' );
}
$gContent->verifyViewPermission();
$gContent->addHit();

$gContent->loadXrefInfo();
$tracks = [];
$artist = null;
if( $gContent->mXrefInfo ) {
	foreach( $gContent->mXrefInfo->allXrefs() as $xref ) {
		switch( $xref['item'] ) {
			case 'track':
				$data = !empty( $xref['data'] ) ? json_decode( $xref['data'], true ) : [];
				$tracks[] = [
					'xref_id' => $xref['xref_id'],
					'title'   => $data['title'] ?? $xref['xkey_ext'],
					'disc'    => $data['disc'] ?? 1,
					'xorder'  => (int)$xref['xorder'],
				];
				break;
			case 'artist': $artist = $xref['xkey_ext']; break;
		}
	}
}
usort( $tracks, fn( $a, $b ) => $a['xorder'] <=> $b['xorder'] );

// Grouped by disc here, not detected via a boundary-change check in the template - a single-disc
// album (the common case) just gets one group and no "Disc 1" heading at all.
$discs = [];
foreach( $tracks as $track ) {
	$discs[$track['disc']][] = $track;
}

$gBitSmarty->assign( 'discs', $discs );
$gBitSmarty->assign( 'multiDisc', count( $discs ) > 1 );
$gBitSmarty->assign( 'artist', $artist );
$gBitSmarty->assign( 'gContent', $gContent );

$gBitSystem->setCanonicalLink( $gContent->getDisplayUrl() );
$gBitSystem->setBrowserTitle( $gContent->getTitle() );
$gBitSystem->display( 'bitpackage:fisheye/view_album.tpl', null, [ 'display_mode' => 'display' ] );
