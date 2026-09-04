{* Episode picker grid - purely a picker, clicking a card swaps which detail block (see
   episode_detail_panels_inc.tpl) is shown, no per-episode request. 6-across at md+ (col-md-2).
   Shared between view_season.tpl and view_program_single_season.tpl (2026-09-04) - factored out
   rather than duplicated. *}
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
