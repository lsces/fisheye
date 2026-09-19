{* Swappable per-featurette detail blocks - same shape as episode_detail_panels_inc.tpl, one per
   $featurettes entry, all but the first hidden. fisheyeShowGridItem('featurette', idx)
   (featurette_grid_inc.tpl) toggles which is visible.

   Params: $featurettes (array), $playToggleFn (JS function name to call on Play, defaults to
   fisheyeToggleEpisodePlayback - Season/Program's shared #fisheye-episode-player/poster toggle).
   view_film.tpl passes its own fisheyeToggleFeaturette() instead, since a film's page has a
   different player (#liberty-video-player, always showing the film itself rather than a
   hidden-until-clicked poster) - both toggle functions accept the same (btn, url) shape and
   read/restore the button's own dataset.title, so this template doesn't need to know which one
   it's calling. *}
{foreach from=$featurettes item=featurette name=featuretteDetails}
	<div class="featurette-detail" id="featurette-detail-{$smarty.foreach.featuretteDetails.index}"{if !$smarty.foreach.featuretteDetails.first} style="display:none;"{/if}>
		<h3>{$featurette.title|escape}</h3>
		{if $featurette.summary}<p>{$featurette.summary|escape}</p>{/if}
		{if $featurette.durationMs}<dl><dt>{tr}Duration{/tr}</dt><dd>{($featurette.durationMs/1000)|display_duration}</dd></dl>{/if}
		<p class="episode-play-action">
			<a class="btn btn-primary" href="{$smarty.const.FISHEYE_PKG_URL}play_episode.php?xref_id={$featurette.xref_id}" data-title="{$featurette.title|escape}" onclick="return {$playToggleFn|default:'fisheyeToggleEpisodePlayback'}(this, this.href);">&#9658; {tr}Play{/tr}</a>
		</p>
	</div>
{/foreach}
