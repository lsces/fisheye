{* Group-tab override for the 'images' xref group (fisheyefilm/fisheyeseason/fisheyeprogram all
   share this one - liberty_xref_group.template set to 'images' for their three group rows).
   Identical to liberty's own generic list_xref.tpl except the "Add record" link below, which the
   generic add_xref.php/add_xref.tpl flow has no file upload for at all - Lester, 2026-09-03: "the
   add button on the_image tab just uses the generic add so you have to create a new image line,
   and then go and edit it". add_image_xref.php uploads straight into a new row in one step. *}
{assign var=xrefAllowEdit value=$allow_edit|default:true}
{assign var=tabTitle value=$xrefGroup->mTitle}
{assign var=isHistory value=($xrefGroup->mXGroup eq 'history')}
{jstab title="`$tabTitle` ({$xrefGroup->mXrefs|@count})"}
{legend legend=$tabTitle}
<div class="form-group table-responsive">
	<table class="table">
		<thead>
			<tr>
				<th>{tr}Type{/tr}</th>
				<th>{tr}Value{/tr}</th>
				<th>{tr}Notes{/tr}</th>
				{if $xrefAllowEdit}
					{if $isHistory}<th>{tr}Ended{/tr}</th>{else}<th>{tr}Started{/tr}</th>{/if}
					<th>{tr}Updated{/tr}</th>
					<th>{tr}Edit{/tr}</th>
				{/if}
			</tr>
		</thead>
		<tbody>
			{if $xrefGroup->mXrefs}
				{foreach $xrefGroup->mXrefs as $xrefInfo}
					<tr class="{cycle values="even,odd"}">
						{include file=$gContent->getXrefRecordTemplate($xrefInfo.template)}
					</tr>
				{/foreach}
			{else}
				<tr class="norecords">
					<td colspan="{if $xrefAllowEdit}6{else}3{/if}">{tr}No {$tabTitle} records found{/tr}</td>
				</tr>
			{/if}
		</tbody>
	</table>
</div>
{if $allow_add && $gContent->isValid() && $gContent->hasUpdatePermission() && !$isHistory}
	<div>
		{if $gContent->supportsAddImage()}
			<a href="{$smarty.const.FISHEYE_PKG_URL}add_image_xref.php?content_id={$gContent->mInfo.content_id}">{biticon ipackage="icons" iname="list-add" iexplain="Add Image"} {tr}Add Image{/tr}</a>
		{else}
			{smartlink ititle="Add record" ipackage="liberty" ifile="add_xref.php" biticon="list-add" content_id=$gContent->mInfo.content_id group=$xrefGroup->mSortOrder}
		{/if}
		{if $gContent->canGrabVideoFrame()}
			&nbsp;
			<a href="{$gContent->getEditUrl()|cat:'&fGrabFrame=1'|escape}">{biticon ipackage="icons" iname="image-x-generic" iexplain="Grab Thumbnail"} {tr}Grab Thumbnail from Video{/tr}</a>
		{/if}
	</div>
{/if}
{/legend}
{/jstab}
