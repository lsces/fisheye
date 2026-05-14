<?php
use \Bitweaver\Fisheye\FisheyeGallery;
global $gBitSystem, $gBitUser, $gBitSmarty, $gBitThemes;

$pRegisterHash = [
	'package_name' => 'fisheye',
	'package_path' => dirname( dirname( __FILE__ ) ).'/',
	'homeable' => true,
];
// fix to quieten down VS Code which can't see the dynamic creation of these ...
define( 'FISHEYE_PKG_NAME', $pRegisterHash['package_name'] );
define( 'FISHEYE_PKG_URL', BIT_ROOT_URL . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'FISHEYE_PKG_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'FISHEYE_PKG_INCLUDE_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/');
define( 'FISHEYE_PKG_CLASS_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/classes/');
define( 'FISHEYE_PKG_ADMIN_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/admin/');

$gBitSystem->registerPackage( $pRegisterHash );

if( $gBitSystem->isPackageActive( 'fisheye' ) ) { // && $gBitUser->hasPermission( 'p_fisheye_view' )) {

	// Default Preferences Defines
	define ( 'FISHEYE_DEFAULT_ROWS_PER_PAGE', 5 );
	define ( 'FISHEYE_DEFAULT_COLS_PER_PAGE', 2 );
	define ( 'FISHEYE_DEFAULT_THUMBNAIL_SIZE', 'large' );

	$menuHash = [
		'package_name'  => FISHEYE_PKG_NAME,
		'index_url'     => FISHEYE_PKG_URL.'index.php',
		'menu_template' => 'bitpackage:fisheye/menu_fisheye.tpl',
	];
	$gBitSystem->registerAppMenu( $menuHash );

	define( 'LIBERTY_SERVICE_PHOTOSHARING', 'photosharing');

	$gLibertySystem->registerService( LIBERTY_SERVICE_PHOTOSHARING, FISHEYE_PKG_NAME, [
		'users_expunge_function' => 'fisheye_expunge_user',
	] );

	function fisheye_expunge_user( $pObject ) {
		global $gBitDb;
		if( !empty( $pObject->mUserId ) ) {
			$query = "SELECT fg.`content_id` FROM `".BIT_DB_PREFIX."fisheye_gallery` fg INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON(fg.`content_id`=lc.`content_id`) WHERE lc.`user_id`=?";
			if( $galleries = $gBitDb->getCol( $query, [ $pObject->mUserId ] ) ) {
				foreach( $galleries as $contentId ) {
					$delGallery = new FisheyeGallery( null, $contentId );
					if( $delGallery->load() ) {
						$delGallery->expunge();
					}
				}
			}
		}
	}
}