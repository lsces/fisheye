{* Shared alternate-image strip - originally duplicated verbatim between view_film.tpl and
   view_season.tpl, pulled out here once a second copy existed. Params: $images (array of
   {xref_id}), $stripId (unique per instance on a page - only matters if a page ever embeds more
   than one), $stripTitle (heading text, defaults to "Images").

   Collapsible (Lester, 2026-09-02: "The images strip would benefit from a open/close until we
   replace it with cast images") - a stopgap presentation, not a permanent fixture, so it stays
   out of the way by default rather than always taking up page space. Starts CLOSED (Lester:
   "the image bar needs to come up closed"); plain JS toggle, no library, same pattern as
   fisheyeShowEpisode(). *}
{if $images|@count}
	<section class="film-images-strip">
		<h2 class="images-strip-toggle" onclick="fisheyeToggleStrip('{$stripId|default:'images-strip'}', this)" style="cursor:pointer;">
			<span class="toggle-indicator">&#9654;</span> {$stripTitle|default:"Images"}
		</h2>
		<div class="row" id="{$stripId|default:'images-strip'}" style="display:none;">
			{foreach from=$images item=stripImage name=stripImages}
				<div class="col-md-1 col-sm-4 col-xs-6">
					<div class="gallery-box">
						<a href="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$stripImage.xref_id}" target="_blank" rel="noopener">
							<div class="gallery-img">
								<img class="img-responsive thumb" src="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$stripImage.xref_id}" alt="{$stripTitle|default:"Images"|escape}" />
							</div>
						</a>
					</div>
				</div>
				{if $smarty.foreach.stripImages.iteration % 2 == 0}<div class="clearfix visible-xs-block"></div>{/if}
				{if $smarty.foreach.stripImages.iteration % 3 == 0}<div class="clearfix visible-sm-block"></div>{/if}
				{if $smarty.foreach.stripImages.iteration % 12 == 0}<div class="clearfix visible-md-block visible-lg-block"></div>{/if}
			{/foreach}
		</div>
	</section>
	<script>
		function fisheyeToggleStrip( id, headerEl ) {
			var el = document.getElementById( id );
			if( !el ) { return; }
			var collapsed = el.style.display === 'none';
			el.style.display = collapsed ? '' : 'none';
			var indicator = headerEl.querySelector( '.toggle-indicator' );
			if( indicator ) { indicator.innerHTML = collapsed ? '&#9660;' : '&#9654;'; }
		}
	</script>
{/if}
