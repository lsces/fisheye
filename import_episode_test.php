<?php
/**
 * ONE-OFF TEST SCRIPT - registers a real TV episode as a liberty_xref row under its season's
 * FisheyeSeason content_id, as a smoke test for the episode side of the disk/TV design (see
 * liberty.md's 2026-09-01 "correction: episode is a liberty_xref row" entry for the settled
 * design this follows). Creates the season itself first if it doesn't already exist. Delete once
 * a real directory-scanning TV importer exists.
 *
 * Run against a specific site via its own symlinked path, e.g.:
 *   php /srv/website/rdmcloud/fisheye/import_episode_test.php
 *
 * @package fisheye
 */

namespace Bitweaver\Fisheye;

use Bitweaver\Liberty\LibertyXrefScheme;

global $gBitSystem, $gBitDb, $gBitUser, $_SERVER;

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? '';
// This script is meant to be run through a real web request (php-fpm as nginx) - DOCUMENT_ROOT
// is already correct in that case, only patch it for the CLI fallback case, where it's empty.
// CLI has no real DOCUMENT_ROOT - setup_inc.php's own BIT_ROOT_PATH fallback resolves from its
// own __FILE__, which PHP flattens through the kernel/_bw5 symlink chain down to the dev repo,
// not the site root. $_SERVER['PWD'] (the shell's logical cwd) is used rather than
// dirname(__FILE__) - __FILE__ resolves through the same symlink chain and would land back on
// the dev repo too.
if( empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
	$_SERVER['DOCUMENT_ROOT'] = dirname( $_SERVER['PWD'] ?? getenv( 'PWD' ) ?? getcwd() );
}

chdir( dirname( __FILE__ ) );
require_once '../kernel/includes/setup_inc.php';

// Log in as the real 'lsces' admin - same reasoning as import_disk_test.php's own 2026-09-02 fix.
$adminUserId = $gBitDb->getOne( "SELECT user_id FROM users_users WHERE login = ?", [ 'lsces' ] );
$gBitUser->mUserId = $adminUserId;
$gBitUser->load();
$gBitUser->loadPermissions( true );

require_once dirname( __DIR__ ).'/liberty/plugins/mime.film.php';

// The show/season/episode being registered - real data, pulled from the live Plex DB
// (Inspector Morse, Series 1, Episode 1 - "The Dead of Jericho").
$showTitle    = 'Inspector Morse';
$seasonTitle  = 'Inspector Morse - Series 1';
$relativePath = 'TV Shows/Inspector Morse/Season 1/Inspector Morse - S01E01 - The Dead of Jericho.mkv';
$episode = [
	'xorder'  => 1,
	'title'   => 'The Dead of Jericho',
	'summary' => "Morse is passed over for promotion and gets a new working partner in the shape of Lewis, who is not sure what to make of his new boss. Their first case together is a murder, when Anne Staveley, who was Morse's friend in a choral society, is found hanged in her house in the Jericho area of Oxford. The death looks like suicide, but Morse has his own reasons for treating the case as a murder investigation.",
	'air_date' => '1987-01-06',
];

$root = \Bitweaver\Liberty\mime_film_get_tvshow_storage_root( $showTitle );
print "fisheye_tvshow_storage_root (resolved for '$showTitle') = '$root'\n";
if( empty( $root ) || !is_file( $root.$relativePath ) ) {
	print "ABORT: '".$root.$relativePath."' is not a file.\n";
	exit( 1 );
}

// Find or create the season - looked up by title, never by a hardcoded content_id/gallery_id
// (see fisheye.md's 2026-09-02 "content_id assumption bug" entry for why).
$seasonContentId = $gBitDb->getOne(
	"SELECT content_id FROM liberty_content WHERE content_type_guid = 'fisheyeseason' AND title = ?",
	[ $seasonTitle ]
);
if( $seasonContentId ) {
	$season = new FisheyeSeason( null, $seasonContentId );
	$season->load();
	print "Season '$seasonTitle' already exists - content_id=$seasonContentId\n";
} else {
	$season = new FisheyeSeason();
	$seasonParamHash = [ 'title' => $seasonTitle ];
	if( !$season->store( $seasonParamHash ) ) {
		print "ABORT: could not create season - ".implode( '; ', $season->mErrors )."\n";
		exit( 1 );
	}
	print "Created season '$seasonTitle' - content_id={$season->mContentId}\n";

	$tvShowsGallery = $gBitDb->getOne(
		"SELECT lc.content_id FROM liberty_content lc INNER JOIN fisheye_gallery fg ON fg.content_id = lc.content_id WHERE lc.content_type_guid = 'fisheyegallery' AND lc.title = ?",
		[ 'TV Shows' ]
	);
	if( $tvShowsGallery ) {
		$gallery = new FisheyeGallery( null, $tvShowsGallery );
		$gallery->load();
		if( $gallery->addItem( $season->mContentId ) ) {
			print "Linked season into 'TV Shows' gallery.\n";
		} else {
			print "WARNING: addItem() to 'TV Shows' gallery failed.\n";
		}
	} else {
		print "WARNING: no 'TV Shows' gallery found - season not linked into any gallery.\n";
	}
}

// Store the episode - one liberty_xref row, content_id = the season's own, per the settled
// design. xkey_ext = root-relative file path, data = JSON metadata, xorder = episode number.
// Two real gotchas found reading LibertyXref::verify() directly rather than guessing: content_id
// isn't auto-filled from the calling object by storeXref() - must be passed explicitly here - and
// the raw text/JSON blob is stored under the key 'edit', not 'data' (verify() only reads
// $pParamHash['edit'] into xref_store['data'], line ~249).
$pParamHash = [
	'content_id' => $season->mContentId,
	'item'       => 'episode',
	'xkey_ext'   => $relativePath,
	'edit'       => json_encode( [ 'title' => $episode['title'], 'summary' => $episode['summary'], 'air_date' => $episode['air_date'] ] ),
	'xorder'     => $episode['xorder'],
];
if( $season->storeXref( $pParamHash ) ) {
	print "Stored episode xref - xref_id={$pParamHash['xref_id']}\n";
} else {
	print "FAILED to store episode xref.\n";
	exit( 1 );
}

// Reload and confirm
$check = new FisheyeSeason( null, $season->mContentId );
$check->load();
print "Reloaded season '{$check->getTitle()}' - content_id={$check->mContentId}\n";
