{* Lists this gallery's own already-registered albums that are MB-matched (have an 'mbid' xref)
   but have no linked Discogs release yet - see fetch_discogs.php's own docblock for why this is
   scoped to one gallery's direct items only, same as load_album.php/load_video.php. *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1><a href="{$galleryUrl|escape}">{$galleryTitle|escape}</a> - {tr}Fetch Discogs Links{/tr}</h1>
	</div>

	<div class="body">

		{if $result}
			{if $result.found}
				<div class="alert alert-success">
					<p>{tr}Discogs links found{/tr}:</p>
					<ul>{foreach from=$result.found item=row}<li>{$row.title|escape} - <a href="{$row.url|escape}" target="_blank" rel="noopener">{$row.url|escape}</a></li>{/foreach}</ul>
				</div>
			{/if}
			{if $result.none}
				<div class="alert alert-warning">
					<p>{tr}No Discogs link on MusicBrainz for{/tr}:</p>
					<ul>{foreach from=$result.none item=row}<li>{$row.title|escape}</li>{/foreach}</ul>
				</div>
			{/if}
			{if $result.errors}
				<div class="alert alert-danger">
					<p>{tr}Failed{/tr}:</p>
					<ul>{foreach from=$result.errors item=row}<li>{$row.title|escape} - {$row.error|escape}</li>{/foreach}</ul>
				</div>
			{/if}
		{/if}

		{if $candidates}
			{form legend="" action="{$smarty.const.FISHEYE_PKG_URL}fetch_discogs.php"}
				<input type="hidden" name="gallery_id" value="{$galleryIdParam}" />
				<p>{tr}Showing up to{/tr} {$candidateLimit} {tr}MB-matched albums here with no Discogs link yet{/tr} - {tr}one MusicBrainz API call per album, so this may take a few seconds per item selected{/tr}.</p>
				<p><label><input type="checkbox" id="fetchDiscogsToggleAll" checked="checked" /> <strong>{tr}Select All{/tr}</strong></label></p>
				<input type="submit" class="btn btn-primary" name="fFetch" value="{tr}Fetch Discogs Links{/tr}" />
				<ul>
					{foreach from=$candidates item=album}
						<li>
							<label>
								<input type="checkbox" class="fetchDiscogsCheckbox" name="selected[]" value="{$album.content_id}" checked="checked" />
								{$album.title|escape}
							</label>
						</li>
					{/foreach}
				</ul>
				<input type="submit" class="btn btn-primary" name="fFetch" value="{tr}Fetch Discogs Links{/tr}" />
				<script>
					$('#fetchDiscogsToggleAll').on('change', function() {
						$('.fetchDiscogsCheckbox').prop('checked', $(this).is(':checked'));
					});
				</script>
			{/form}
		{else}
			<p>{tr}Nothing to fetch - every MB-matched album here either already has a Discogs link or has none available.{/tr}</p>
		{/if}

	</div>
</div>
{/strip}
