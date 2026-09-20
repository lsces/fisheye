{* Single level - the gallery (an artist/composer folder) is already known via gallery_id, no
   show->season-style discovery step needed the way load_program.php has. *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1><a href="{$galleryUrl|escape}">{$galleryTitle|escape}</a> - {tr}Load Albums{/tr}</h1>
	</div>

	<div class="body">

		{if !$artistDir}
			<div class="alert alert-warning">
				{tr}No folder found on disk matching this gallery's title{/tr} ("{$galleryTitle|escape}")
				{tr}under any base folder inside Music/.{/tr}
			</div>
		{/if}

		{if $importResult}
			{if $importResult.created}
				<div class="alert alert-success">
					<p>{tr}Albums loaded{/tr}:</p>
					<ul>
						{foreach from=$importResult.created item=row}
							<li><a href="{$smarty.const.FISHEYE_PKG_URL}view_album.php?content_id={$row.content_id}">{$row.folder|escape}</a>{if $row.tracks} - {$row.tracks} {tr}tracks{/tr}{/if}{if !$row.cover} ({tr}no cover art found{/tr}){/if}</li>
						{/foreach}
					</ul>
				</div>
			{/if}
			{if $importResult.boxsets}
				<div class="alert alert-success">
					<p>{tr}Box set collections created{/tr} ({tr}pick which discs to load next{/tr}):</p>
					<ul>
						{foreach from=$importResult.boxsets item=boxset}
							<li>
								<a href="{$boxset.url|escape}">{$boxset.folder|escape}</a>
								{if $boxset.already}({tr}already existed{/tr}){/if}
								- <a href="{$boxset.loadUrl|escape}">{tr}Load its discs now{/tr}</a>
							</li>
						{/foreach}
					</ul>
				</div>
			{/if}
			{if $importResult.errors}
				<div class="alert alert-danger">
					<p>{tr}Failed{/tr}:</p>
					<ul>{foreach from=$importResult.errors item=row}<li>{$row.folder|escape} - {$row.error|escape}</li>{/foreach}</ul>
				</div>
			{/if}
		{/if}

		{if $candidates}
			{form legend="" action="{$smarty.const.FISHEYE_PKG_URL}load_album.php"}
				<input type="hidden" name="gallery_id" value="{$galleryIdParam}" />
				<p>{tr}Showing up to{/tr} {$candidateLimit} {tr}not-yet-loaded albums for{/tr} "{$galleryTitle|escape}":</p>
				<p><label><input type="checkbox" id="loadAlbumToggleAll" checked="checked" /> <strong>{tr}Select All{/tr}</strong></label></p>
				<input type="submit" class="btn btn-primary" name="fImportAlbums" value="{tr}Load Selected Albums{/tr}" />
				<ul>
					{foreach from=$candidates item=album}
						<li>
							<label>
								<input type="checkbox" class="loadAlbumCheckbox" name="selected[]" value="{$album|escape}" checked="checked" />
								{$album|escape}
							</label>
						</li>
					{/foreach}
				</ul>
				<input type="submit" class="btn btn-primary" name="fImportAlbums" value="{tr}Load Selected Albums{/tr}" />
				<script>
					$('#loadAlbumToggleAll').on('change', function() {
						$('.loadAlbumCheckbox').prop('checked', $(this).is(':checked'));
					});
				</script>
			{/form}
		{elseif $artistDir}
			<p>{tr}Nothing to load - every album folder here is already registered.{/tr}</p>
		{/if}

	</div>
</div>
{/strip}
