{strip}
{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='nav' serviceHash=$gContent->mInfo}

{if $gGallery}
	<div class="navigation">
		<span class="pull-left">
			{if !empty( $gGallery->mInfo.previous_image_id )}
				<a href="{$gContent->getImageUrl($gGallery->mInfo.previous_image_id)|escape}">
					{if $gBitSystem->isFeatureActive( 'gallerybar_use_icons' )}
						{biticon ipackage="icons" iname="go-previous" iexplain=previous}
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
						{biticon ipackage="icons" iname="go-next" iexplain=next}
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
{/strip}
