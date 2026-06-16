<small>
	{if $gContent->hasUpdatePermission()}
		{if !empty($gGallery->mInfo)}
			{assign var=creatorInfo value=$gGallery->mInfo}
		{else}
			{assign var=creatorInfo value=$gContent->mInfo}
		{/if}
		<a href="{$smarty.const.FISHEYE_PKG_URL}?user_id={$creatorInfo.user_id}">{displayname user=$creatorInfo.creator_user user_id=$creatorInfo.creator_user_id|default:0 real_name=$creatorInfo.creator_real_name}</a> &rsaquo;
	{/if}
	{assign var=breadCrumbs value=$gContent->getBreadcrumbLinks(1)}
	{foreach from=$breadCrumbs item=breadTitle key=breadId}
		{if $breadId == $gContent->mGalleryId}
			{$breadTitle|escape}
		{else}
			<a href="{$smarty.const.FISHEYE_PKG_URL}gallery/{$breadId}">{$breadTitle|escape}</a> &rsaquo;
		{/if}
	{/foreach}
</small>
