<?php

$tables = [

'fisheye_gallery' => "
	gallery_id I4 PRIMARY,
	content_id I4,
	rows_per_page I4,
	cols_per_page I4,
	thumbnail_size C(32),
	preview_content_id I4,
	image_comment C(1)
",

'fisheye_gallery_image_map' => "
	gallery_content_id I4 NOTNULL,
	item_content_id I4 NOTNULL,
	item_position F
",

'fisheye_image' => "
	image_id I4 PRIMARY,
	content_id I4 NOTNULL,
	photo_date I8,
	width I4,
	height I4
",
];

global $gBitInstaller;

foreach( array_keys( $tables ) AS $tableName ) {
	$gBitInstaller->registerSchemaTable( FISHEYE_PKG_NAME, $tableName, $tables[$tableName] );
}

$indices = [
	'fisheye_gallery_id_idx'      => [ 'table' => 'fisheye_gallery', 'cols' => 'gallery_id', 'opts' => null ],
	'fisheye_gallery_content_idx' => [ 'table' => 'fisheye_gallery', 'cols' => 'content_id', 'opts' => [ 'UNIQUE' ] ],
	'fisheye_image_id_idx'        => [ 'table' => 'fisheye_image', 'cols' => 'image_id', 'opts' => null ],
	'fisheye_image_content_idx'   => [ 'table' => 'fisheye_image', 'cols' => 'content_id', 'opts' => [ 'UNIQUE' ] ],
];
$gBitInstaller->registerSchemaIndexes( FISHEYE_PKG_NAME, $indices );

$gBitInstaller->registerPackageInfo( FISHEYE_PKG_NAME, [
	'description' => "FishEye is a package for creating image galleries",
	'license' => '<a href="http://www.gnu.org/licenses/licenses.html#LGPL">LGPL</a>',
] );

// ### Sequences
$sequences = [
	'fisheye_gallery_id_seq' => [ 'start' => 1 ],
	'fisheye_image_id_seq' => [ 'start' => 1 ],
];
$gBitInstaller->registerSchemaSequences( FISHEYE_PKG_NAME, $sequences );

// ### Default Preferences
$gBitInstaller->registerPreferences( FISHEYE_PKG_NAME, [
	[ FISHEYE_PKG_NAME, 'fisheye_list_title','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_list_created','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_list_user','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_list_hits','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_list_thumbnail','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_list_thumbnail_size','small'],
	[ FISHEYE_PKG_NAME, 'fisheye_gallery_list_title','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_gallery_list_description','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_gallery_list_image_titles','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_gallery_default_rows_per_page','5'],
	[ FISHEYE_PKG_NAME, 'fisheye_gallery_default_cols_per_page','3'],
	[ FISHEYE_PKG_NAME, 'fisheye_gallery_default_thumbnail_size','small'],
	[ FISHEYE_PKG_NAME, 'fisheye_image_list_title','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_image_list_description','y'],
	[ FISHEYE_PKG_NAME, 'fisheye_image_default_thumbnail_size','medium'],
	[ FISHEYE_PKG_NAME, 'fisheye_menu_text','Image Galleries'],
	// more intuitive if we can see all galleries we can upload images to
	[ FISHEYE_PKG_NAME, 'fisheye_show_public_on_upload','n'],
	[ FISHEYE_PKG_NAME, 'fisheye_show_all_to_admins','n'],
] );

// ### Default User Permissions
$gBitInstaller->registerUserPermissions( FISHEYE_PKG_NAME, [
	['p_fisheye_list_galleries', 'Can list image galleries', 'basic', FISHEYE_PKG_NAME],
	['p_fisheye_view', 'Can view image galleries', 'basic', FISHEYE_PKG_NAME],
	['p_fisheye_create', 'Can create an image gallery', 'registered', FISHEYE_PKG_NAME],
	['p_fisheye_update', 'Can update image gallery', 'editors', FISHEYE_PKG_NAME],
	['p_fisheye_upload', 'Can upload images to gallery', 'registered', FISHEYE_PKG_NAME],
	['p_fisheye_admin', 'Can admin image galleries', 'editors', FISHEYE_PKG_NAME],
	['p_fisheye_upload_nonimages', 'Can upload non_image files', 'editors', FISHEYE_PKG_NAME],
	['p_fisheye_change_thumb_size', 'Can set the thumbnail size for a gallery', 'editors', FISHEYE_PKG_NAME],
	['p_fisheye_create_public_gal', 'Can create public galleries any user can load images into', 'editors', FISHEYE_PKG_NAME],
	['p_fisheye_download_gallery_arc',' Can download an archived copy of Fisheye gallery', 'registered', FISHEYE_PKG_NAME],
] );

if( defined( 'RSS_PKG_NAME' )) {
	$gBitInstaller->registerPreferences( FISHEYE_PKG_NAME, [
		[ RSS_PKG_NAME, FISHEYE_PKG_NAME.'_rss', 'y'],
	]);
}

// ### Register content types
$gBitInstaller->registerContentObjects( FISHEYE_PKG_NAME, [
	'FisheyeGallery'=>FISHEYE_PKG_CLASS_PATH.'FisheyeGallery.php',
	'FisheyeImage'=>FISHEYE_PKG_CLASS_PATH.'FisheyeImage.php',
	'FisheyeFilm'=>FISHEYE_PKG_CLASS_PATH.'FisheyeFilm.php',
	'FisheyeSeason'=>FISHEYE_PKG_CLASS_PATH.'FisheyeSeason.php',
	'FisheyeAlbum'=>FISHEYE_PKG_CLASS_PATH.'FisheyeAlbum.php',
] );

