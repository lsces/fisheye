<?php
/**
 * @version  $Revision$
 * $Header$
 * @package  liberty
 * @subpackage plugins_storage
 */

/**
 * required setup
 */
namespace Bitweaver\Liberty;

use Bitweaver\Fisheye\FisheyeImage;
use Bitweaver\BitBase;
use Bitweaver\KernelTools;

/**
 * definitions
 */
define( 'PLUGIN_GUID_DATAGALLERY', 'datagallery' );

global $gLibertySystem;

$pluginParams = [
	'tag'           => 'gallery',
	'title'         => 'Fisheye Gallery',
	'description'   => KernelTools::tra( "Display a list of images in other content. This plugin only works with files that have been uploaded using fisheye." ),
	'help_page'     => 'DataPluginGallery',

	'auto_activate' => false,
	'requires_pair' => false,
	'syntax'        => '{gallery id= }',
	'plugin_type'   => DATA_PLUGIN,

	// display icon in quicktags bar
	'booticon'       => '{biticon ipackage="icons" iname="image-x-generic" iexplain="Image"}',
	'taginsert'     => '{gallery id= size= nolink=}',

	// functions
	'help_function' => 'data_gallery_help',
	'load_function' => 'data_gallery',
];
$gLibertySystem->registerPlugin( PLUGIN_GUID_DATAGALLERY, $pluginParams );
$gLibertySystem->registerDataTag( $pluginParams['tag'], PLUGIN_GUID_DATAGALLERY );

function data_gallery( $pData, $pParams ) {
	global $gBitSystem, $gBitSmarty;
	$ret = ' ';

	$imgStyle = '';

	$wrapper = \Bitweaver\Liberty\liberty_plugins_wrapper_style( $pParams );

	$description = !isset( $wrapper['description'] ) ? $wrapper['description'] : null;
	foreach( $pParams as $key => $value ) {
		if( !empty( $value ) ) {
			switch( $key ) {
				// rename a couple of parameters
				case 'width':
				case 'height':
					if( preg_match( "/^\d+(em|px|%|pt)$/", trim( $value ) ) ) {
						$imgStyle .= $key.':'.$value.';';
					} elseif( preg_match( "/^\d+$/", $value ) ) {
						$imgStyle .= $key.':'.$value.'px;';
					}
					// remove values from the hash that they don't get used in the div as well
					$pParams[$key] = null;
					break;
			}
		}
	}

	$wrapper = \Bitweaver\Liberty\liberty_plugins_wrapper_style( $pParams );

	if( !empty( $pParams['src'] ) ) {
		$thumbUrl = $pParams['src'];
	} elseif( BitBase::verifyId( $pParams['id'] ) && $gBitSystem->isPackageActive( 'fisheye' )) {
		require_once FISHEYE_PKG_CLASS_PATH.'FisheyeImage.php';

		$gallery = new FisheyeImage();
			$listHash = $pParams;
			$listHash['size'] = 'small';
			$listHash['gallery_id'] = $pParams['id'];
			$listHash['max_records'] = 3;
			$listHash['sort_mode'] = 'random';
			$images = $gallery->getList( $listHash );
$out = '<div>';
  foreach( $images as $image ) {
		// insert source url if we need the original file
			if( !empty( $pParams['size'] ) && $pParams['size'] == 'original' ) {
				$thumbUrl = $image['source_url'];
			} elseif( $image['thumbnail_url'] ) {
				$thumbUrl = $image['thumbnail_url'];
			}

			if( empty( $image['$description'] ) ) {
				$description = !isset( $wrapper['description'] ) ? $wrapper['description'] : $image['title'];
			}

		// check if we have a valid thumbnail
		if( !empty( $thumbUrl )) {
			// set up image first
			$ret = '<img class="img-responsive"'.
				' alt="'.  $description.'"'.
				' title="'.$description.'"'.
				' src="'  .$thumbUrl.'"'.
				' style="float:left; '.$imgStyle.'"'.
				' />';

			if( !empty( $pParams['nolink'] ) ) {
			} elseif( !empty( $wrapper['link'] ) ) {
				// if this image is linking to something, wrap the image with the <a>
				$ret = '<a href="'.trim( $wrapper['link'] ).'">'.$ret.'</a>';
			} elseif ( empty( $pParams['size'] ) || $pParams['size'] != 'original' ) {
				if ( $image['source_url'] ) {
					$ret = '<a href="'.trim( $image['source_url'] ).'">'.$ret.'</a>';
				}
			}

			if( !empty( $wrapper['style'] ) || !empty( $class ) || !empty( $wrapper['description'] ) ) {
				$ret = '<'.$wrapper['wrapper'].' class="'.( !empty( $wrapper['class'] ) ? $wrapper['class'] : "img-responsive" ).'" style="'.$wrapper['style'].'">'.$ret.( !empty( $wrapper['description'] ) ? '<br />'.$wrapper['description'] : '' ).'</'.$wrapper['wrapper'].'>';
			}
		} else {
			$ret = KernelTools::tra( "Unknown Gallery" );
		}
		$out .= $ret;
	}
  $out .= '</div>';
	}
	return $out;
}

function data_gallery_help() {
	$help =
		'<table class="data help">'
			.'<tr>'
				.'<th>' . KernelTools::tra( "Key" ) . '</th>'
				.'<th>' . KernelTools::tra( "Type" ) . '</th>'
				.'<th>' . KernelTools::tra( "Comments" ) . '</th>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>id</td>'
				.'<td>' . KernelTools::tra( "numeric") . '<br />' . KernelTools::tra("(required)") . '</td>'
				.'<td>' . KernelTools::tra( "gallery id number of Images to display inline.") . KernelTools::tra( "You can use either content_id or id." ).'</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>size</td>'
				.'<td>' . KernelTools::tra( "key-words") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "If the File is an image, you can specify the size of the thumbnail displayed. Possible values are:") . ' <strong>avatar, small, medium, large, original</strong> '
				. KernelTools::tra( "(Default = " ) . '<strong>medium</strong>)</td>'
			.'</tr>'
			.'<tr class="odd">'
				.'<td>nolink</td>'
				.'<td>' . KernelTools::tra( "key-words") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Remove hotlink from element. Used to display fixed copies of an image item.") . '</td>'
			.'</tr>'
			.'<tr class="even">'
				.'<td>num</td>'
				.'<td>' . KernelTools::tra( "key-words") . '<br />' . KernelTools::tra("(optional)") . '</td>'
				.'<td>' . KernelTools::tra( "Number of images to display from the gallery")
				. KernelTools::tra( "(Default = " ) . '<strong>3</strong>)</td>'
			.'</tr>'
		.'</table>'
		. KernelTools::tra( "Example: ") . "{gallery id='13' size='small'}";
	return $help;
}