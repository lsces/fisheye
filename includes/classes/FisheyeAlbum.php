<?php
/**
 * Music album — extends FisheyeImage with content_type_guid='fisheyealbum'.
 *
 * The real, folder-leaf content_id in the artist->album->track tree (an "artist"/"composer"
 * itself has no content_id at all - it's a computed, FoodDay-pattern browsing level over its
 * albums, not yet built - see fisheye.md's 2026-09-02 entry). Ring-fences album-level metadata
 * (artist/composer, MusicBrainz+Discogs links) plus the TRACK xref item away from plain
 * fisheyeimage photo rows and from FisheyeFilm/FisheyeSeason's own item sets - no other
 * behavioural difference from FisheyeImage, same pattern as Contact/ContactPerson/ContactBusiness.
 *
 * @package fisheye
 */
namespace Bitweaver\Fisheye;

define( 'FISHEYEALBUM_CONTENT_TYPE_GUID', 'fisheyealbum' );

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
}
