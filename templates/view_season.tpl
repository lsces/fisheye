{strip}
<div class="display fisheye view-season">
	<header>
		<div class="floaticon">
			{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='icon' serviceHash=$gContent->mInfo}
			{if $gContent->hasUpdatePermission()}
				<a title="{tr}Edit{/tr}" href="{$gContent->getEditUrl()|escape}">{biticon ipackage="icons" iname="edit" iexplain="Edit"}</a>
			{/if}
		</div>
		<h1>{$gContent->getTitle()|escape}</h1>
		{include file="bitpackage:fisheye/gallery_breadcrumb_inc.tpl"}
	</header>

	<section class="body">
		<div class="row">
			<div class="col-md-3">
				{if $gContent->getThumbnailUri()}
					<img class="img-responsive" src="{$gContent->getThumbnailUri()}" alt="{$gContent->getTitle()|escape}" />
				{/if}
			</div>
			<div class="col-md-9 film-facts">
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
		</div>

		{if $gContent->mInfo.data}
			<div class="row">
				<div class="col-md-12 film-summary">
					<p>{$gContent->mInfo.data|escape}</p>
				</div>
			</div>
		{/if}
	</section>

	{if $episodes|@count}
		<section class="season-episodes">
			<h2>{tr}Episodes{/tr}</h2>
			<ol>
				{foreach from=$episodes item=episode}
					<li>{$episode.title|escape}</li>
				{/foreach}
			</ol>
		</section>
	{/if}

	{if $seasonImages|@count}
		<section class="film-images-strip">
			<h2>{tr}Images{/tr}</h2>
			<div class="row">
				{foreach from=$seasonImages item=seasonImage name=seasonImages}
					<div class="col-md-1 col-sm-4 col-xs-6">
						<div class="gallery-box">
							<a href="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$seasonImage.xref_id}" target="_blank" rel="noopener">
								<div class="gallery-img">
									<img class="img-responsive thumb" src="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$seasonImage.xref_id}" alt="{$gContent->getTitle()|escape}" />
								</div>
							</a>
						</div>
					</div>
					{if $smarty.foreach.seasonImages.iteration % 2 == 0}<div class="clearfix visible-xs-block"></div>{/if}
					{if $smarty.foreach.seasonImages.iteration % 3 == 0}<div class="clearfix visible-sm-block"></div>{/if}
					{if $smarty.foreach.seasonImages.iteration % 12 == 0}<div class="clearfix visible-md-block visible-lg-block"></div>{/if}
				{/foreach}
			</div>
		</section>
	{/if}
</div>
{/strip}
