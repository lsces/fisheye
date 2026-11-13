{* The menu icon of a gallery - shared by fisheye's Edit page and fisheyemedia's, so the two are the same. Needs $gContent. A native select cannot draw pictures,
   so this is the same Bootstrap dropdown the menu itself is: a button showing the chosen icon and its name, opening a list of icons with their names. The choice
   goes in the hidden menu_icon field, which posts as the select did. *}
{assign var=currentMenuIcon value=$gContent->getPreference('menu_icon')|default:'folder-open'}
{assign var=menuIconChoices value=$gContent->menuIconChoices()}
<div class="form-group">
	{formlabel label="Menu icon"}
	{forminput}
		<div class="dropdown" id="menu-icon-picker">
			<input type="hidden" name="menu_icon" id="menu_icon" value="{$currentMenuIcon|escape}" />
			<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				<span class="menu-icon-current">{biticon ipackage="icons" iname=$currentMenuIcon iexplain=$menuIconChoices.$currentMenuIcon iforce="icon"} {tr}{$menuIconChoices.$currentMenuIcon}{/tr}</span> <span class="caret"></span>
			</button>
			<ul class="dropdown-menu">
				{foreach $menuIconChoices as $iconName => $iconLabel}
					<li><a href="#" data-icon="{$iconName|escape}">{biticon ipackage="icons" iname=$iconName iexplain=$iconLabel iforce="icon"} {tr}{$iconLabel}{/tr}</a></li>
				{/foreach}
			</ul>
		</div>
		{formhelp note="The icon this gallery has beside its name in the media menu, when it is a top-level gallery."}
	{/forminput}
</div>
<script>
(function () {ldelim}
	var picker = document.getElementById('menu-icon-picker');
	if (!picker) {ldelim} return; {rdelim}
	picker.querySelectorAll('.dropdown-menu a').forEach(function (a) {ldelim}
		a.addEventListener('click', function (e) {ldelim}
			e.preventDefault();
			document.getElementById('menu_icon').value = a.getAttribute('data-icon');
			picker.querySelector('.menu-icon-current').innerHTML = a.innerHTML;
		{rdelim});
	{rdelim});
{rdelim})();
</script>
