{strip}
{include file="bitpackage:fisheye/gallery_nav.tpl"}
<div class="galleriffic">

<div class="header">
	{include file="bitpackage:fisheye/gallery_icons_inc.tpl"}
	<h1>{$gContent->getTitle()|escape}</h1>
</div>

{if $gContent->mInfo.data && $gContent->getPreference('show_description') ne 'n'}
<div class="body">
	<p>{$gContent->mInfo.data|escape}</p>
</div>
{/if}

{assign var=hasVideos value=false}
{foreach from=$gContent->mItems item=_scanItem}
	{if is_a($_scanItem, '\Bitweaver\Fisheye\FisheyeImage') && $_scanItem->mInfo.mime_type|substr:0:6 == 'video/'}
		{assign var=hasVideos value=true}
	{/if}
{/foreach}

{jstabs}
{jstab title="Pictures"}

<!-- Start Advanced Gallery Html Containers -->
<div class="navigation-container">
	<div id="thumbs" class="navigation">
		<div>
		<ul class="thumbs noscript">
			{foreach from=$gContent->mItems item=galItem}
			{if !is_a($galItem, '\Bitweaver\Fisheye\FisheyeImage') || $galItem->mInfo.thumbnail_url.avatar}
			<li>
				{if is_a($galItem, '\Bitweaver\Fisheye\FisheyeImage')}
					<a class="thumb" rel="nozoom" name="{$galItem->mImageId}" href="{$galItem->mInfo.thumbnail_url.large}{*$smarty.const.FISHEYE_PKG_URL}view_image.php?image_id={$galItem->mImageId*}" title="{$galItem->mInfo.title|escape}">
						<img src="{$galItem->mInfo.thumbnail_url.avatar}" alt="{$galItem->mInfo.title|escape}" />
					</a>
					<h2 class="heading">
						<div class="image-heading">{booticon iname="icon-picture" isize="small" iexplain=$galItem->getContentTypeName()|escape}{$galItem->getDisplayLink( null )}</div>
					</h2>
					<div class="caption">
						<div class="meta floatright">
							{if $galItem->mInfo.event_time}
							<div class="photo-date date">
								{$galItem->mInfo.event_time|bit_short_date}
							</div>
							{/if}
							{if ($galItem->hasUpdatePermission() || $gContent->getPreference('link_original_images')) && $galItem->getDownloadUrl()}
							<div class="download">
								<a href="{$galItem->getDownloadUrl()}">{tr}Download Original{/tr}</a>
								{if $galItem->mInfo.width && $galItem->mInfo.height}
								<div class="photo-date">{$galItem->mInfo.width}x{$galItem->mInfo.height} {tr}pixels{/tr}</div>
								{/if}
							</div>
							{/if}
						</div>
						<div class="image-title"><p>{$galItem->mInfo.title|escape}</p></div>
						<div class="image-desc"><p>{$galItem->mInfo.description|default:''}</p></div>
					</div>
				{elseif is_a($galItem, '\Bitweaver\Fisheye\FisheyeGallery')}
					<a class="thumb" rel="nozoom" name="{$galItem->mContentId}" href="{$galItem->mPreviewImage->mInfo.thumbnail_url.large}" title="{$galItem->mInfo.title|escape}">
						<img src="{$galItem->mPreviewImage->mInfo.thumbnail_url.avatar}" alt="{$galItem->mInfo.title|escape}"/>
					</a>
					<div class="heading">
						<h2>{booticon iname="icon-picture" isize="small" iexplain=$galItem->getContentTypeName()|escape}{$galItem->getDisplayLink( null )}</h2><span class="image-count">({$galItem->getImageCount()} {tr}Items{/tr})</span>
					</div>
					<div class="caption">
						<div class="image-title"><p>{$galItem->mInfo.title|escape}</p></div>
						<div class="image-desc">{$galItem->mInfo.description|default:''}</div>
						<div class="download"></div>
					</div>
				{/if}
			</li>
			{/if}
			{/foreach}
		</ul>
		</div>
	</div>
	<div class="clear"></div>
	{include file="bitpackage:liberty/services_inc.tpl" serviceLocation='view' serviceHash=$gContent->mInfo}

	{if $gContent->getPreference('allow_comments') eq 'y'}
		{include file="bitpackage:liberty/comments.tpl"}
	{/if}

</div>

<div id="gallery" class="content">
	<div class="slideshow-container">
		<div id="heading" class="heading-container"></div>
		<div id="controls" class="controls"></div>
		<div id="loading" class="loader"></div>
		<div id="slideshow" class="slideshow"></div>
		<div id="imagedetails" class="image-details-container"></div>
	</div>
	<div id="caption" class="caption-container"></div>
</div>

