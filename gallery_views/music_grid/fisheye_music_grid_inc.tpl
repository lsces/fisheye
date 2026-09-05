{* Same responsive flex grid as film_grid (8 across on a wide monitor, folding to 4 then 2 - see
   that template's own docblock for why this isn't cols_per_page/Bootstrap-col driven). Own
   floaticon set (music_gallery_icons_inc.tpl - Edit/Image Order/Public/Delete, same shape as
   program_gallery_icons_inc.tpl minus a "Load X" action - no load_music.php/load_collection.php
   equivalent exists yet for music, registration is CLI-only via FisheyeAlbum::registerFromDisk()
   for now) - same tidy film_grid/program_grid already had, the generic gallery_icons_inc.tpl's
   own Download Gallery/Add Image actions don't apply to a top-level Music gallery. *}
{strip}
<div class="display fisheye">
	<header>
		{include file="bitpackage:fisheye/music_gallery_icons_inc.tpl"}
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

		<style>
			.music-grid { display: flex; flex-wrap: wrap; margin: 0 -5px; }
			.music-grid-item { box-sizing: border-box; padding: 5px; text-align: center; width: 12.5%; }
			@media (max-width: 1199px) { .music-grid-item { width: 25%; } }
			@media (max-width: 767px) { .music-grid-item { width: 50%; } }
		</style>
		<div class="music-grid">
		{foreach from=$gContent->mItems item=galItem key=itemContentId}
			<div class="music-grid-item">
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
			</div>
		{foreachelse}
			<p class="norecords">{tr}This gallery is empty{/tr}. <a href="{$smarty.const.FISHEYE_PKG_URL}upload.php?gallery_id={$gContent->mGalleryId ?? 0}">Upload pictures!</a></p>
		{/foreach}
		</div>
		{pagination gallery_id=$gContent->mGalleryId ?? 0}
	</div><!-- end .body -->

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gContent->getPreference('allow_comments') eq 'y'}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}
</div><!-- end .fisheye -->
{/strip}
