{strip}
<td>{$xrefInfo.xref_title|escape}</td>
<td>
	<a href="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$xrefInfo.xref_id}" target="_blank" rel="noopener">
		<img src="{$smarty.const.FISHEYE_PKG_URL}view_extra_image.php?xref_id={$xrefInfo.xref_id}" alt="{$xrefInfo.xkey_ext|escape}" style="max-height:90px; max-width:140px;" />
	</a>
</td>
<td>{$xrefInfo.data|escape}</td>
{include file="bitpackage:liberty/xref/dates_cell.tpl"}
{include file="bitpackage:liberty/xref/action_icons.tpl"}
{/strip}
