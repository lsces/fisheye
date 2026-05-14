{strip}
{* include file="bitpackage:fisheye/gallery_nav.tpl" *}
<div class="display fisheye container">
	<div class="header col-xs-12">
		{include file="bitpackage:fisheye/gallery_icons_inc.tpl"}
		<h1>{$gContent->getTitle()|escape}</h1>
	</div>

	<div class="body col-xs-12">
		{formfeedback success=$fisheyeSuccess error=$fisheyeErrors warning=$fisheyeWarnings}

		{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$gContent->mInfo}
		{if $gContent->mInfo.data}
			<p>{$gContent->mInfo.data|escape}</p>
		{/if}

		<div class="col-xs-12">
		{counter assign="imageCount" start="0" print=false}
		{assign var="max" value=100}
		{foreach from=$gContent->mItems item=galItem key=itemContentId}
			<div class="col-md-4 col-sm-6 col-xs-12"> <!-- Begin Image Cell -->
				<div class="col-xs-12 gallery-box">
					<a href="{$galItem->getDisplayUrl()|escape}">
						<div class="col-xs-12 gallery-img table-cell">
							<img class="col-xs-12 thumb" src="{$galItem->getThumbnailUri($gContent->getField('thumbnail_size'))}" alt="{$galItem->mInfo.title|escape|default:'image'}" />
						</div>
						<div class="col-xs-12 gallery-img-title table-cell center">
							<h3>{$galItem->mInfo.title|escape}</h3>
						</div>
					</a>
				</div>
			</div> <!-- End Image Cell -->
			{counter}
			{if $imageCount % 2 == 0}<div class="hidden-xs hidden-md hidden-lg clear"></div>{/if}
			{if $imageCount % 3 == 0}<div class="hidden-xs hidden-sm clear"></div>{/if}
		{foreachelse}
			<tr><td class="norecords">{tr}This gallery is empty{/tr}. <a href="{$smarty.const.FISHEYE_PKG_URL}upload.php?gallery_id={$gContent->mGalleryId}">Upload pictures!</a></td></tr>
		{/foreach}
		</div>
		<div class="clear"></div>
	</div>	<!-- end .body -->

	{pagination gallery_id=$gContent->mGalleryId}

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gContent->getPreference('allow_comments') eq 'y'}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}
</div>	<!-- end .fisheye -->
{/strip}
