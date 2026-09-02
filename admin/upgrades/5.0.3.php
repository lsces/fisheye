<?php
/**
 * @package fisheye
 */

global $gBitInstaller;

$X = BIT_DB_PREFIX;

$gBitInstaller->registerPackageUpgrade(
	[
		'package'     => FISHEYE_PKG_NAME,
		'version'     => '5.0.3',
		'description' => 'FisheyeFilm/FisheyeSeason/FisheyeAlbum - three new phantom content '
			.'types (own content_type_guid, no behavioural difference from FisheyeImage otherwise, '
			.'same pattern as Contact/ContactPerson/ContactBusiness) replacing the shared '
			.'\'fisheyeimage\'-level metadata group as the *forward* path for film/TV/music '
			.'content - each ring-fences its own xref_group/item set via LibertyXrefType\'s '
			.'dual-guid scoping, so plain photo galleries never see genre/director/IMDB-link '
			.'fields and each media kind only sees its own external-ID sources. '
			.'liberty_content_types rows for the three new guids are NOT declared here - that '
			.'table has its own idempotent registration path via bit_setup_inc.php\'s '
			.'registerContentType() calls (added alongside this upgrade); a raw INSERT here would '
			.'duplicate that non-idempotently, exactly the bug found+fixed in contact on '
			.'2026-08-19. Existing \'fisheyeimage\'-typed content (registered by 5.0.2) is '
			.'untouched - nothing here migrates live content_type_guid values, see fisheye.md\'s '
			.'2026-09-02 entry. Adds: genre/director/writer/star/content_rating/duration at both '
			.'fisheyefilm and fisheyeseason; genre/artist/composer at fisheyealbum; IMDB link at '
			.'fisheyefilm and fisheyeseason; TheTVDB link at fisheyeseason; MusicBrainz+Discogs '
			.'links at fisheyealbum (all via the new generic \'href\' xref item template - '
			.'cross_ref_href was being loaded into every xref row already but no template '
			.'rendered it as a link until now, see liberty/MANUAL.md); EPISODE items at '
			.'fisheyeseason and TRACK items at fisheyealbum (content_id = the season/album\'s '
			.'own, one liberty_xref row per episode/track - xkey_ext = root-relative file path, '
			.'data = JSON metadata, xorder = episode/track number - plain \'text\' template '
			.'pending a dedicated view once real playback/browsing is built). role_id=3 '
			.'(Registered) throughout - reassessed from the 5.0.2 fisheyeimage set\'s role_id=-1 '
			.'(Anonymous): that made sense for a single already-public film test row, but opening '
			.'every new film/season/album/episode/track item to anonymous view by default is too '
			.'broad now the set is growing - a site can still loosen individual items per-role '
			.'via admin_xref_sources.php.',
	],
	[
		[ 'QUERY' => [
			'SQL92' => [
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('metadata','fisheyefilm','Film Details',1,3,'','')",
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('external','fisheyefilm','External Links',2,3,'','')",
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('metadata','fisheyeseason','Season Details',1,3,'','')",
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('external','fisheyeseason','External Links',2,3,'','')",
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('episodes','fisheyeseason','Episodes',3,3,'','')",
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('metadata','fisheyealbum','Album Details',1,3,'','')",
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('external','fisheyealbum','External Links',2,3,'','')",
				"INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('tracks','fisheyealbum','Tracks',3,3,'','')",

				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('genre',         'fisheyefilm','metadata','Genre',         1,3,'',                            'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('director',      'fisheyefilm','metadata','Director',      1,3,'',                            'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('writer',        'fisheyefilm','metadata','Writer',        1,3,'',                            'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('star',          'fisheyefilm','metadata','Star',          1,3,'',                            'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('content_rating', 'fisheyefilm','metadata','Content Rating',0,3,'',                            'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('duration',       'fisheyefilm','metadata','Duration',      0,3,'',                            'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('imdb',          'fisheyefilm','external','IMDB',          0,3,'https://www.imdb.com/title/', 'href',NULL)",

				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('genre',         'fisheyeseason','metadata','Genre',         1,3,'',                                        'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('director',      'fisheyeseason','metadata','Director',      1,3,'',                                        'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('writer',        'fisheyeseason','metadata','Writer',        1,3,'',                                        'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('star',          'fisheyeseason','metadata','Star',          1,3,'',                                        'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('content_rating', 'fisheyeseason','metadata','Content Rating',0,3,'',                                        'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('duration',       'fisheyeseason','metadata','Duration',      0,3,'',                                        'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('imdb',          'fisheyeseason','external','IMDB',          0,3,'https://www.imdb.com/title/',             'href',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('tvdb',          'fisheyeseason','external','TheTVDB',       0,3,'https://thetvdb.com/dereferrer/series/', 'href',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('episode',       'fisheyeseason','episodes','Episode',       1,3,'',                                        'text',NULL)",

				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('genre',         'fisheyealbum','metadata','Genre',         1,3,'',                                  'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('artist',        'fisheyealbum','metadata','Artist',        1,3,'',                                  'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('composer',      'fisheyealbum','metadata','Composer',      1,3,'',                                  'text',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('mbid',          'fisheyealbum','external','MusicBrainz',   0,3,'https://musicbrainz.org/release/', 'href',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('discogs',       'fisheyealbum','external','Discogs',       0,3,'https://www.discogs.com/release/', 'href',NULL)",
				"INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('track',         'fisheyealbum','tracks',  'Track',         1,3,'',                                  'text',NULL)",
			],
		]],
	]
);
