{strip}
<div class="display fisheye view-film">
	<header>
		{* film_icons_inc.tpl, not gallery_icons_inc.tpl - the gallery one's download/image-order/
		   upload/permissions icons all key off $gContent->mGalleryId, which a plain film content
		   item doesn't have; a film only ever needs the one Edit icon. *}
		{include file="bitpackage:fisheye/film_icons_inc.tpl"}
		<h1>{$gContent->getTitle()|escape}</h1>
		{include file="bitpackage:fisheye/gallery_breadcrumb_inc.tpl"}
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

		{if $gContent->mInfo.data}
			<div class="row">
				<div class="col-md-12 film-summary">
					<p>{$gContent->mInfo.data|escape}</p>
				</div>
			</div>
		{/if}
	</section>

	{if $filmImages|@count}
		<section class="film-images-strip">
			<h2>{tr}Images{/tr}</h2>
			<div class="row">
				{foreach from=$filmImages item=filmImage name=filmImages}
					<div class="col-md-1 col-sm-4 col-xs-6">
						<div class="gallery-box">
							<a href="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$filmImage.xref_id}" target="_blank" rel="noopener">
								<div class="gallery-img">
									<img class="img-responsive thumb" src="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$filmImage.xref_id}" alt="{$gContent->getTitle()|escape}" />
								</div>
							</a>
						</div>
					</div>
					{* posters (portrait) and backdrops (landscape) mixed in the same row leave rows of
					   uneven height, so a plain Bootstrap float grid tucks the next item up under a
					   shorter neighbour instead of starting a clean new row - one clearfix per
					   breakpoint's own column count (2/3/12 - col-md-1 is Bootstrap's narrowest, 12
					   across, per Lester's "go up to 12 images across on hr monitor") forces the wrap
					   at the right point. *}
					{if $smarty.foreach.filmImages.iteration % 2 == 0}<div class="clearfix visible-xs-block"></div>{/if}
					{if $smarty.foreach.filmImages.iteration % 3 == 0}<div class="clearfix visible-sm-block"></div>{/if}
					{if $smarty.foreach.filmImages.iteration % 12 == 0}<div class="clearfix visible-md-block visible-lg-block"></div>{/if}
				{/foreach}
			</div>
		</section>
	{/if}
</div>
{/strip}
