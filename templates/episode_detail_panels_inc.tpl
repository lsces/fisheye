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
			<a class="btn btn-primary" href="{$smarty.const.FISHEYE_PKG_URL}play_episode.php?xref_id={$episode.xref_id}" target="_blank" rel="noopener" onclick="return fisheyePlayEpisodeInline(this.href);">&#9658; {tr}Play Episode{/tr}</a>
		</p>
	</div>
{/foreach}
<script>
	// Swaps the poster image (fisheye-episode-poster) for the hidden player
	// (fisheye-episode-player, view_season.tpl/view_program_single_season.tpl's own left-hand
	// poster column) rather than navigating away - Lester, 2026-09-04: "player hidden in the
	// left hand half of the top area which is made visible when Play Episode is hit". Falls
	// through to the real play_episode.php page (target="_blank") if JS doesn't run or either
	// element isn't on this page for some reason.
	//
	// view_program_single_season.tpl's left side is two col-md-3 columns (poster + show facts) -
	// fisheye-episode-poster-col/-facts-col, both hidden so the player (col-md-6) gets their
	// combined width instead of being squeezed into just the poster's own column - Lester,
	// 2026-09-04: "it could do with using both of the left panels so it's more like the other
	// player". Neither id exists on view_season.tpl (a single col-md-6 poster column already),
	// so those two lookups just no-op there.
	function fisheyePlayEpisodeInline( url ) {
		var poster = document.getElementById( 'fisheye-episode-poster' );
		var player = document.getElementById( 'fisheye-episode-player' );
		var source = player ? player.querySelector( 'source' ) : null;
		if( !poster || !player || !source ) {
			return true;
		}
		source.src = url;
		player.load();
		player.play();
		poster.style.display = 'none';
		player.style.display = '';
		[ 'fisheye-episode-poster-col', 'fisheye-episode-facts-col' ].forEach( function( id ) {
			var col = document.getElementById( id );
			if( col ) {
				col.style.display = 'none';
			}
		} );
		return false;
	}
</script>
