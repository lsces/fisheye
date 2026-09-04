{strip}
<div class="display fisheye view-film">
	<header>
		{* film_icons_inc.tpl, not gallery_icons_inc.tpl - the gallery one's download/image-order/
		   upload/permissions icons all key off $gContent->mGalleryId, which a plain film content
		   item doesn't have; a film only ever needs the one Edit icon. *}
		{include file="bitpackage:fisheye/film_icons_inc.tpl"}
		{* Own breadcrumb instead of the shared gallery_breadcrumb_inc.tpl - that one hardcodes
		   a pretty-url 'gallery/<id>' link that always lands on the generic gallery view
		   regardless of type, same reasoning view_season.tpl's own title-link already worked
		   around. getBreadcrumbTrail() (FisheyeBase, 2026-09-03) walks the real ancestor chain -
		   "Films" always leads, then any Collection sub-gallery the film actually sits in, each
		   segment's own URL type-correct (film_grid galleries route the same as any other). *}
		<h1>{foreach from=$gContent->getBreadcrumbTrail() item=crumb}<a href="{$crumb.url|escape}">{$crumb.title|escape}</a> - {/foreach}{$gContent->getTitle()|escape}</h1>
	</header>

	<section class="body">
		<div class="row">
			<div class="col-md-5">
				{* player.tpl directly, not video/view.tpl via getMimeTemplate() - skips its
				   trailing mime_meta_inc.tpl include (Uploaded by/Downloads/Last Modified/
				   Hits), which just duplicates/clutters what this page already shows better *}
				{include file="bitpackage:liberty/mime/video/player.tpl" attachment=$gContent->mInfo.image_file}
			</div>
			<div class="col-md-4 film-facts">
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
			{if $gContent->getThumbnailUrl('medium')}
				<div class="col-md-3 film-poster">
					<img class="img-responsive" src="{$gContent->getThumbnailUrl('medium')|escape}" alt="{$gContent->getTitle()|escape}" />
				</div>
			{/if}
		</div>
	</section>

	{include file="bitpackage:fisheye/images_strip_inc.tpl" images=$filmImages stripId="film-images-strip" stripTitle="Images"}

	{if $featurettes|@count}
		{* "Featurettes/ is no different to Season/" (Lester, 2026-09-04) - same play_episode.php
		   link shape view_season.tpl's own episode grid already uses, just a plain list rather
		   than a grid since a film's own Featurettes set is typically small. *}
		<section class="film-featurettes">
			<h2>{tr}Featurettes{/tr}</h2>
			<ul>
				{foreach from=$featurettes item=featurette}
					<li><a href="{$smarty.const.FISHEYE_PKG_URL}play_episode.php?xref_id={$featurette.xref_id}" target="_blank" rel="noopener">{$featurette.title|escape}</a></li>
				{/foreach}
			</ul>
		</section>
	{/if}
</div>
{/strip}
