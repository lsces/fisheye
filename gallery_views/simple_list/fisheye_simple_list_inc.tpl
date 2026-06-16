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

<div class="body">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th style="width:100px"></th>
				<th>{smartlink ititle=Name isort=title icontrol=$galInfo}</th>
				{if $gBitSystem->isFeatureActive('fisheye_item_list_date') || $gBitSystem->isFeatureActive('fisheye_item_list_creator')}
					<th style="width:10%">{smartlink ititle=Uploaded isort=created iorder=desc idefault=1 icontrol=$galInfo}</th>
				{/if}
				{if $gBitSystem->isFeatureActive('fisheye_item_list_size')}
					<th style="width:8%">{tr}Size{/tr}</th>
				{/if}
				{if $gBitSystem->isFeatureActive('fisheye_item_list_hits')}
					<th style="width:5%">{smartlink ititle=Downloads isort="lch.hits" icontrol=$galInfo}</th>
				{/if}
				<th style="width:15%; text-align:right">{tr}Actions{/tr}</th>
			</tr>
		</thead>
		<tbody>
			{foreach from=$gContent->mItems item=galItem}
			<tr>
				<td style="text-align:center; vertical-align:middle;">
					{if $galItem->mInfo.content_type_guid != 'fisheyegallery'}
						{if $gBitSystem->isFeatureActive('site_fancy_zoom')}
							{if $gContent->hasUpdatePermission() || $gGallery && $gGallery->getPreference('link_original_images')}
								<a href="{$galItem->getDownloadUrl()|escape}">
							{else}
								<a href="{$galItem->mInfo.thumbnail_url.large}">
							{/if}
						{/if}
						<img class="img-thumbnail" src="{$galItem->mInfo.thumbnail_url.small}" alt="{$galItem->getTitle()|escape}" title="{$galItem->getTitle()|escape}" style="max-height:80px; max-width:100%" />
						{if $gBitSystem->isFeatureActive('site_fancy_zoom')}
							</a>
						{/if}
					{else}
						<a href="{$galItem->getDisplayUrl()|escape}">
							<img class="img-thumbnail" src="{$galItem->getThumbnailUri()}" alt="{$galItem->getTitle()|escape|default:'image'}" style="max-height:80px; max-width:100%" />
						</a>
					{/if}
				</td>
				<td>
					<h4><a href="{$galItem->getDisplayUrl()}">{$galItem->getTitle()|escape}</a></h4>
					{if $galItem->mInfo.data}
						<small class="text-muted">{$galItem->mInfo.data|truncate:200|escape}</small>
					{/if}
					{if $gBitSystem->isFeatureActive('fisheye_item_list_name')}
						<br/><small class="text-muted">{$galItem->mInfo.filename} ({$galItem->mInfo.mime_type})</small>
					{/if}
				</td>
				{if $gBitSystem->isFeatureActive('fisheye_item_list_date') || $gBitSystem->isFeatureActive('fisheye_item_list_creator')}
					<td>
						{if $gBitSystem->isFeatureActive('fisheye_item_list_date')}{$galItem->mInfo.created|bit_short_date}{/if}
						{if $gBitSystem->isFeatureActive('fisheye_item_list_creator')}<br/>{tr}by{/tr} {displayname hash=$galItem->mInfo}{/if}
					</td>
				{/if}
				{if $gBitSystem->isFeatureActive('fisheye_item_list_size')}
					<td style="text-align:right;">
						{if $galItem->mInfo.download_url}{$galItem->mInfo.file_size|display_bytes}{/if}
						{if $galItem->mInfo.prefs.duration}{if $galItem->mInfo.download_url} / {/if}{$galItem->mInfo.prefs.duration|display_duration}{/if}
					</td>
				{/if}
				{if $gBitSystem->isFeatureActive('fisheye_item_list_hits')}
					<td style="text-align:right;">{$galItem->mInfo.hits|default:"{tr}none{/tr}"}</td>
				{/if}
				<td class="actionicon">
					{if $galItem->mInfo.content_type_guid != 'fisheyegallery'}
						{if $gBitUser->hasPermission('p_treasury_download_item') && $galItem->mInfo.download_url}
							<a href="{$galItem->mInfo.download_url}">{biticon ipackage="icons" iname="go-down" iexplain="Download File"}</a>
						{/if}
						{if $gBitUser->hasPermission('p_treasury_view_item')}
							<a href="{$galItem->getDisplayUrl()}">{biticon ipackage="icons" iname="folder-open" iexplain="View File"}</a>
						{/if}
						{if $gContent->isOwner($galItem->mInfo) || $gBitUser->isAdmin()}
							<a href="{$smarty.const.FISHEYE_PKG_URL}edit_image.php?content_id={$galItem->mInfo.content_id}&amp;action=edit">{biticon ipackage="icons" iname="edit" iexplain="Edit File"}</a>
							<a href="{$smarty.const.FISHEYE_PKG_URL}edit_image.php?content_id={$galItem->mInfo.content_id}&amp;delete=1">{biticon ipackage="icons" iname="user-trash" iexplain="Remove File"}</a>
						{/if}
					{/if}
				</td>
			</tr>
			{/foreach}
		</tbody>
	</table>
	{pagination gallery_id=$gContent->mGalleryId ?? 0}
</div>

</div>
{/strip}
