{strip}
<div id="xsbanner" class="col-xs-12 visible-xs">
	<div id="banner-xs" class="visible-xs">
	</div>
</div>
<div id="banner" class="col-xs-12 hidden-xs">
	<div id="banner">
		<ul id="background">
			{foreach from=$modImages item=modImg}
				<li><img src="/liberty/download/file/{$modImg.image_id|default:0}" title="{$modImg.title|default:''}" alt="{$modImg.title|default:''}" /></li>
			{/foreach}
		</ul>
	</div>
	<div id="overlay">
		<div id="banner-md" class="visible-sm visible-md">
		</div>
		<div id="banner-lg" class="visible-lg">
		</div>
	</div>
</div>
<div class="clear"></div>
{/strip}
