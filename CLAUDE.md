# Fisheye Package — Developer Notes

## Gallery view template dispatch
`view.php` → `display_fisheye_gallery_inc.php` → `$gContent->getRenderTemplate()` returns
`bitpackage:fisheye/view_gallery.tpl`, which dispatches to:

```
gallery_views/{layout}/fisheye_{layout}_inc.tpl
```

Active layouts and their inc files:
- `galleriffic` — `fisheye_galleriffic_inc.tpl` (dispatcher) → `_1.tpl` / `_2.tpl` / `_5.tpl`
  by `$gContent->mInfo.galleriffic_style`
- `auto_flow` — `fisheye_auto_flow_inc.tpl`
- `fixed_grid` — `fisheye_fixed_grid_inc.tpl` (inc2/inc3 deleted — superseded)
- `matteo` — `fisheye_matteo_inc.tpl`
- `position_number` — `fisheye_position_number_inc.tpl`
- `simple_list` — `fisheye_simple_list_inc.tpl`

## Standard header pattern
Each gallery view inc uses the shared include pattern (matching stock/contact):

```smarty
<div class="display fisheye">
<header>
    {include file="bitpackage:fisheye/gallery_icons_inc.tpl"}
    <h1>{$gContent->getTitle()|escape}</h1>
    {include file="bitpackage:fisheye/gallery_breadcrumb_inc.tpl"}
</header>
```

- `gallery_icons_inc.tpl` — floaticon action icons (download, edit, add image, delete)
- `gallery_breadcrumb_inc.tpl` — `<small>` breadcrumb trail; owner link shown to users
  with update permission; ancestors from `getBreadcrumbLinks(1)`
- `gallery_nav.tpl` — prev/next image navigation only (breadcrumbs were removed from here)

Do NOT use `<div class="header">` — the merg theme blue background applies only to `<header>`.
Do NOT add a `container` class to the outer div — the Bitweaver layout already provides one.

## Data / description blocks
Fisheye gallery data is **plain text** (not wiki/rich text). Use `data|escape`, never
`getParsedData()`. The description section sits between `<header>` and the main body:

```smarty
{if $gContent->mInfo.data && $gContent->getPreference('show_description') ne 'n'}
<section class="body">
    <p>{$gContent->mInfo.data|escape}</p>
</section>
{/if}
```

## fixed_grid cols_per_page
`$cols_per_page` is NOT a Smarty variable — read it from `$gContent->mInfo.cols_per_page`.
Stored in `fisheye_gallery` table; edited via gallery edit form.

```smarty
{assign var="cols" value=$gContent->mInfo.cols_per_page|default:4}
{assign var="tdWidth" value="`100/$cols`"}
<table class="thumbnailblock" style="width:100%">
```

## PDF viewer — search/findbar
`liberty/templates/mime/pdf/view.tpl` passes `?highlight=term` from `view_image.php`
into the pdfjs viewer URL hash:

```
viewer.html?file={url}#zoom=page-width&search={highlight}
```

Standard pdfjs handles `#search=` via `findfromurlhash` but only highlights silently —
it does NOT open the findbar UI. A local patch in `themes/js/pdfjs-5.2.133/web/viewer.mjs`
adds the open call at the hash-parsing point (search for `params.has("search")`):

```javascript
if (query) {
    PDFViewerApplication.findBar.open();
    PDFViewerApplication.findBar.findField.value = query;
}
```

**This patch must be re-applied after any pdfjs version upgrade.**

## simple_list feature flags
Optional columns gated on kernel_config features:
- `fisheye_item_list_date` / `fisheye_item_list_creator` — Uploaded / by columns
- `fisheye_item_list_size` — Size/Duration column
- `fisheye_item_list_hits` — Downloads column
- `fisheye_item_list_name` — filename/mime shown below title in name column
- `fisheye_item_list_desc` — (unused in current template; data always shown)
