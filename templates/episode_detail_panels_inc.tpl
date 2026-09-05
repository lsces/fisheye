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
			<a class="btn btn-primary" id="episode-play-btn-{$smarty.foreach.episodeDetails.index}" href="{$smarty.const.FISHEYE_PKG_URL}play_episode.php?xref_id={$episode.xref_id}" target="_blank" rel="noopener" onclick="return fisheyeToggleEpisodePlayback(this, this.href);">&#9658; {tr}Play Episode{/tr}</a>
			{* Experimental fallback for content the browser can't play inline (e.g. not yet mpeg2_tidy.php'd) - downloads a one-line .m3u handed off to VLC (or whatever's registered for it) rather than trying inline playback. *}
			<a class="btn btn-secondary" href="{$smarty.const.FISHEYE_PKG_URL}play_episode.php?xref_id={$episode.xref_id}&vlc=1">VLC</a>
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
	//
	// The button itself doubles as the back control - Lester, 2026-09-04: "back button next ...
	// Play episode -> Stop?" - clicking the same (or any other) episode's button while one is
	// playing stops it and restores the poster/facts columns; only one button is ever in "Stop"
	// state at a time, tracked via fisheyePlayingBtn so switching to a different episode's button
	// resets whichever one was previously showing "Stop" back to "Play Episode".
	var fisheyePlayingBtn = null;
	var FISHEYE_PLAY_LABEL = '&#9658; {tr}Play Episode{/tr}';
	var FISHEYE_STOP_LABEL = '&#9632; {tr}Stop{/tr}';

	function fisheyeResetEpisodePlayer() {
		var poster = document.getElementById( 'fisheye-episode-poster' );
		var player = document.getElementById( 'fisheye-episode-player' );
		if( player ) {
			player.pause();
			player.style.display = 'none';
		}
		if( poster ) {
			poster.style.display = '';
		}
		[ 'fisheye-episode-poster-col', 'fisheye-episode-facts-col' ].forEach( function( id ) {
			var col = document.getElementById( id );
			if( col ) {
				col.style.display = '';
			}
		} );
		if( fisheyePlayingBtn ) {
			fisheyePlayingBtn.innerHTML = FISHEYE_PLAY_LABEL;
			fisheyePlayingBtn = null;
		}
	}

	function fisheyeToggleEpisodePlayback( btn, url ) {
		var poster = document.getElementById( 'fisheye-episode-poster' );
		var player = document.getElementById( 'fisheye-episode-player' );
		var source = player ? player.querySelector( 'source' ) : null;
		if( !poster || !player || !source ) {
			return true;
		}
		if( btn === fisheyePlayingBtn ) {
			fisheyeResetEpisodePlayer();
			return false;
		}
		fisheyeResetEpisodePlayer();
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
		btn.innerHTML = FISHEYE_STOP_LABEL;
		fisheyePlayingBtn = btn;
		return false;
	}
</script>
