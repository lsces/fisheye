{strip}
{if empty($liberty_preview)}
	{include file="bitpackage:fisheye/gallery_nav.tpl"}
{/if}

<div class="display fisheye">
	{if empty($liberty_preview)}
		<div class="floaticon">
			{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='icon' serviceHash=$gContent->mInfo}
			{if $gContent->hasUpdatePermission()}
				<a title="{tr}Refresh{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}image/{$gContent->mImageId}?rethumb=1">{biticon ipackage="icons" iname="view-refresh" iexplain="Refresh"}</a>
				<a title="{tr}Edit{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit_image.php?image_id={$gContent->mImageId}">{biticon ipackage="icons" iname="document-properties" iexplain="Edit Image"}</a>
				<a title="{tr}Delete{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit_image.php?image_id={$gContent->mImageId}&amp;delete=1">{biticon ipackage="icons" iname="edit-delete" iexplain="Delete Image"}</a>
			{/if}
		</div>
	{/if}

	{formfeedback hash=$feedback}
	<div class="header">
		<h1>{$gContent->getTitle()|default:$gContent->mInfo.filename|escape}</h1>
	</div>

	<div class="body">
		{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='body' serviceHash=$gContent->mInfo}
		<div class="image">
			{include file=$gLibertySystem->getMimeTemplate('view',$gContent->mInfo.attachment_plugin_guid) attachment=$gContent->mInfo.image_file}
			{if $gBitSystem->isFeatureActive('fisheye_image_list_description') && $gContent->mInfo.data ne ''}
				<p class="description">{$gContent->mInfo.parsed_data|truncate:250:"..."}</p>
			{/if}
			</div>
	</div>	<!-- end .body -->

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gGallery && $gGallery->isCommentable()}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}

</div>	<!-- end .fisheye -->
{/strip}
