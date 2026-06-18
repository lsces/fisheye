{strip}
<div class="display fisheye">
	<header>
		{include file="bitpackage:fisheye/gallery_icons_inc.tpl"}
		<h1>{$gContent->getTitle()|escape}</h1>
		{include file="bitpackage:fisheye/gallery_breadcrumb_inc.tpl"}
	</header>

	{if $gContent->mInfo.data && $gContent->getPreference('show_description') ne 'n'}
	<section class="body">
		<p>{$gContent->mInfo.data|escape}</p>
	</section>
	{/if}

	<div class="body col-xs-12">
		{formfeedback success=$fisheyeSuccess error=$fisheyeErrors warning=$fisheyeWarnings}

		{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$gContent->mInfo}

		<div class="fisheye-flow">
		{foreach from=$gContent->mItems item=galItem key=itemContentId}
			<div class="col-lg-3 col-md-4 col-sm-6 col-xs-12"> <!-- Begin Image Cell -->
				<div class="gallery-box">
					<a href="{if empty($galItem->mInfo.display_url)}/fisheye/gallery/{$galItem->mInfo.gallery_id}
							{else}{$galItem->mInfo.display_url}{/if}">
						<div class="gallery-img">
							<img class="thumb img-responsive" src="{$galItem->getThumbnailUri($gContent->getField('thumbnail_size'))}" alt="{$galItem->mInfo.title|escape|default:'image'}" />
						</div>
						<div class="gallery-img-title center">
							<h3>{$galItem->mInfo.title|escape}</h3>
						</div>
					</a>
				</div>
			</div> <!-- End Image Cell -->
		{foreachelse}
			<div class="norecords">{tr}This gallery is empty{/tr}. <a href="{$smarty.const.FISHEYE_PKG_URL}upload.php?gallery_id={$galleryId}">Upload pictures!</a></div>
		{/foreach}
		</div>

	</div>	<!-- end .body -->

	{pagination gallery_id=$galleryId}

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gContent->getPreference('allow_comments') eq 'y'}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}
</div>	<!-- end .fisheye -->
{/strip}
