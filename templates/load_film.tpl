{* Lists un-registered films under Films/ (capped batch), with checkboxes to import a selection *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>{tr}Load Films{/tr}</h1>
	</div>

	<div class="body">

		<p>{tr}Films folder:{/tr} <code>{$filmsDir|default:'(storage root not configured)'|escape}</code></p>

		{if $result}
			{if $result.imported}
				<div class="alert alert-success">
					<p>{tr}Imported{/tr} {$result.imported|@count} {tr}in{/tr} {$result.total_seconds}s:</p>
					<ul>
						{foreach from=$result.imported item=row}
							<li>
								<a href="{$smarty.const.FISHEYE_PKG_URL}view_film.php?content_id={$row.content_id}">{$row.path|escape}</a>
								{if $row.plex.matched}({tr}Plex metadata found{/tr}){else}({tr}no Plex match{/tr}){/if}
								{if $result.fetch_images}
									- {if $row.images.matched}{tr}images:{/tr} {$row.images.items|@count}{else}({tr}no images fetched{/tr}){/if}
								{/if}
								[{$row.seconds}s]
							</li>
						{/foreach}
					</ul>
				</div>
			{/if}
			{if $result.already}
				<div class="alert alert-warning">
					<p>{tr}Already registered (skipped){/tr}:</p>
					<ul>{foreach from=$result.already item=row}<li>{$row.path|escape}</li>{/foreach}</ul>
				</div>
			{/if}
			{if $result.errors}
				<div class="alert alert-danger">
					<p>{tr}Failed{/tr}:</p>
					<ul>{foreach from=$result.errors item=row}<li>{$row.path|escape} - {$row.error|escape}</li>{/foreach}</ul>
				</div>
			{/if}
		{/if}

		{if $candidates}
			{form legend="" action="{$smarty.const.FISHEYE_PKG_URL}load_film.php"}
				<p>{tr}Showing up to{/tr} {$candidateLimit} {tr}not-yet-loaded films.{/tr}</p>
				<div class="form-group">
					<label><input type="checkbox" name="fetch_images" value="1" /> {tr}Also fetch Plex poster/backdrop images per film (slower - only text metadata is fetched otherwise){/tr}</label>
				</div>
				<table class="table">
					<thead><tr>
						<th><input type="checkbox" onclick="var cb=this.form.elements['selected[]']; if(cb.length===undefined)cb=[cb]; for(var i=0;i<cb.length;i++)cb[i].checked=this.checked;" /></th>
						<th>{tr}Title{/tr}</th>
						<th>{tr}File{/tr}</th>
					</tr></thead>
					<tbody>
						{foreach from=$candidates item=film}
							<tr>
								<td><input type="checkbox" name="selected[]" value="{$film.relative_path|escape}" checked="checked" /></td>
								<td>{$film.title|escape}</td>
								<td><code>{$film.relative_path|escape}</code></td>
							</tr>
						{/foreach}
					</tbody>
				</table>
				<input type="submit" class="btn btn-primary" name="fImport" value="{tr}Import Selected{/tr}" />
			{/form}
		{elseif $filmsDir}
			<p>{tr}Nothing to load - every film under the Films folder is already registered.{/tr}</p>
		{/if}

	</div>
</div>
{/strip}
