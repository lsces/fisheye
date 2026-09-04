{strip}
<div class="display fisheye">
	{if empty($liberty_preview)}
	<header>
		{* image_icons_inc.tpl, not gallery_icons_inc.tpl - every icon there (Download Gallery,
		   Image Order, Add Image, Delete Gallery) keys off $gContent->mGalleryId, which is
		   meaningless on this page: $gContent here is the image itself, not the gallery it sits
		   in. Found live 2026-09-04 testing a film's delete: this page's own "Delete" icon was
		   silently deleting the gallery the item belonged to, not the item - same
		   view_film.tpl-vs-gallery_icons_inc.tpl mismatch that page's own comment already flagged
		   when it was built, just never fixed here since this was the only other page still
		   including it. *}
		{include file="bitpackage:fisheye/image_icons_inc.tpl"}
		<h1>{$gContent->getTitle()|default:$gContent->mInfo.filename|escape}</h1>
		{include file="bitpackage:fisheye/gallery_breadcrumb_inc.tpl"}
	</header>
	{/if}

	{formfeedback hash=$feedback}
	<section class="body">
		{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$gContent->mInfo}
		<div class="image">
			{include file=$gLibertySystem->getMimeTemplate('view',$gContent->mInfo.attachment_plugin_guid) attachment=$gContent->mInfo.image_file}
			{if $gBitSystem->isFeatureActive('fisheye_image_list_description') && $gContent->mInfo.data ne ''}
				<p class="description">{$gContent->mInfo.parsed_data|truncate:250:"..."}</p>
			{/if}
		</div>
		{if empty($liberty_preview)}
			{include file="bitpackage:fisheye/gallery_nav.tpl"}
		{/if}
	</section>

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gGallery && $gGallery->isCommentable()}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}

</div>
{/strip}