<script>/*<![CDATA[*/
{literal}
jQuery(document).ready(function($) {
	// We only want these styles applied when javascript is enabled
	$('div.content').css('display', 'block');

	// Initially set opacity on thumbs and add
	// additional styling for hover effect on thumbs
	var onMouseOutOpacity = 0.67;
	$('#thumbs ul.thumbs li').opacityrollover({
		mouseOutOpacity:   onMouseOutOpacity,
		mouseOverOpacity:  1.0,
		fadeSpeed:         'fast',
		exemptionSelector: '.selected'
	});

	// Initialize Advanced Galleriffic Gallery
	var gallery = $('#thumbs').galleriffic({
		delay:                     2500,
		numThumbs:                 {/literal}{$gContent->getPreference('galleriffic_num_thumbs', $gBitSystem->getConfig('fisheye_gallery_default_galleriffic_num_thumbs', 30))}{literal},
		preloadAhead:              10,
		enableTopPager:            true,
		enableBottomPager:         true,
		maxPagesToShow:            6,
		imageContainerSel:         '#slideshow',
		controlsContainerSel:      '#controls',
		headingContainerSel:       '#heading',
		captionContainerSel:       '#caption',
		loadingContainerSel:       '#loading',
		renderSSControls:          true,
		renderNavControls:         true,
		playLinkText:              '',
		playLinkImage:             '{/literal}{booticon iname="icon-control-start" isize="small" iexplain="Play Slideshow"}{literal}',
		pauseLinkText:             '',
		pauseLinkImage:            '{/literal}{booticon iname="icon-control-pause" isize="small" iexplain="Pause Slideshow"}{literal}',
		prevLinkText:              '&laquo;',
		nextLinkText:              '&raquo;',
		nextPageLinkText:          'Next &rsaquo;',
		prevPageLinkText:          '&lsaquo; Prev',
		enableHistory:             true,
		autoStart:                 false,
		syncTransitions:           false,
		defaultTransitionDuration: 250,
		onSlideChange:             function(prevIndex, currentIndex) {
			// 'this' refers to the gallery, which is an extension of $('#thumbs')
			this.find('ul.thumbs').children()
				.eq(prevIndex).fadeTo('fast', onMouseOutOpacity).end()
				.eq(currentIndex).fadeTo('fast', 1.0);

			// Update the photo index display
			$('.photo-index').html( (currentIndex+1) +' of '+ this.data.length);
		},
		onImageLoadFinish:			function(pImageData) {
			jQuery.ajax({
				url: '{/literal}{$smarty.const.FISHEYE_PKG_URL}view_image_details.php?image_id={literal}'+pImageData.hash,
				success: function(data) {
					$('#imagedetails').html(data);
				}
			});
		},
		onPageTransitionOut:       function(callback) {
			this.fadeTo('fast', 0.0, callback);
		},
		onPageTransitionIn:        function() {
			var prevPageLink = this.find('a.prev').css('visibility', 'hidden');
			var nextPageLink = this.find('a.next').css('visibility', 'hidden');

			// Show appropriate next / prev page links
			if (this.displayedPage > 0)
				prevPageLink.css('visibility', 'visible');

			var lastPage = this.getNumPages() - 1;
			if (this.displayedPage < lastPage)
				nextPageLink.css('visibility', 'visible');

			this.fadeTo('fast', 1.0);
		}
	});

	gallery.find('a.prev').click(function(e) {
		gallery.previousPage();
		e.preventDefault();
	});

	gallery.find('a.next').click(function(e) {
		gallery.nextPage();
		e.preventDefault();
	});
});
{/literal}
/*]]>*/</script>

{/jstab}
{if $hasVideos}
{jstab title="Videos"}
<div class="row">
	<div class="col-sm-4" id="fisheye-video-list" style="overflow-y:auto;max-height:500px;border-right:1px solid #ddd;">
		{foreach from=$gContent->mItems item=galItem}
			{if is_a($galItem, '\Bitweaver\Fisheye\FisheyeImage') && $galItem->mInfo.mime_type|substr:0:6 == 'video/'}
			<div class="fisheye-video-item"
			     data-src="{$galItem->mInfo.download_url|escape}"
			     data-type="{$galItem->mInfo.mime_type|escape}"
			     data-title="{$galItem->mInfo.title|escape}"
			     data-url="{$galItem->getDisplayUrl()|escape}"
			     style="cursor:pointer;padding:8px;border-bottom:1px solid #eee;">
				{if $galItem->mInfo.thumbnail_url.small}
					<img src="{$galItem->mInfo.thumbnail_url.small}" style="float:left;margin-right:8px;max-width:80px;" alt="" />
				{else}
					{booticon iname="fa-film" isize="medium" iexplain="Video"}
				{/if}
				<span>{$galItem->mInfo.title|escape}</span>
				<div style="clear:both;"></div>
			</div>
			{/if}
		{/foreach}
	</div>
	<div class="col-sm-8">
		<p class="norecords" id="fisheye-video-prompt">{tr}Select a video from the list to play.{/tr}</p>
		<video id="fisheye-video-player" controls preload="metadata" style="width:100%;max-height:500px;display:none;">
			<source id="fisheye-video-src" src="" type="video/mp4" />
			<p>{tr}Your browser does not support HTML5 video.{/tr}</p>
		</video>
		<div id="fisheye-video-meta" style="display:none;margin-top:8px;">
			<h4 id="fisheye-video-title"></h4>
			<a id="fisheye-video-link" href="">{tr}Full page{/tr}</a>
		</div>
	</div>
</div>
<script>
{literal}
(function() {
	function fisheyePlayVideo(el) {
		var player  = document.getElementById('fisheye-video-player');
		var srcEl   = document.getElementById('fisheye-video-src');
		srcEl.src   = el.getAttribute('data-src');
		srcEl.type  = el.getAttribute('data-type');
		player.load();
		player.play();
		player.style.display = '';
		document.getElementById('fisheye-video-prompt').style.display = 'none';
		document.getElementById('fisheye-video-title').textContent = el.getAttribute('data-title');
		document.getElementById('fisheye-video-link').href = el.getAttribute('data-url');
		document.getElementById('fisheye-video-meta').style.display = '';
		document.querySelectorAll('.fisheye-video-item').forEach(function(item) {
			item.style.background = '';
		});
		el.style.background = '#e8f4fe';
	}
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.fisheye-video-item').forEach(function(el) {
			el.addEventListener('click', function() { fisheyePlayVideo(this); });
		});
	});
}());
{/literal}
</script>
{/jstab}
{/if}
{/jstabs}

</div>
{/strip}
