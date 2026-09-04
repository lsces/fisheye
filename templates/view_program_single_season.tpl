{* Single-season show: skips the dummy "Season 1" click-through entirely - view_program.php
   dispatches here instead of view_program.tpl when a show has exactly one season, loading that
   season's own episode/image data itself (same shape view_season.php uses). Real FisheyeSeason
   object still underneath, just not a separate page view - Lester, 2026-09-04: "just a different
   tpl when the single season state is identified".

   Layout, per Lester: left side 50/50 (series thumbnail | show summary), episode detail panel
   to the right (col-md-6, swaps per-episode same as view_season.tpl), episodes along the bottom. *}
{strip}
<div class="display fisheye view-program view-program-single-season">
	<header>
		<div class="floaticon">
			{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='icon' serviceHash=$gContent->mInfo}
			{if $gContent->hasUpdatePermission()}
				<a title="{tr}Edit{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit_program.php?content_id={$gContent->mContentId}">{biticon ipackage="icons" iname="edit" iexplain="Edit"}</a>
			{/if}
		</div>
		<h1>{foreach from=$gContent->getBreadcrumbTrail() item=crumb}<a href="{$crumb.url|escape}">{$crumb.title|escape}</a> - {/foreach}{$gContent->getTitle()|escape}</h1>
	</header>

	<section class="body">
		<div class="row">
			{if $gContent->getThumbnailUri('medium')}
				<div class="col-md-3 film-poster">
					<img class="img-responsive" src="{$gContent->getThumbnailUri('medium')}" alt="{$gContent->getTitle()|escape}" />
				</div>
			{/if}
			<div class="col-md-3 film-facts">
				{if $gContent->mInfo.data}
					<p class="film-summary">{$gContent->mInfo.data|escape}</p>
				{/if}
				{if $directors|@count || $stars|@count}
					<p class="film-credits">
						{if $directors|@count}<strong>{tr}Director{/tr}{if $directors|@count > 1}s{/if}:</strong> {$directors|@implode:", "|escape}<br />{/if}
						{if $stars|@count}<strong>{tr}Starring{/tr}:</strong> {$stars|@implode:", "|escape}{/if}
					</p>
				{/if}
				{if $genres|@count}
					<p class="film-genres">
						{foreach from=$genres item=genre}<span class="label label-default">{$genre|escape}</span> {/foreach}
					</p>
				{/if}
				<dl class="film-info">
					{if $contentRating}<dt>{tr}Rating{/tr}</dt><dd>{$contentRating|escape}</dd>{/if}
					{if $durationMs}<dt>{tr}Duration{/tr}</dt><dd>{($durationMs/1000)|display_duration}</dd>{/if}
					{if $writers|@count}<dt>{tr}Writer{/tr}{if $writers|@count > 1}s{/if}</dt><dd>{$writers|@implode:", "|escape}</dd>{/if}
				</dl>
				{if $externalLinks|@count}
					<p class="film-external-links">
						{foreach from=$externalLinks item=link name=externalLinks}
							<a href="{$link.url|escape}" target="_blank" rel="noopener">{$link.title|escape}</a>{if !$smarty.foreach.externalLinks.last} &middot; {/if}
						{/foreach}
					</p>
				{/if}
			</div>
			<div class="col-md-6">
				{include file="bitpackage:fisheye/episode_detail_panels_inc.tpl"}
			</div>
		</div>
	</section>

	{include file="bitpackage:fisheye/episode_grid_inc.tpl"}

	{include file="bitpackage:fisheye/images_strip_inc.tpl" images=$seasonImages stripId="season-images-strip" stripTitle="Images"}
</div>
{/strip}
