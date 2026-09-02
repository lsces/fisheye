{strip}
<div class="display fisheye view-program">
	<header>
		<div class="floaticon">
			{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='icon' serviceHash=$gContent->mInfo}
			{if $gContent->hasUpdatePermission()}
				<a title="{tr}Edit{/tr}" href="{$smarty.const.FISHEYE_PKG_URL}edit_program.php?content_id={$gContent->mContentId}">{biticon ipackage="icons" iname="edit" iexplain="Edit"}</a>
			{/if}
		</div>
		<h1>{$gContent->getTitle()|escape}</h1>
	</header>

	<section class="body">
		<div class="row">
			{if $gContent->getThumbnailUri('medium')}
				<div class="col-md-3 film-poster">
					<img class="img-responsive" src="{$gContent->getThumbnailUri('medium')}" alt="{$gContent->getTitle()|escape}" />
				</div>
			{/if}
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

	{if $gContent->mItems|@count}
		<section class="film-seasons">
			<h2>{tr}Seasons{/tr}</h2>
			<div class="row">
				{foreach from=$gContent->mItems item=season}
					<div class="col-md-3 col-sm-4 col-xs-6">
						<div class="gallery-box">
							<a href="{$smarty.const.FISHEYE_PKG_URL}view_season.php?content_id={$season->mContentId}">
								{if $season->getThumbnailUri()}
									<div class="gallery-img">
										<img class="img-responsive thumb" src="{$season->getThumbnailUri()}" alt="{$season->mInfo.title|escape}" />
									</div>
								{/if}
								<div class="gallery-img-title center">
									<small>{$season->mInfo.title|escape}</small>
								</div>
							</a>
						</div>
					</div>
				{/foreach}
			</div>
		</section>
	{/if}
</div>
{/strip}
