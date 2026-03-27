{* $Header$ *}
{strip}
{if !empty($gGallery) && $gContent}
	{if !empty($gGallery->mInfo.previous_image_id)}
		<link rel="prev" title="{tr}Previous{/tr}" href="{$gContent->getImageUrl($gGallery->mInfo.previous_image_id)|escape}" />
	{/if}
	{if !empty($gGallery->mInfo.next_image_id)}
		<link rel="next" title="{tr}Next{/tr}" href="{$gContent->getImageUrl($gGallery->mInfo.next_image_id)|escape}" />
	{/if}
{/if}
{if $gBitSystem->isPackageActive( 'rss' ) && $gBitSystem->isFeatureActive( 'fisheye_rss' ) && $gBitSystem->getActivePackage() eq 'fisheye' && $gBitUser->hasPermission( 'p_fisheye_view' )}
	{if !empty($gGallery)}
		{assign var=fisheye_rss_gal_id value=$gGallery->mGalleryId}
	{elseif $gContent}
		{assign var=fisheye_rss_gal_id value=$gContent->mGalleryId}
	{/if}
	<link rel="alternate" type="application/rss+xml" title="{$gBitSystem->getConfig('fisheye_rss_title',"{tr}Image Galleries{/tr} RSS")}" href="{$smarty.const.FISHEYE_PKG_URL}fisheye_rss.php?version={$gBitSystem->getConfig('rssfeed_default_version',0)}&amp;gallery_id={$fisheye_rss_gal_id}" />
{/if}
{/strip}
