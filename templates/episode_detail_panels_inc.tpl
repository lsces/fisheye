{* Swappable per-episode detail blocks - one per $episodes entry, all but the first hidden.
   fisheyeShowEpisode() (episode_grid_inc.tpl) toggles which is visible by index. Shared between
   view_season.tpl and view_program_single_season.tpl (2026-09-04) - factored out rather than
   duplicated, no wrapping column div here so each caller supplies its own. *}
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
		<p class="episode-play-action">
			<a class="btn btn-primary" href="{$smarty.const.FISHEYE_PKG_URL}play_episode.php?xref_id={$episode.xref_id}" target="_blank" rel="noopener">&#9658; {tr}Play Episode{/tr}</a>
		</p>
	</div>
{/foreach}
