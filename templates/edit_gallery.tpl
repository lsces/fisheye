{literal}
<script>//<![CDATA[
function updateGalleryPagination() {
	var paginationIds = ['fixed_grid','auto_flow','position_number','simple_list','matteo','galleriffic','film_grid','program_grid'];
	// music_grid still shares fixed_grid's rows*cols settings panel - same underlying grid
	// pagination math (see FisheyeGallery::getList()'s layout branch), just its own view
	// template/floaticons - not worth a duplicate panel for it alone. film_grid/program_grid
	// have their own (Lester, 2026-09-04) - fixed at 4*8=32 by FisheyeGallery::
	// verifyGalleryData(), not admin-configurable, the visual column count is pure CSS now.
	var styleGroup = { music_grid: 'fixed_grid' };
	paginationIds.forEach(function(id) {
		var div = document.getElementById(id+'-pagination');
		div.style.display = 'none';
		div.querySelectorAll('input, select').forEach(function(el) { el.disabled = true; });
	});
	var input = document.getElementById('editGalleryForm').gallery_pagination;
	var select = input.options[input.selectedIndex].value;
	var activeDiv = document.getElementById((styleGroup[select] || select)+'-pagination');
	activeDiv.style.display = 'block';
	activeDiv.querySelectorAll('input, select').forEach(function(el) { el.disabled = false; });
}
document.addEventListener('DOMContentLoaded', updateGalleryPagination);
//]]></script>
{/literal}
{strip}
<div class="edit fisheye">
	<div class="header">
		<h1>
			{if $gContent->getTitle()}
				{tr}Edit Gallery{/tr}: {$gContent->getTitle()|escape}
			{else}
				{tr}Create Image Gallery{/tr}
			{/if}
		</h1>
	</div>

	<div class="body">
		{form id="editGalleryForm" ipackage="fisheye" ifile="edit.php"}
			{jstabs}
				{jstab title="Edit Gallery"}
					{legend legend="Edit Gallery"}
						{formfeedback error=$errors warning=$fisheyeWarnings success=$fisheyeSuccess}

						<input type="hidden" name="gallery_id" value="{$galleryId|escape}"/>

						<div class="form-group">
							{formlabel label="Title" for="gallery-title" mandatory="y"}
							{forminput}
								<input type="text" name="title" id="gallery-title" value="{$gContent->getTitle()|escape}" maxlength="160" size="50"/>
							{/forminput}
						</div>

						<div class="form-group">
							{formlabel label="Description" for="gallery-desc"}
							{forminput}
								<textarea name="edit" id="gallery-desc" rows="4" cols="50">{$gContent->mInfo.data|default:''|escape}</textarea>
							{/forminput}
						</div>

						<div class="form-group">
							{forminput label="checkbox"}
								<input type="checkbox" name="show_description" id="show_description" value="y" {if $gContent->getPreference('show_description') ne 'n'}checked="checked"{/if} />{tr}Show Description{/tr}
								{formhelp note="Display the gallery description text below the gallery header."}
							{/forminput}
						</div>

						{if $gBitUser->hasPermission('p_fisheye_create_public_gal')}
							<div class="form-group">
								{forminput label="checkbox"}
									<input type="checkbox" name="is_public" id="is_public" value="y" {if $gContent->getPreference('is_public') eq 'y'}checked="checked"{/if} />Public Gallery
									{formhelp note="Allow other users to upload to this gallery."}
								{/forminput}
							</div>
						{/if}

						<div class="form-group">
							{forminput label="checkbox"}
								<input type="checkbox" name="link_original_images" id="link_original_images" value="y" {if $gContent->getPreference('link_original_images') eq 'y'}checked="checked"{/if} />Show Original Images
								{formhelp note="Display a link to the original image for anyone viewing the images in the gallery."}
							{/forminput}
						</div>

						<div class="form-group">
							{formlabel label="Gallery Pagination" for="gallery-pagination"}
							{forminput}
								{html_options name="gallery_pagination" id="gallery-pagination" options=$galleryPaginationTypes selected=$gContent->getPreference('gallery_pagination',$gBitSystem->getConfig('default_gallery_pagination',$smarty.const.FISHEYE_PAGINATION_FIXED_GRID)) onchange="updateGalleryPagination();"}
								<div id="fixed_grid-pagination">
									<input type="text" id="gallery-rows-per-page" name="rows_per_page" size="2" maxlength="2" value="{$gContent->mInfo.rows_per_page|default:$gBitSystem->getConfig('fisheye_gallery_default_rows_per_page')}"/> {tr}Rows per page{/tr}<br/>
									<input type="text" id="gallery-cols-per-page" name="cols_per_page" size="2" maxlength="2" value="{$gContent->mInfo.cols_per_page|default:$gBitSystem->getConfig('fisheye_gallery_default_cols_per_page')}"/> {tr}Columns per page{/tr}
									{formhelp note="The images will be displayed in a fixed grid. You can specify the number of thumbnails to display per page.<br /><strong>[rows] * [columns] = [number of images]</strong>."}
								</div>

								<div id="auto_flow-pagination">
									<input type="text" id="gallery-total-per-page" name="total_per_page" size="3" maxlength="3" value="{$gContent->getPreference('total_per_page', $gContent->mInfo.rows_per_page|default:20)}"/> {tr}Total images per page{/tr}
									{formhelp note="The layout of the images on each gallery page will automatically adjust to the browser width. You can specify the total number of thumbnails to display per page."}
								</div>

								<div id="position_number-pagination">
									{formhelp note="This option allows you to designate each specific image on each page. The image order number entered on the Image Order page will determine the exact location of each image. Fractional numbers indicate PAGE.POSITION and will specifiy variable images per page, such as: 1.1, 1.2, 2.1, 3.1, 3.2, 3.3"}
								</div>

								<div id="simple_list-pagination">
									<input type="text" id="gallery-total-per-page-list" name="total_per_page" size="3" maxlength="3" value="{$gContent->getPreference('total_per_page', $gContent->mInfo.rows_per_page|default:20)}"/> {tr}Total lines per page{/tr}
									{formhelp note="This option allows a single column display of images with mime details where available."}
								</div>

								<div id="matteo-pagination">
									<input type="text" id="gallery-total-per-page-matteo" name="total_per_page" size="3" maxlength="3" value="{$gContent->getPreference('total_per_page', $gContent->mInfo.rows_per_page|default:20)}"/> {tr}Total images per page{/tr}
									{formhelp note="This option provides an ajax powered scrolling display using the mbGallery jquery library."}
								</div>

								<div id="galleriffic-pagination">
									<input type="text" id="galleriffic-num-thumbs" name="galleriffic_num_thumbs" size="3" maxlength="3" value="{$gContent->getPreference('galleriffic_num_thumbs', $gBitSystem->getConfig('fisheye_gallery_default_galleriffic_num_thumbs', 30))}"/> {tr}Thumbnails per page{/tr}<br/>
									<input type="text" id="galleriffic-style" name="galleriffic_style" size="2" maxlength="2" value="{$gContent->mInfo.galleriffic_style|default:$gBitSystem->getConfig('fisheye_gallery_default_galleriffic_style')}"/> {tr}Galleriffic layout style{/tr}
									{formhelp note="This option provides a javascript powered tabbed thumbnail list display using the galleriffic jquery library."}
								</div>

								<div id="film_grid-pagination">
									{formhelp note="Fixed responsive grid - 8 films across on a wide monitor, folding to 4 then 2 on narrower screens. Not admin-configurable: 32 per page, matching a whole number of full-width rows."}
								</div>

								<div id="program_grid-pagination">
									{formhelp note="Fixed responsive grid - 8 shows across on a wide monitor, folding to 4 then 2 on narrower screens. Not admin-configurable: 32 per page, matching a whole number of full-width rows."}
								</div>
							{/forminput}
						</div>

						{if $thumbnailSizes}
							<div class="form-group">
								{formlabel label="Thumbnail Size" for="thumbnail_size"}
								{forminput}
									{if $gContent->mInfo.thumbnail_size}
										{assign var=thumb_size value=$gContent->mInfo.thumbnail_size}
									{else}
										{assign var=thumb_size value=$gBitSystem->getConfig('fisheye_gallery_default_thumbnail_size')}
									{/if}
									{html_options values=$thumbnailSizes options=$thumbnailSizes name="thumbnail_size" id="thumbnail_size" selected=$thumb_size}
								{/forminput}
							</div>
						{/if}

						{if $gContent->isValid()}
							<div class="form-group">
								{forminput label="checkbox"}
									<input type="checkbox" name="generate_thumbnails" id="generate_thumbnails" value="y" />{tr}Regenerate All Thumbnails{/tr}
									{formhelp note="Re-generate thumbnails for all images and videos in this gallery. This may take some time for large galleries."}
								{/forminput}
							</div>
						{/if}

						<div class="form-group">
							{forminput label="checkbox"}
								<input type="checkbox" name="allow_comments" id="allow_comments" value="y" {if !$gContent->isValid() || $gContent->getPreference('allow_comments') eq 'y'}checked="checked"{/if} />{tr}Image Comments{/tr}
								{formhelp note="Allow posting comments for an image."}
							{/forminput}
						</div>

						{include file="bitpackage:liberty/edit_services_inc.tpl" serviceFile="content_edit_mini_tpl"}
					{/legend}
				{/jstab}

				{include file="bitpackage:liberty/edit_services_inc.tpl" serviceFile="content_edit_tab_tpl"}

				{if $galleryTree}
					{jstab title="Gallery Memberships"}
						{legend legend="Gallery Memberships"}
							If you would like this gallery to be a sub-gallery, check the parent gallery below. It is possible to belong to multiple galleries. If no parent is checked, this gallery will appear as a top-level gallery.
							<div class="form-group">
								{formlabel label=$gContent->getContentTypeName()|cat:" Belongs to These Galleries"}
								{forminput}
									{if $galleryTree}
										<div class="gallerytree">
											{$galleryTree}
										</div>
									{else}
										<p class="norecords">
											No Galleries Found.<br />
										</p>
									{/if}
								{/forminput}
							</div>
						{/legend}
					{/jstab}
				{/if}
			{/jstabs}

			<div class="form-group submit">
				{if $gContent->isValid()}
					<input type="submit" class="btn btn-default" name="cancelgallery" value="Cancel"/>
				{/if}
				<input type="submit" class="btn btn-default" name="savegallery" value="Save Gallery"/>
			</div>
		{/form}

	</div>	<!-- end .body -->
</div>	<!-- end .fisheye -->
{/strip}
