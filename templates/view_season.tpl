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
			{if $gContent->getThumbnailUri('medium')}
				<div class="col-md-3 film-poster">
					<img class="img-responsive" src="{$gContent->getThumbnailUri('medium')}" alt="{$gContent->getTitle()|escape}" />
				</div>
			{/if}
			<div class="col-md-9">
				{if $externalLinks|@count}
					<p class="film-external-links">
						{foreach from=$externalLinks item=link name=externalLinks}
							<a href="{$link.url|escape}" target="_blank" rel="noopener">{$link.title|escape}</a>{if !$smarty.foreach.externalLinks.last} &middot; {/if}
						{/foreach}
					</p>
				{/if}
				{if $gContent->mInfo.data}
					<p>{$gContent->mInfo.data|escape}</p>
				{/if}
			</div>
		</div>
	</section>

	{* No season-level facts panel - Plex has none of its own (Lester, 2026-09-02: "Plex DOESN'T
	   put anything up on a season page... it's the TV that toggles to display a selected
	   episode's metadata as you select each"). The episode grid below is the real content of the
	   page - clicking a card swaps which already-rendered detail block is shown, same
	   highlight-swaps-the-panel interaction, no per-episode request. Grid is 6-across at md+
	   (col-md-2 - Lester: "a block which I think follows the 6 across style"). *}
	{if $episodes|@count}
		<section class="season-episodes">
			<h2>{tr}Episodes{/tr}</h2>
			<div class="row">
				{foreach from=$episodes item=episode name=episodes}
					<div class="col-md-2 col-sm-4 col-xs-6">
						<div class="gallery-box episode-item{if $smarty.foreach.episodes.first} active{/if}" onclick="fisheyeShowEpisode({$smarty.foreach.episodes.index})" style="cursor:pointer;">
							<div class="gallery-img">
								{if $episode.thumb}
									<img class="img-responsive thumb" src="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$episode.xref_id}" alt="{$episode.title|escape}" />
								{/if}
								<a class="episode-play" href="{$smarty.const.FISHEYE_PKG_URL}play_episode.php?xref_id={$episode.xref_id}" target="_blank" rel="noopener" title="{tr}Play{/tr}" onclick="event.stopPropagation();">&#9658;</a>
							</div>
							<div class="gallery-img-title center">
								<small>{$episode.xorder}. {$episode.title|escape}{if $episode.air_date} &middot; {$episode.air_date|escape}{/if}</small>
							</div>
						</div>
					</div>
					{if $smarty.foreach.episodes.iteration % 2 == 0}<div class="clearfix visible-xs-block"></div>{/if}
					{if $smarty.foreach.episodes.iteration % 3 == 0}<div class="clearfix visible-sm-block"></div>{/if}
					{if $smarty.foreach.episodes.iteration % 6 == 0}<div class="clearfix visible-md-block visible-lg-block"></div>{/if}
				{/foreach}
			</div>

			<div class="row">
				<div class="col-md-12">
					{foreach from=$episodes item=episode name=episodeDetails}
						<div class="episode-detail" id="episode-detail-{$smarty.foreach.episodeDetails.index}"{if !$smarty.foreach.episodeDetails.first} style="display:none;"{/if}>
							<h3>{$episode.title|escape}</h3>
							{if $episode.air_date}<p class="episode-air-date"><small>{$episode.air_date|escape}</small></p>{/if}
							{if $episode.summary}<p>{$episode.summary|escape}</p>{/if}
							<dl>
								{if $episode.directors|@count}<dt>{tr}Director{/tr}{if $episode.directors|@count > 1}s{/if}</dt><dd>{$episode.directors|@implode:", "|escape}</dd>{/if}
								{if $episode.writers|@count}<dt>{tr}Writer{/tr}{if $episode.writers|@count > 1}s{/if}</dt><dd>{$episode.writers|@implode:", "|escape}</dd>{/if}
								{if $episode.stars|@count}<dt>{tr}Starring{/tr}</dt><dd>{$episode.stars|@implode:", "|escape}</dd>{/if}
								{if $episode.content_rating}<dt>{tr}Rating{/tr}</dt><dd>{$episode.content_rating|escape}</dd>{/if}
								{if $episode.durationMs}<dt>{tr}Duration{/tr}</dt><dd>{($episode.durationMs/1000)|display_duration}</dd>{/if}
							</dl>
						</div>
					{/foreach}
				</div>
			</div>
		</section>
		<script>
			function fisheyeShowEpisode( idx ) {
				document.querySelectorAll( '.episode-detail' ).forEach( function( el ) { el.style.display = 'none'; } );
				document.querySelectorAll( '.episode-item' ).forEach( function( el ) { el.classList.remove( 'active' ); } );
				var detail = document.getElementById( 'episode-detail-' + idx );
				if( detail ) { detail.style.display = ''; }
				var items = document.querySelectorAll( '.episode-item' );
				if( items[idx] ) { items[idx].classList.add( 'active' ); }
			}
		</script>
	{/if}

	{include file="bitpackage:fisheye/images_strip_inc.tpl" images=$seasonImages stripId="season-images-strip" stripTitle="Images"}
</div>
{/strip}
