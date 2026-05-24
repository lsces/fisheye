{strip}
{include file="bitpackage:fisheye/gallery_nav.tpl"}
<div class="display fisheye">
	<div class="header">
		{include file="bitpackage:fisheye/gallery_icons_inc.tpl"}
		<h1>{$gContent->getTitle()|escape}</h1>
	</div>

	{if $gContent->mInfo.data && $gContent->getPreference('show_description') ne 'n'}
	<div class="body">
		<p>{$gContent->mInfo.data|escape}</p>
	</div>
	{/if}

	<div class="body">
		{formfeedback success=$fisheyeSuccess error=$fisheyeErrors warning=$fisheyeWarnings}

		{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$gContent->mInfo}
		{if $gContent->mInfo.data}
			<p>{$gContent->mInfo.data|escape}</p>
		{/if}

	<table class="thumbnailblock">
		{counter assign="imageCount" start="0" print=false}
		{assign var="max" value=100}
		{assign var="tdWidth" value="`$max/$gContent->mInfo.cols_per_page`"}
		{foreach from=$gContent->mItems item=galItem key=itemContentId}
			{if $imageCount % $gContent->mInfo.cols_per_page == 0}
				<tr > <!-- Begin Image Row -->
			{/if}

			<td style="width:{$tdWidth}%; vertical-align:top;"> <!-- Begin Image Cell -->
				{box class="box `$galItem->mInfo.content_type_guid`"}
					<a href="{$galItem->getDisplayUrl()|escape}">
						<img class="thumb" src="{$galItem->getThumbnailUri()}" alt="{$galItem->mInfo.title|escape|default:'image'}" />
					</a>
					{if $gBitSystem->isFeatureActive( 'fisheye_gallery_list_image_titles' )}
						<h2>{$galItem->mInfo.title|escape}</h2>
					{/if}
				{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$galItem->mInfo type=mini}
					{if $gBitSystem->isFeatureActive( 'fisheye_gallery_list_image_descriptions' )}
						<p>{$galItem->mInfo.data|escape}</p>
					{/if}
				{/box}
			</td> <!-- End Image Cell -->
			{counter}

			{if $imageCount % $gContent->mInfo.cols_per_page == 0}
				</tr> <!-- End Image Row -->
			{/if}

		{foreachelse}
			<tr><td class="norecords">{tr}This gallery is empty{/tr}. <a href="{$smarty.const.FISHEYE_PKG_URL}upload.php?gallery_id={$galleryId}">{tr}Upload pictures{/tr}!</a></td></tr>
		{/foreach}

		{if $imageCount % $gContent->mInfo.cols_per_page != 0}</tr>{/if}
	</table>

	</div>	<!-- end .body -->

	{pagination gallery_id=$galleryId gallery_path=$gContent->mGalleryPath}

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gContent->getPreference('allow_comments') eq 'y'}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}
</div>	<!-- end .fisheye -->
{/strip}
	
