{strip}
<div class="gallerybar">
	<nav>
		{assign var=breadCrumbs value=$gContent->getBreadcrumbLinks(1)}
		<ol class="breadcrumb">
			{if $gContent->hasUpdatePermission()}
				{if !empty($gGallery->mInfo)}
					{assign var=creatorInfo value=$gGallery->mInfo}
				{else}
					{assign var=creatorInfo value=$gContent->mInfo}
				{/if}
				<li>{displayname user=$creatorInfo.creator_user user_id=$creatorInfo.creator_user_id|default:0 real_name=$creatorInfo.creator_real_name} :: <a href="{$smarty.const.FISHEYE_PKG_URL}?user_id={$creatorInfo.user_id}">Galleries</a></li>
			{/if}
			{if $breadCrumbs}
				{foreach from=$breadCrumbs item=breadTitle key=breadId}
					{if $breadId==$gContent->mGalleryId}<li class="active">{$breadTitle}</li>
					{else}<li><a href="{$smarty.const.FISHEYE_PKG_URL}gallery/{$breadId}">{$breadTitle}</a></li>{/if}
				{/foreach}
			{/if}
		</ol>
	</nav>

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='nav' serviceHash=$gContent->mInfo}

	{if $gGallery}
		<div class="navigation">
			<span class="pull-left">
				{if !empty( $gGallery->mInfo.previous_image_id )}
					<a href="{$gContent->getImageUrl($gGallery->mInfo.previous_image_id)|escape}">
						{if $gBitSystem->isFeatureActive( 'gallerybar_use_icons' )}
							{booticon iname="fa-arrow-left"  ipackage="icons"  iexplain=previous}
						{else}
							&laquo;&nbsp;{tr}previous{/tr}
						{/if}
						{if $gBitSystem->isFeatureActive( 'gallery_bar_use_thumbnails' )}
							<br />
							<img src="{$gGallery->mInfo.previous_image_avatar}" />
						{/if}
					</a>
				{else}&nbsp;{/if}
			</span>

			<span class="pull-right">
				{if !empty( $gGallery->mInfo.next_image_id )}
					<a href="{$gContent->getImageUrl($gGallery->mInfo.next_image_id)|escape}">
						{if $gBitSystem->isFeatureActive( 'gallerybar_use_icons' )}
							{booticon iname="fa-arrow-right" iexplain=next}
						{else}
							{tr}next{/tr}&nbsp;&raquo;
						{/if}
						{if $gBitSystem->isFeatureActive( 'gallery_bar_use_thumbnails' )}
							<br />
							<img src="{$gGallery->mInfo.next_image_avatar}" />
						{/if}
					</a>
				{else}&nbsp;{/if}
			</span>
		</div><!-- end .navigation -->
	{/if}

	<div class="clear"></div>
</div><!-- end .gallerybar -->
{/strip}