// Requirements
$gBitInstaller->registerRequirements( FISHEYE_PKG_NAME, [
	'liberty' => [ 'min' => '5.0.0' ],
]);

// ### Xref groups/items, original 'fisheyeimage'-level set (mime.film.php and friends,
// disk-registered video content) - kept exactly as-is for any pre-existing content still
// registered as plain FisheyeImage (a real film should be a FisheyeFilm going forward, see below,
// but nothing here migrates already-live content_type_guid values - see fisheye.md's 2026-09-02
// entry). Superseded as the *forward* path by the per-class groups below, which ring-fence each
// class's own item set via LibertyXrefType's dual-guid (contentTypeGuid, packageGuid) scoping -
// items registered at 'fisheyeimage' specifically are invisible to FisheyeFilm/Season/Album rows
// and vice versa, so nothing here needs removing for the split to work cleanly.
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('metadata','fisheyeimage','Film Details',1,-1,'','')";

$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('genre',         'fisheyeimage','metadata','Genre',         1,-1,'','text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('director',      'fisheyeimage','metadata','Director',      1,-1,'','text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('writer',        'fisheyeimage','metadata','Writer',        1,-1,'','text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('star',          'fisheyeimage','metadata','Star',          1,-1,'','text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('content_rating', 'fisheyeimage','metadata','Content Rating',0,-1,'','text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('duration',       'fisheyeimage','metadata','Duration',      0,-1,'','text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('artist',        'fisheyeimage','metadata','Artist',        1,-1,'','text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('composer',      'fisheyeimage','metadata','Composer',      1,-1,'','text',NULL)";

// ### FisheyeFilm ('fisheyefilm') - own metadata set + IMDB link
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('metadata','fisheyefilm','Film Details',1,3,'','')";
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('external','fisheyefilm','External Links',2,3,'','')";

$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('genre',         'fisheyefilm','metadata','Genre',         1,3,'',                              'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('director',      'fisheyefilm','metadata','Director',      1,3,'',                              'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('writer',        'fisheyefilm','metadata','Writer',        1,3,'',                              'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('star',          'fisheyefilm','metadata','Star',          1,3,'',                              'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('content_rating', 'fisheyefilm','metadata','Content Rating',0,3,'',                              'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('duration',       'fisheyefilm','metadata','Duration',      0,3,'',                              'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('imdb',          'fisheyefilm','external','IMDB',          0,3,'https://www.imdb.com/title/',   'href',NULL)";

// ### FisheyeSeason ('fisheyeseason') - own metadata set, IMDB+TheTVDB links, EPISODE items
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('metadata','fisheyeseason','Season Details',1,3,'','')";
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('external','fisheyeseason','External Links',2,3,'','')";
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('episodes','fisheyeseason','Episodes',3,3,'','')";

$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('genre',         'fisheyeseason','metadata','Genre',         1,3,'',                                             'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('director',      'fisheyeseason','metadata','Director',      1,3,'',                                             'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('writer',        'fisheyeseason','metadata','Writer',        1,3,'',                                             'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('star',          'fisheyeseason','metadata','Star',          1,3,'',                                             'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('content_rating', 'fisheyeseason','metadata','Content Rating',0,3,'',                                             'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('duration',       'fisheyeseason','metadata','Duration',      0,3,'',                                             'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('imdb',          'fisheyeseason','external','IMDB',          0,3,'https://www.imdb.com/title/',                  'href',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('tvdb',          'fisheyeseason','external','TheTVDB',       0,3,'https://thetvdb.com/dereferrer/series/',      'href',NULL)";
// EPISODE: content_id = the season's own content_id, one liberty_xref row per episode -
// xkey_ext holds the root-relative file path (same convention as mime.film.php's file_name),
// data holds JSON metadata (title, summary, air date - Plex-sourced where available), xorder is
// the episode number. Plain 'text' template for now (renders xkey/xkey_ext/data as three columns)
// - a dedicated view is future work once real playback/browsing is built, see fisheye.md.
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('episode',       'fisheyeseason','episodes','Episode',       1,3,'',                                             'text',NULL)";

// ### FisheyeAlbum ('fisheyealbum') - own metadata set, MusicBrainz+Discogs links, TRACK items
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('metadata','fisheyealbum','Album Details',1,3,'','')";
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('external','fisheyealbum','External Links',2,3,'','')";
$xrefTypes[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('tracks','fisheyealbum','Tracks',3,3,'','')";

$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('genre',         'fisheyealbum','metadata','Genre',         1,3,'',                                     'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('artist',        'fisheyealbum','metadata','Artist',        1,3,'',                                     'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('composer',      'fisheyealbum','metadata','Composer',      1,3,'',                                     'text',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('mbid',          'fisheyealbum','external','MusicBrainz',   0,3,'https://musicbrainz.org/release/',    'href',NULL)";
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('discogs',       'fisheyealbum','external','Discogs',       0,3,'https://www.discogs.com/release/',    'href',NULL)";
// TRACK: same shape as EPISODE above, content_id = the album's own content_id.
$xrefItems[] = "INSERT INTO `".BIT_DB_PREFIX."liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('track',         'fisheyealbum','tracks',  'Track',         1,3,'',                                     'text',NULL)";

$gBitInstaller->registerSchemaDefault( FISHEYE_PKG_NAME, array_merge( $xrefTypes, $xrefItems ) );

