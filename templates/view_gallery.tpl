{assign var=galLayout value=$gContent->getLayout()}
{include file="`$gContent->getGalleryViewsPath()`gallery_views/`$galLayout`/fisheye_`$galLayout`_inc.tpl" }
