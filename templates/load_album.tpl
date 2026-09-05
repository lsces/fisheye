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
				{tr}under either Music Modern/ or Music Classical/.{/tr}
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
				<ul>
					{foreach from=$candidates item=album}
						<li>
							<label>
								<input type="checkbox" name="selected[]" value="{$album|escape}" checked="checked" />
								{$album|escape}
							</label>
						</li>
					{/foreach}
				</ul>
				<input type="submit" class="btn btn-primary" name="fImportAlbums" value="{tr}Load Selected Albums{/tr}" />
			{/form}
		{elseif $artistDir}
			<p>{tr}Nothing to load - every album folder here is already registered.{/tr}</p>
		{/if}

	</div>
</div>
{/strip}
