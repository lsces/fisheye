<div class="floaticon">
	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='icon' serviceHash=$gContent->mInfo}
	{if $gContent->hasUpdatePermission()}
		<a title="{tr}Edit{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit_image.php?image_id={$gContent->mImageId}">{biticon ipackage="icons" iname="edit" iexplain="Edit"}</a>
	{/if}
	{if $gContent->hasAdminPermission()}
		<a title="{tr}Delete Image{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit_image.php?image_id={$gContent->mImageId}&amp;delete=1">{biticon ipackage="icons" iname="user-trash" iexplain="Delete Image"}</a>
	{/if}
</div>
