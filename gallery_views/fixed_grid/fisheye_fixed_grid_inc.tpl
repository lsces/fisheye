{strip}
{include file="bitpackage:fisheye/gallery_nav.tpl"}
<div class="display fisheye container">
	<div class="header col-xs-12">
		{include file="bitpackage:fisheye/gallery_icons_inc.tpl"}
		<h1>{$gContent->getTitle()|escape}</h1>
	</div>

	{if $gContent->mInfo.data && $gContent->getPreference('show_description') ne 'n'}
	<div class="body">
		<p>{$gContent->mInfo.data|escape}</p>
	</div>
	{/if}

	<div class="body">
		{if $gContent->mGalleryId != 0}
			<table class="thumbnailblock">
			{counter assign="imageCount" start="0" print=false}
			{assign var="max" value=100}
			{assign var="tdWidth" value="4"}
			{foreach from=$gContent->mItems item=galItem key=itemContentId}
				{if $imageCount % 4 == 0}
					<tr > <!-- Begin Image Row -->
				{/if}

				<td style="width:25%; vertical-align:top;"> <!-- Begin Image Cell -->
					{box class="box `$galItem->mInfo.content_type_guid`"}
						<a href="{$galItem->getDisplayUrl()|escape}">
							<img class="thumb" src="{$galItem->getThumbnailUri()}" alt="{$galItem->mInfo.title|escape|default:'image'}" />
						</a>
						{if $gBitSystem->isFeatureActive( 'fisheye_gallery_list_image_titles' )}
							<h4>{$galItem->mInfo.title|escape}</h4>
						{/if}
					{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$galItem->mInfo type=mini}
						{if $gBitSystem->isFeatureActive( 'fisheye_gallery_list_image_descriptions' )}
							<p>{$galItem->mInfo.data|escape}</p>
						{/if}
					{/box}
				</td> <!-- End Image Cell -->
				{counter}

				{if $imageCount % 4 == 0}
					</tr> <!-- End Image Row -->
				{/if}

			{foreachelse}
				<tr><td class="norecords">{tr}This gallery is empty{/tr}. <a href="{$smarty.const.FISHEYE_PKG_URL}upload.php?gallery_id={$gGallery->mGalleryId}">Upload pictures!</a></td></tr>
			{/foreach}

			{if $imageCount % 4 != 0}</tr>{/if}
			</table>
		{else}
			{tr}No gallery for this contact{/tr}. <a href="{$smarty.const.FISHEYE_PKG_URL}create.php?title={$gContent->mInfo.organisation}&contact={$gContent->mInfo.content_id}">Create Contact Gallery!</a>
		{/if}

		{pagination gallery_id=$galleryId}
	</div>	<!-- end .body -->
</div>	<!-- end .fisheye -->
{/strip}	
