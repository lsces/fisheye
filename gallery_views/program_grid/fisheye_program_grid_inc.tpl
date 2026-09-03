{* TV Show galleries - same grid rendering as fixed_grid, own floaticon set
   (program_gallery_icons_inc.tpl - Load Seasons instead of Download/Upload, seasons come from
   disk registration via load_program.php not browser upload) and breadcrumb, same framework as
   film_grid. *}
{strip}
<div class="display fisheye">
	<header>
		{include file="bitpackage:fisheye/program_gallery_icons_inc.tpl"}
		{* Own breadcrumb instead of the shared gallery_breadcrumb_inc.tpl - same reasoning as
		   film_grid's own header. getBreadcrumbTrail() walks the real ancestor chain via
		   fisheye_gallery_image_map - "TV Shows" leads for any registered show gallery. *}
		<h1>{foreach from=$gContent->getBreadcrumbTrail() item=crumb}<a href="{$crumb.url|escape}">{$crumb.title|escape}</a> - {/foreach}{$gContent->getTitle()|escape}</h1>
	</header>

	{if $gContent->mInfo.data && $gContent->getPreference('show_description') ne 'n'}
	<section class="body">
		<p>{$gContent->mInfo.data|escape}</p>
	</section>
	{/if}

	<div class="body">
		{formfeedback success=$fisheyeSuccess error=$fisheyeErrors warning=$fisheyeWarnings}

		{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$gContent->mInfo}

		{assign var="cols" value=$gContent->mInfo.cols_per_page|default:4}
		{assign var="tdWidth" value="`100/$cols`"}
		<table class="thumbnailblock" style="width:100%">
		{counter assign="imageCount" start="0" print=false}
		{foreach from=$gContent->mItems item=galItem key=itemContentId}
			{if $imageCount % $cols == 0}
				<tr><!-- Begin Image Row -->
			{/if}

			<td style="width:{$tdWidth}%; vertical-align:top; text-align:center;"><!-- Begin Image Cell -->
				{box class="box `$galItem->mInfo.content_type_guid`" style="margin-left:0;"}
					<a href="{$galItem->getDisplayUrl()|escape}">
						<img class="thumb img-responsive center-block" src="{$galItem->getThumbnailUri($gContent->getField('thumbnail_size'))}" alt="{$galItem->mInfo.title|escape|default:'image'}" />
					</a>
					{if $gBitSystem->isFeatureActive('fisheye_gallery_list_image_titles')}
						<h4>{$galItem->mInfo.title|escape}</h4>
					{/if}
					{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$galItem->mInfo type=mini}
					{if $gBitSystem->isFeatureActive('fisheye_gallery_list_image_descriptions')}
						<p>{$galItem->mInfo.data|truncate:200:"..."|escape}</p>
					{/if}
				{/box}
			</td><!-- End Image Cell -->
			{counter}

			{if $imageCount % $cols == 0}
				</tr><!-- End Image Row -->
			{/if}

		{foreachelse}
			<tr><td class="norecords">{tr}This gallery is empty{/tr}. <a href="{$smarty.const.FISHEYE_PKG_URL}load_program.php?gallery_id={$gContent->mGalleryId ?? 0}">Load seasons!</a></td></tr>
		{/foreach}

		{if $imageCount % $cols != 0}</tr>{/if}
		</table>
		{pagination gallery_id=$gContent->mGalleryId ?? 0}
	</div><!-- end .body -->

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gContent->getPreference('allow_comments') eq 'y'}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}
</div><!-- end .fisheye -->
{/strip}
