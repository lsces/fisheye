{* Two levels: no show yet = pick a show folder to register; ?show=Name = pick season folders
   under that (already-registered) show. Plain lists, not load_film.php's big table - a show's
   season count is small enough that it doesn't need one. *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1>
			{tr}Load TV Shows{/tr}
			{if $scopeShow}- <a href="{$smarty.const.FISHEYE_PKG_URL}load_program.php">{tr}Shows{/tr}</a> - {$scopeShow.title|escape}{/if}
		</h1>
	</div>

	<div class="body">

		{if $showResult.error}
			<div class="alert alert-danger">{$showResult.error|escape}</div>
		{elseif $showResult.created}
			<div class="alert alert-success">
				{tr}Registered show{/tr} "{$scopeShow.title|escape}"
				{if $showResult.plex.matched}({tr}Plex metadata found{/tr}){else}({tr}no Plex match{/tr}){/if}
			</div>
		{/if}

		{if $seasonResult}
			{if $seasonResult.created}
				<div class="alert alert-success">
					<p>{tr}Seasons loaded{/tr}:</p>
					<ul>
						{foreach from=$seasonResult.created item=row}
							<li>{$row.folder|escape} - {$row.episodes.items|@count} {tr}episodes from Plex{/tr}</li>
						{/foreach}
					</ul>
				</div>
			{/if}
			{if $seasonResult.errors}
				<div class="alert alert-danger">
					<p>{tr}Failed{/tr}:</p>
					<ul>{foreach from=$seasonResult.errors item=row}<li>{$row.folder|escape} - {$row.error|escape}</li>{/foreach}</ul>
				</div>
			{/if}
		{/if}

		{if !$scopeShow && $candidates}
			<p>{tr}Showing up to{/tr} {$candidateLimit} {tr}not-yet-loaded shows.{/tr}</p>
			<ul>
				{foreach from=$candidates item=show}
					<li><a href="{$smarty.const.FISHEYE_PKG_URL}load_program.php?show={$show|escape:'url'}">{$show|escape}</a></li>
				{/foreach}
			</ul>
		{elseif !$scopeShow}
			<p>{tr}Nothing to load - every show folder is already registered.{/tr}</p>
		{/if}

		{if $scopeShow && $candidates}
			{form legend="" action="{$smarty.const.FISHEYE_PKG_URL}load_program.php"}
				<input type="hidden" name="gallery_id" value="{$scopeShow.gallery_id}" />
				<p>{tr}Not-yet-loaded seasons for{/tr} "{$scopeShow.title|escape}":</p>
				<ul>
					{foreach from=$candidates item=season}
						<li>
							<label>
								<input type="checkbox" name="selected[]" value="{$season|escape}" checked="checked" />
								{$season|escape}
							</label>
						</li>
					{/foreach}
				</ul>
				<input type="submit" class="btn btn-primary" name="fImportSeasons" value="{tr}Load Selected Seasons{/tr}" />
			{/form}
		{elseif $scopeShow}
			<p>{tr}Nothing to load - every season folder for this show is already registered.{/tr}</p>
		{/if}

	</div>
</div>
{/strip}
