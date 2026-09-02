<?php
/**
 * Admin page for registering an already-on-disk film via mime.film.php's no-copy attachment path
 * - the real, permanent replacement for hand-editing import_disk_test.php's $toRegister array and
 * running it via CLI. Runs as a genuine authenticated admin request (php-fpm as nginx), so
 * thumbnail generation under storage/attachments/ works correctly with no permission workarounds -
 * see fisheye.md's 2026-09-02 "through bitweaver, not hacks" entry for why that matters.
 *
 * Plex metadata backfill itself lives in FisheyeFilm::reloadPlexMetadata() (moved there
 * 2026-09-02) so edit_film.php's 'Reload Metadata' action can reuse it for a film imported
 * before the backfill existed - see that method's docblock for the Plex-matching detail.
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
				$plexMeta = $film->reloadPlexMetadata();
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
