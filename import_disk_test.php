<?php
/**
 * ONE-OFF TEST SCRIPT - registers a single already-on-disk film through mime.film.php's
 * "mimeplugin" (no-upload) path, as a smoke test for the whole disk/film mime-plugin chain
 * before building a real directory-scanning importer. Delete once that importer exists.
 *
 * Run against a specific site via its own symlinked path, e.g.:
 *   php /srv/website/rdmcloud/fisheye/import_disk_test.php
 *
 * @package fisheye
 */

namespace Bitweaver\Fisheye;

use Bitweaver\Liberty\LibertyMime;

global $gBitSystem, $gLibertySystem, $_SERVER;

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? '';
// CLI has no real DOCUMENT_ROOT - setup_inc.php's own BIT_ROOT_PATH fallback resolves from its
// own __FILE__, which PHP flattens through the kernel/_bw5 symlink chain down to the dev repo,
// not the site root. Confirmed for real 2026-09-02 (previously just predicted). Deliberately
// uses $_SERVER['PWD'] (the shell's logical cwd), not dirname(__FILE__) - __FILE__ resolves
// through the same symlink chain and would land back on the dev repo too.
$_SERVER['DOCUMENT_ROOT'] = dirname( $_SERVER['PWD'] ?? getenv( 'PWD' ) ?? getcwd() );

chdir( dirname( __FILE__ ) );
require_once '../kernel/includes/setup_inc.php';

// Log in as the real 'lsces' admin - registering content as Anonymous (user_id=-1, the CLI
// bootstrap default) isn't how a real admin operation should happen; same 3 lines
// RoleUser::login() runs after a successful password check, skipping the parts that only make
// sense for a real HTTP request. LibertyContent::verify() picks up $gBitUser->mUserId
// automatically for the film content created below.
global $gBitDb, $gBitUser;
$adminUserId = $gBitDb->getOne( "SELECT user_id FROM users_users WHERE login = ?", [ 'lsces' ] );
$gBitUser->mUserId = $adminUserId;
$gBitUser->load();
$gBitUser->loadPermissions( true );

// mime plugins are scanned lazily (normally triggered by lookupMimeHandler()) - our own
// store() path deliberately never calls that, so force this one plugin file to load directly.
// Lives in liberty/plugins/ now, not fisheye/liberty_plugins/ - see liberty.md's 2026-09-01
// "inconsistent plugin registration" entries for why.
require_once dirname( __DIR__ ).'/liberty/plugins/mime.film.php';

// registered with auto_activate=>false - getPluginFunction()'s isPluginActive() gate blocks it
// entirely until this runs once (same one-time step mimeflatdefault needed). Persists in
// kernel_config after this, doesn't need repeating on every request once done for real.
if( !$gLibertySystem->isPluginActive( 'mimefilm' )) {
	$gLibertySystem->setActivePlugin( 'mimefilm' );
	print "Activated mimefilm plugin.\n";
}

$root = \Bitweaver\Liberty\mime_film_get_storage_root();
print "fisheye_disk_storage_root (normalised) = '$root'\n";

// Looked up by title, not a hardcoded gallery_id - sequence-generated ids are never guaranteed to
// land the same way across different databases (desktop/srv9/srv10 all have independent
// liberty_content_id_seq/fisheye_gallery_id_seq histories), confirmed the hard way 2026-09-02.
global $gBitDb;
$galleryContentId = $gBitDb->getOne(
	"SELECT lc.content_id FROM liberty_content lc INNER JOIN fisheye_gallery fg ON fg.content_id = lc.content_id WHERE lc.content_type_guid = 'fisheyegallery' AND lc.title = ?",
	[ 'Films' ]
);
$gallery = new FisheyeGallery( null, $galleryContentId );
$gallery->load();
if( !$gallery->isValid() ) {
	print "ABORT: could not load the 'Films' gallery - run rdmcloud's apply_media_scheme.php first.\n";
	exit( 1 );
}
print "Loaded gallery: ".$gallery->getTitle()." (content_id=".$gallery->mContentId.")\n";

// h264+aac confirmed via ffprobe - genuinely browser-playable, unlike the first (mpeg4/mkv) test.
$toRegister = [
	'Films/James Bond/Casino Royale (2006).mp4'   => 'Casino Royale (2006)', // 2.25GB - file_size overflow fix test
];

foreach( $toRegister as $relativePath => $title ) {
	print "\n--- $title ---\n";
	if( empty( $root ) || !is_file( $root.$relativePath )) {
		print "ABORT: '".$root.$relativePath."' is not a file.\n";
		continue;
	}

	$film = new FisheyeFilm();
	$pParamHash = [
		'title' => $title,
		'mimeplugin' => [
			'mimefilm' => [ 'file_name' => $relativePath ],
		],
	];

	if( $film->store( $pParamHash )) {
		print "Stored content_id=".$film->mContentId."\n";
		if( $gallery->addItem( $film->mContentId )) {
			print "Linked into gallery.\n";
		} else {
			print "WARNING: addItem() to gallery failed.\n";
		}
		$check = new FisheyeFilm( null, $film->mContentId );
		$check->load();
		print "Reloaded - source_file: ".( $check->mInfo['image_file']['source_file'] ?? '(none)' )."\n";
	} else {
		print "STORE FAILED:\n";
		print_r( $film->mErrors );
	}
}
