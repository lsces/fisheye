<div class="floaticon">
	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='icon' serviceHash=$gContent->mInfo}
	{if $gContent->hasUpdatePermission()}
		{if $gContent->getTitle() eq 'Music'}
			{* Top-level only, same gating as film_gallery_icons_inc.tpl's own "Load Collections" -
			   load_music_collection.php doesn't exist yet (parked, see FisheyeAlbum.php's own
			   docblock for the load_collection/load_discography plan) - button added ahead of the
			   script itself so the UI is ready once it's built. *}
			<a title="{tr}Add Music Collection{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}load_music_collection.php">{biticon ipackage="icons" iname="folder-new" iexplain="Add Music Collection"}</a>
		{else}
			{* Any collection gallery below the top level - registers one album (a single
			   FisheyeAlbum::registerFromDisk() folder) into this gallery, as opposed to the
			   top-level button above which will bulk-scan for whole collections at once.
			   load_album.php doesn't exist yet either - same "button first" reasoning. *}
			<a title="{tr}Load Album{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}load_album.php?gallery_id={$gContent->mGalleryId}">{biticon ipackage="icons" iname="folder-open" iexplain="Load Album"}</a>
		{/if}
		<a title="{tr}Edit{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit.php?gallery_id={$gContent->mGalleryId}">{biticon ipackage="icons" iname="edit"  iexplain="Edit"}</a>
		<a title="{tr}Image Order{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}image_order.php?gallery_id={$gContent->mGalleryId}">{biticon ipackage="icons" iname="view-sort-ascending" iexplain="Image Order"}</a>
	{/if}
	{if $gContent->getPreference('is_public')}
		{biticon ipackage="icons" iname="emblem-important"  iexplain="Public"}
	{/if}
	{if $gContent->hasAdminPermission()}
		<a title="{tr}User Permissions{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit.php?gallery_id={$gContent->mGalleryId}&amp;delete=1">{biticon ipackage="icons" iname="user-trash" iexplain="Delete Gallery"}</a>
	{/if}
</div>
