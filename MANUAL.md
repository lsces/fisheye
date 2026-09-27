# Fisheye Package — Reference Manual

How the package actually works today. For the history of *why* — decisions, bugs found, wrong
turns — see `CLAUDE.md`'s dated session log instead; this file only tracks current behaviour.

## What this is

A photo gallery package built on two base classes:

- **`FisheyeGallery`** — a container. Holds members (photos, other galleries) via
  `addItem()`/`loadImages()`, extends `LibertyMime` (so it also has its own optional attachment
  slot, used by real "collection"-style content).
- **`FisheyeImage`** — a single photo.

[`fisheyemedia`](https://github.com/lsces/fisheyemedia) extends both of these (via its own
intermediate `FisheyeMediaGallery`/`FisheyeMediaImage` classes) to catalogue a film/TV/music
library — a separate package, not part of fisheye itself. Fisheye exposes two generic extension
points it relies on, usable by any package the same way:
- **Gallery layouts** — `FisheyeGallery::getAllLayouts()` merges its own built-in layouts with
  whatever any active package contributes via `registerService('fisheye_gallery_layout', ...)`
  (see `getGridPaginationTypes()`/`getGalleryViewsPath()` for how a contributed layout's own
  pagination math and template folder get resolved). Fisheye has no hardcoded knowledge of any
  layout name beyond its own.
- **Display/edit routing** — `getDisplayUrl()`/`getEditUrl()` are ordinary polymorphic method
  overrides. A subclass with its own dedicated view/edit pages overrides these directly; fisheye's
  own generic defaults (`view_image.php`/`edit.php`) never try to guess a content type's real
  destination from anything other than the object's own override.

## Real thumbnail attachments (subclass gotchas)

Both `FisheyeImage` and `FisheyeGallery` descend from `LibertyMime`, so both have an unused
attachment slot — a subclass with no file of its own (e.g. a gallery-shaped phantom subclass) can
store a real image there to get proper generated thumbnails through the standard machinery, rather
than a xref-based reference (which can't select a size and never gets a real generated thumbnail).

Two real gotchas here for any such subclass:
- **`FisheyeGallery::load()`/`store()` shortcut straight to `LibertyContent`'s own versions**,
  never touching attachment data at all. Any code on a gallery-based phantom subclass that needs
  its own attachment must call `\Bitweaver\Liberty\LibertyMime::load()`/`::store()` explicitly
  (class-scoped, not `parent::`) rather than relying on `$this->load()`/`$this->store()`.
- **`FisheyeGallery::getThumbnailImage()`'s own recursion treats any member that `is_a()` a
  `FisheyeGallery` as "just another gallery to bubble through"** — a gallery-based phantom
  subclass that has its own real thumbnail attachment must override `getThumbnailImage()` to
  short-circuit and return itself once it has that data, or a parent gallery's own thumbnail
  lookup will bubble straight past it into one of its members instead.

A generic `promoteImageToThumbnail( $pRelativePath )` hook (implemented per-subclass, against its
own storage shape) lets an already-downloaded alternate image be promoted into the real thumbnail
slot later ("change the auto-pick").

## Gallery view template dispatch

`view.php` → `display_fisheye_gallery_inc.php` → `$gContent->getRenderTemplate()` returns
`bitpackage:fisheye/view_gallery.tpl`, which dispatches to `` `$gContent->getGalleryViewsPath()`
gallery_views/{layout}/fisheye_{layout}_inc.tpl `` (a layout's own contributing package decides
which `gallery_views/` folder its templates live under — see "What this is" above). Fisheye's own
built-in layouts: `galleriffic` (dispatcher →`_1.tpl`/`_2.tpl`/`_5.tpl` by
`$gContent->mInfo.galleriffic_style`), `auto_flow`, `fixed_grid` (the generic per-item grid —
genuinely calls `getThumbnailUri()`/`getThumbnailImage()` as real polymorphic methods, unlike
`galleriffic`, which reads `$galItem->mPreviewImage->mInfo.thumbnail_url.avatar` directly and
bypasses any content-type-specific thumbnail override entirely), `matteo`, `position_number`,
`simple_list`.

Standard header pattern for every gallery view inc (matching stock/contact):

```smarty
<div class="display fisheye">
<header>
    {include file="bitpackage:fisheye/gallery_icons_inc.tpl"}
    <h1>{$gContent->getTitle()|escape}</h1>
    {include file="bitpackage:fisheye/gallery_breadcrumb_inc.tpl"}
</header>
```

- `gallery_icons_inc.tpl` — floaticon action icons (download/edit/add image/delete) — these key
  off `$gContent->mGalleryId`, so a plain content item (a film, a photo) needs its own minimal
  icons include instead (Edit only, plus the generic `services_inc.tpl` hook every view page
  gets) rather than including this wholesale.
- `gallery_breadcrumb_inc.tpl` — ancestor links are built via a hardcoded pretty-url pattern that
  always lands on the generic gallery view, regardless of the target's real content type — **not
  type-aware**. A content type with its own dedicated view page should link to its parent directly
  via the parent object's own `getDisplayUrl()` instead of including this wholesale.
- `gallery_nav.tpl` — prev/next image navigation only.

Do NOT use `<div class="header">` (the merg theme's blue background applies only to `<header>`);
do NOT add a `container` class to the outer div (the Bitweaver layout already provides one).

Gallery description text is **plain text**, not wiki/rich text — use `data|escape`, never
`getParsedData()`.

`fixed_grid`'s `$cols_per_page` is not a Smarty variable — read `$gContent->mInfo.cols_per_page`
(stored in `fisheye_gallery`, edited via the gallery edit form).

## Other template notes

- **PDF viewer search/findbar**: `liberty/templates/mime/pdf/view.tpl` passes `?highlight=term`
  into the pdfjs viewer URL hash (`viewer.html?file={url}#zoom=page-width&search={highlight}`).
  Standard pdfjs handles `#search=` but only highlights silently — a local patch in
  `themes/js/pdfjs-<version>/web/viewer.mjs` (search for `params.has("search")`) opens the findbar
  UI too. **This patch must be re-applied after any pdfjs version upgrade.**
- **`auto_flow` image sizing**: renders `<img class="thumb">` inside flex items — a per-site theme
  CSS `max-width`/fixed `height` on `.thumb` breaks mixed portrait/landscape galleries. Fix scoped
  to a `.fisheye-flow img.thumb { width:100%; height:auto; max-width:none; }` rule (specificity
  0,2,1 beats a typical `a img.thumb` site rule at 0,1,2). Don't set `aspect-ratio` — galleries mix
  orientations.
- **`simple_list` feature flags** (kernel_config): `fisheye_item_list_date`/`_creator` (Uploaded/by
  columns), `_size` (Size/Duration column), `_hits` (Downloads column), `_name` (filename/mime
  under the title).
