{* Same shape as view_film.tpl's Featurettes section - a plain styled list rather than a grid,
   since a track list is inherently ordered/small. Each track plays via play_track.php (xref_id
   only, mirrors play_episode.php), plain link/target=_blank for now - not pinching an in-page
   player the way Featurettes pinch view_film.tpl's video player, since there's no audio player
   on this page yet (a natural follow-up, not built today). *}
{strip}
<div class="display fisheye">
	<header>
		{* Same shape as view_season.tpl/view_program.tpl's own floaticon - this is a single
		   album's item view, not a gallery view, so the generic gallery_icons_inc.tpl (which
		   expects $gContent->mGalleryId) doesn't apply here, just like it didn't for those. *}
		<div class="floaticon">
			{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='icon' serviceHash=$gContent->mInfo}
			{if $gContent->hasUpdatePermission()}
				<a title="{tr}Edit{/tr}" href="{$gContent->getEditUrl()|escape}">{biticon ipackage="icons" iname="edit" iexplain="Edit"}</a>
			{/if}
		</div>
		<h1>{foreach from=$gContent->getBreadcrumbTrail() item=crumb}<a href="{$crumb.url|escape}">{$crumb.title|escape}</a> - {/foreach}{$gContent->getTitle()|escape}</h1>
	</header>

	<div class="body">
		<div class="row">
			<div class="col-md-3">
				<img class="thumb img-responsive" src="{$gContent->getThumbnailUrl('medium')|escape}" alt="{$gContent->getTitle()|escape}" />
			</div>
			<div class="col-md-9">
				{if $artist}<p><strong>{tr}Artist{/tr}:</strong> {$artist|escape}</p>{/if}
				{if $gContent->mInfo.data}<p>{$gContent->mInfo.data|escape}</p>{/if}

				{if $discs|@count}
					<section class="album-tracks">
						<h2>{tr}Tracks{/tr}</h2>
						{foreach from=$discs item=discTracks key=discNum}
							{if $multiDisc}<h3>{tr}Disc{/tr} {$discNum|escape}</h3>{/if}
							<ul>
								{foreach from=$discTracks item=track}
									<li><a class="btn btn-default track-btn" href="{$smarty.const.FISHEYE_PKG_URL}play_track.php?xref_id={$track.xref_id}" target="_blank" rel="noopener">{$track.title|escape}</a></li>
								{/foreach}
							</ul>
						{/foreach}
					</section>
				{/if}
			</div>
		</div>
	</div><!-- end .body -->

	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gContent->getPreference('allow_comments') eq 'y'}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}
</div><!-- end .fisheye -->
{/strip}
