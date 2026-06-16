{strip}
{if !empty($packageMenuTitle)}<a class="dropdown-toggle" data-toggle="dropdown" href="#"> {tr}{$packageMenuTitle}{/tr} <b class="caret"></b></a>{/if}
<ul class="{$packageMenuClass}">
	{if $gBitUser->hasPermission('p_fisheye_view')}
		<li><a class="item" href="{$smarty.const.FISHEYE_PKG_URL}list_galleries.php">{biticon ipackage="icons" iname="view-list-text" iexplain="List Galleries" ilocation=menu}</a></li>
	{/if}
	{if $gBitUser->hasPermission('p_fisheye_create')}
		<li><a class="item" href="{$smarty.const.FISHEYE_PKG_URL}list_galleries.php?user_id={$gBitUser->mUserId}">{biticon ipackage="icons" iname="image-x-generic" iexplain="My Galleries" ilocation=menu}</a></li>
		<li><a class="item" href="{$smarty.const.FISHEYE_PKG_URL}edit.php">{biticon ipackage="icons" iname="camera-photo" iexplain="Create a Gallery" ilocation=menu}</a></li>
	{/if}
	{if $gBitUser->hasPermission('p_fisheye_upload')}
		<li><a class="item" href="{$smarty.const.FISHEYE_PKG_URL}upload.php">{biticon ipackage="icons" iname="go-up" iexplain="Upload Images" ilocation=menu}</a></li>
	{/if}
	{* if $gBitUser->hasPermission('p_fisheye_view')}
		<li><a class="item" href="{$smarty.const.FISHEYE_PKG_URL}gallery_tree.php">{biticon ipackage="icons" iname="preferences-system" iexplain="growser gallery tree" ilocation=menu} Browse Gallery Tree</a></li>
	{/if *}
	{if $gBitUser->isRegistered() && $gBitSystem->isPackageActive('quota')}
		<li><a class="item" href="{$smarty.const.QUOTA_PKG_URL}">{biticon ipackage="icons" iname="drive-harddisk" iexplain="Usage" ilocation=menu}</a></li>
	{/if}
	{if $gBitUser->isRegistered() && $gBitSystem->isPackageActive('gatekeeper')}
		<li><a class="item" href="{$smarty.const.GATEKEEPER_PKG_URL}">{biticon ipackage="icons" iname="lock" iexplain="Security" ilocation=menu}</a></li>
	{/if}
	{*if $gBitUser->hasPermission('p_fisheye_admin')}
		<li><a class="item" href="{$smarty.const.FISHEYE_PKG_URL}admin/admin_imagegals.php">{tr}Admin Galleries{/tr}</a></li>
	{/if*}
</ul>
{/strip}
