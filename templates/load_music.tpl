{* Base-folder picker (Classic/Modern/whatever else sits under Music/) then real collection
   folders under it with no gallery yet - see load_music.php's own docblock for the "real
   collection vs single album" distinction. *}
{strip}
<div class="floaticon">{bithelp}</div>

<div class="admin liberty">
	<div class="header">
		<h1><a href="{$topGalleryUrl|escape}">{tr}Music{/tr}</a> - {tr}Load Music Collections{/tr}{if $base} - {$base|escape}{/if}</h1>
	</div>

	<div class="body">

		{if !$base}
			{if $baseFolders}
				<p>{tr}Choose a base folder{/tr}:</p>
				<ul>
					{foreach from=$baseFolders item=folder}
						<li><a href="{$smarty.const.FISHEYE_PKG_URL}load_music.php?base={$folder|escape:'url'}">{$folder|escape}</a></li>
					{/foreach}
				</ul>
			{else}
				<p>{tr}No base folders found under Music/.{/tr}</p>
			{/if}
		{else}

			{if $result}
				{if $result.created}
					<div class="alert alert-success">
						<p>{tr}Collections created{/tr}:</p>
						<ul>
							{foreach from=$result.created item=row}
								<li>{$row.folder|escape} - <a href="{$smarty.const.FISHEYE_PKG_URL}load_album.php?gallery_id={$row.gallery_id}">{tr}Load its albums now{/tr}</a></li>
							{/foreach}
						</ul>
					</div>
				{/if}
				{if $result.errors}
					<div class="alert alert-danger">
						<p>{tr}Failed{/tr}:</p>
						<ul>{foreach from=$result.errors item=row}<li>{$row.folder|escape} - {$row.error|escape}</li>{/foreach}</ul>
					</div>
				{/if}
			{/if}

			{if $candidates}
				{form legend="" action="{$smarty.const.FISHEYE_PKG_URL}load_music.php"}
					<input type="hidden" name="base" value="{$base|escape}" />
					<p>{tr}Real collection folders (more than one album) under{/tr} {$base|escape}/ {tr}with no gallery yet:{/tr}</p>
					<ul>
						{foreach from=$candidates item=candidate}
							<li>
								<label>
									<input type="checkbox" name="selected[]" value="{$candidate.folder|escape}" />
									{$candidate.folder|escape}
								</label>
							</li>
						{/foreach}
					</ul>
					<input type="submit" class="btn btn-primary" name="fCreate" value="{tr}Create Selected Galleries{/tr}" />
				{/form}
			{else}
				<p>{tr}Nothing to load - every real collection folder here already has a gallery.{/tr}</p>
			{/if}

		{/if}

	</div>
</div>
{/strip}
