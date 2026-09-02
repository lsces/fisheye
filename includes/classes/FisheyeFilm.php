<?php
/**
 * Film — extends FisheyeImage with content_type_guid='fisheyefilm'.
 *
 * Exists purely to ring-fence film-specific xref_group/item registrations (genre/director/
 * writer/star/content_rating/duration, IMDB link) away from plain fisheyeimage photo rows and
 * from FisheyeSeason/FisheyeAlbum's own item sets — no behavioural difference from FisheyeImage
 * otherwise, same pattern as Contact/ContactPerson/ContactBusiness. See liberty.md's 2026-09-01
 * entries for the wider film/TV/music design, and fisheye.md's 2026-09-02 entry for this split.
 *
 * @package fisheye
 */
namespace Bitweaver\Fisheye;

define( 'FISHEYEFILM_CONTENT_TYPE_GUID', 'fisheyefilm' );

class FisheyeFilm extends FisheyeImage {

	public function __construct( $pImageId = null, $pContentId = null ) {
		parent::__construct( $pImageId, $pContentId );
		$this->mContentTypeGuid = FISHEYEFILM_CONTENT_TYPE_GUID;
		$this->registerContentType( FISHEYEFILM_CONTENT_TYPE_GUID, [
			'content_type_guid' => FISHEYEFILM_CONTENT_TYPE_GUID,
			'content_name'      => 'Film',
			'handler_class'     => 'FisheyeFilm',
			'handler_package'   => 'fisheye',
			'handler_file'      => 'FisheyeFilm.php',
			'maintainer_url'    => 'https://www.bitweaver.org',
		] );
		// mPackageGuid='fisheye' is set automatically by registerContentType()
		// because handler_package('fisheye') != content_type_guid('fisheyefilm').
	}
}
