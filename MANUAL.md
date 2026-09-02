# Fisheye Package — Reference Manual

How the package actually works today. For the history of *why* — decisions, bugs found, wrong
turns — see `CLAUDE.md`'s dated session log instead; this file only tracks current behaviour.

## What this is

A photo/media gallery package built on two base classes:

- **`FisheyeGallery`** — a container. Holds members (photos, films, other galleries) via
  `addItem()`/`loadImages()`, extends `LibertyMime` (so it also has its own optional attachment
  slot, used by real "collection"-style content — see below).
- **`FisheyeImage`** — a single item: a photo, or (via a phantom subclass) a film or a TV season.

As of the 2026-09 media extension, fisheye also covers real media cataloguing (film/TV/music
libraries scanned from disk, backed by a local Plex Media Server install as the metadata/artwork
source) without a separate package — the gap over plain photo galleries was just extra mime types,
extra `gallery_views` layouts, and per-content-type xref vocabulary, all of which the existing
xref/gallery machinery already supports.

## Content type hierarchy

Phantom subclasses ring-fence xref vocabulary via `content_type_guid`, the same pattern
`Contact`/`ContactPerson`/`ContactBusiness` uses — each subclass has no behavioural difference
from its base beyond its own `registerContentType()` call. `LibertyXrefType`'s dual-guid
`IN(contentTypeGuid, packageGuid)` scoping means an item registered at one guid is invisible to
another — so Season-level fields don't leak onto a Film, TheTVDB doesn't show up as an option on
a Film, MusicBrainz doesn't show up on a Season, etc.

| Class | Extends | Represents | Own file? |
|---|---|---|---|
| `FisheyeFilm` | `FisheyeImage` | A single film | Yes — the video itself, a real `LibertyMime` attachment |
| `FisheyeSeason` | `FisheyeImage` | One season of a show | No — pure metadata container over its episodes' xref rows |
| `FisheyeProgram` | `FisheyeGallery` | A TV show | No — but has its own attachment slot for a selected thumbnail (see below) |
| `FisheyeAlbum` | `FisheyeImage` | A music album | Registered, xref vocabulary defined; playback/browsing UI not built out yet |

Hierarchy: **Show → Season → Episode** / **Artist → Album → Track**; a Film stands alone
(single-level). Show/Artist/Composer as a genuine top-level browsable concept (a computed listing
with no `liberty_content` row of its own, same idea as `food`'s `FoodDay` pattern) is designed but
not built — for now, a show is just a real `FisheyeProgram` gallery holding `FisheyeSeason`
members.

**Collections** (a franchise, or a show grouping its seasons) don't need a new content type —
`FisheyeGallery::addItem()` takes any `content_id` with no content-type check, and its own
`isInGallery()` guard already checks both directions before inserting, so nesting a gallery inside
another gallery is a safe, anticipated case.

## View/edit page pairs

Each real media content type gets its own `view_X.php`/`edit_X.php` pair, replacing the generic
`view.php`/`edit.php`/`view_image.php`/`edit_image.php` a plain gallery/photo uses:

- `view_film.php` / `edit_film.php`
- `view_program.php` / `edit_program.php`
- `view_season.php` / `edit_season.php`

**View pages are pure display** — no `$_REQUEST` action handling, no update-permission checks
beyond what rendering needs. **Edit pages own every mutating action**: title edit via the generic
`store()` call, the generic liberty xref table (`list_xref.tpl`/`add_xref.php`/`edit_xref.php` —
reused as-is, no bespoke per-field markup needed since every item already registers with an
existing generic template), and the Plex reload actions (see below).

**Both `getEditUrl()` and `getDisplayUrl()` need an explicit override on every phantom subclass.**
The generic defaults point at the base class's own generic page (`<package>/edit.php` for
`getEditUrl()`, an `image_id`/`gallery_id`-keyed generic view for `getDisplayUrl()`) — which is
wrong for any of these types and fails in different ways depending on which is missing:
- Missing `getEditUrl()` override: a fatal error the first time anything tries to redirect there
  (the generic gallery `edit.php` calls methods a phantom-gallery subclass doesn't have).
- Missing `getDisplayUrl()` override: no fatal error, just a silently wrong link — gallery-grid
  templates all call this generically, so a member without its own override quietly routes to the
  wrong page.

The shared `gallery_breadcrumb_inc.tpl` component is **not type-aware** — its ancestor links are
built via a hardcoded pretty-url pattern that always lands on the generic gallery view, regardless
of the target's real content type. A season's own page instead links directly to its parent via
the parent object's own `getDisplayUrl()`, one level only (a season is always exactly one level
below its show, so a full breadcrumb chain isn't needed there).

**When resolving a parent/ancestor object of unknown real type, use `FisheyeGallery::lookup()`
(which routes through `getLibertyObject()`'s normal polymorphic dispatch), never a bare
`new FisheyeGallery(...)`** — the latter is always a plain `FisheyeGallery` instance regardless of
the row's actual registered type, silently defeating any `getDisplayUrl()`/`getEditUrl()` override
the real subclass has.

## Storage roots

Two independent storage roots, both resolved via `liberty/plugins/mime.film.php`:

- `mime_film_get_storage_root()` — the plain root (`fisheye_disk_storage_root` config key), used
  by films and music.
- `mime_film_get_tvshow_storage_root( $pShowTitle )` — TV shows only, split by the show title's
  first letter into two configurable roots (`fisheye_tvshow_storage_root_am` /
  `fisheye_tvshow_storage_root_nz`) — a real filesystem split some deployments use for a large TV
  library, not merely cosmetic.

**Every content class exposes its own `getImageStorageRoot()`** (film: the plain root; season/
program: the TV-specific root, resolved via the show title) — any code that needs to resolve a
storage root for xref file data (image/episode file streaming, the generic xref-file-replace hooks
below) must call this polymorphically on the content object, never hardcode either
`mime_film_get_storage_root()` call directly. The two roots can coincide in a given deployment
(making a hardcoded call look like it works) but diverge in general — this has been a real,
repeated bug source.

## Xref-based metadata

Standard `liberty_xref_group`/`liberty_xref_item` vocabulary per content type — genre/director/
writer/star (grouped under its own `cast` tab, not lumped in with single-value fields)/
content_rating/duration, plus external-ID link items (`imdb`/`tmdb`/`tvdb` as applicable per type)
using the generic `href` template. All served by the existing generic xref admin UI — no bespoke
per-field markup needed anywhere in this extension.

**Pages must never hardcode which `x_group` an item lives in.** Use
`LibertyXrefContent::allXrefs()` (a flat pass across every loaded group, same "ignore group name"
spirit as the existing `findByItem()`/`allItems()`) and bucket by item name only. A page that reads
`$xrefInfo->mGroups['metadata']` directly breaks silently the next time an item moves to a
different tab — already happened once with `star`'s move into `cast`.

**Episode** is a `liberty_xref` row under its season's own `content_id` (not a separate gallery
level) — `xkey_ext` holds the video file path relative to the season's storage root, `data` holds
a JSON blob (title/summary/air_date/director/writer/star/content_rating/duration/a `thumb` image
path), `xorder` is the episode number. `Track` (album/song) is the same shape, one level down from
an album.

**Alternate images** (`image` item, `images` group) — extra poster/backdrop artwork for a film,
season, or show, stored as ordinary xref rows rather than a second `LibertyMime` attachment row
(multiple attachments per `content_id` is not supported on this stack). `xkey_ext` is the file's
path relative to the owning object's storage root, in a shared `images/` folder alongside the main
file; `xorder` gives display order. Rendered via the shared, collapsible `images_strip_inc.tpl`
(starts closed) — a stopgap presentation layer, expected to eventually be replaced by real
cast/crew imagery once that data exists.

## Real thumbnail attachments (Season/Program)

A season or show has no file of its own, but both `FisheyeImage` and `FisheyeGallery` descend from
`LibertyMime`, so both have an unused attachment slot — a real image can be stored there to get
proper generated thumbnails through the standard machinery, rather than a xref-based reference
(which can't select a size and never gets a real generated thumbnail).

Two real gotchas here, both fisheye-specific:
- **`FisheyeGallery::load()`/`store()` shortcut straight to `LibertyContent`'s own versions**,
  never touching attachment data at all. Any code on a gallery-based phantom subclass that needs
  its own attachment must call `\Bitweaver\Liberty\LibertyMime::load()`/`::store()` explicitly
  (class-scoped, not `parent::`) rather than relying on `$this->load()`/`$this->store()`.
- **`FisheyeGallery::getThumbnailImage()`'s own recursion treats any member that `is_a()` a
  `FisheyeGallery` as "just another gallery to bubble through"** — a gallery-based phantom
  subclass that has its own real thumbnail attachment must override `getThumbnailImage()` to
  short-circuit and return itself once it has that data, or a parent gallery's own thumbnail
  lookup will bubble straight past it into one of its members instead.

A generic `promoteImageToThumbnail( $pRelativePath )` hook lets an already-downloaded alternate
image be promoted into the real thumbnail slot later ("change the auto-pick"); each content class
implements this appropriately for its own storage shape (a film reuses its poster-sidecar
convention since its one attachment slot is already the video file itself; season/program swap the
attachment directly).

## Plex integration

Plex is a bootstrap/backfill **source**, not a runtime dependency — every reload action below
copies data (text fields, artwork files) into fisheye's own storage (`liberty_xref`/`liberty_content`/
a real attachment, per the sections above) rather than reading from Plex live at display time. The
intent is that Plex is switched off entirely once real local metadata/artwork exists for everything
it's fed — nothing in fisheye reads from Plex outside these explicit, one-off reload actions.

Two config keys (`fisheye/admin/admin_fisheye_inc.php`):
- `fisheye_plex_db_path` — Plex's own library SQLite database. World-readable by default, no
  permission workaround needed for text metadata.
- `fisheye_plex_token` — needed for anything going through Plex's local HTTP API (artwork
  endpoints, external-ID guids) — lives in Plex's own `Preferences.xml`, which is *not*
  world-readable, so this has to be copied in by hand once.

Each content class matches itself against Plex's `metadata_items` table:
- Film — by the real absolute file path (`realpath()` against the storage root, since Plex's own
  `media_parts.file` doesn't know about any symlink the storage root itself might be).
- Season — no file of its own, so matched via one of its own episode xref rows' file path, walking
  Plex's `metadata_items.parent_id` from episode → season.
- Show — matched by exact title against Plex's own show-level (`metadata_type=2`) records.

Plex's `metadata_items.metadata_type`: `1`=movie, `2`=show, `3`=season, `4`=episode. Its
`tags`/`taggings` `tag_type`: `1`=genre, `4`=director, `5`=writer, `6`=actor/star (not documented
anywhere by Plex itself, confirmed empirically). **Genre never exists below show level** in Plex's
own data model — a season-level record carries no genre/director/writer/star/rating/duration at
all; that data only exists one level down, per-episode.

Three separate, idempotent reload actions per edit page (kept deliberately separate — different
weight/frequency, not one action doing everything):
- **Reload Metadata** — text fields + external-ID guids. Rebuild-not-diff: deletes every xref row
  it's about to write before re-inserting, since a repeated run with no delete step just appends
  duplicates (there's no natural per-value key to update in place).
- **Reload Images** — alternate poster/backdrop artwork from Plex's `/posters`/`/arts` local API
  endpoints (TMDB-backed, `w342`/`w780` presets re-resized down to a 400px bounding box via the
  shared resize helper below). Idempotent **per type** (poster vs. art) — a type only re-fetches
  if every existing row of that type has first been deleted, so tidying one type down to empty
  doesn't block ever re-fetching it without also wiping the other type. Also auto-attaches Plex's
  own currently-`selected="1"` poster as the real thumbnail attachment the first time it runs
  (checked via an empty attachment slot, so a later manual override is never clobbered).
- **Load Episodes** (season only) — pulls the season's full episode list from Plex, including each
  episode's own text metadata and its own Plex-generated screenshot thumbnail.

## Generic xref-file hooks (`liberty/edit_xref.php`)

The shared xref controller knows nothing about fisheye specifically — three `method_exists()`-
gated hooks let a content class handle its own file lifecycle for an xref row that references a
file:
- `replaceXrefFile( $pItem, $pXkeyExt, $pTmpPath )` — an uploaded file replaces what an xref row
  already references, in place (the row's own `xkey_ext` never changes).
- `deleteXrefFile( $pItem, $pXkeyExt )` — cleans up the physical file on a real hard-delete
  (`expunge=3`) of the row, distinguished from an Archive (soft-delete via `update` permission).
- `promoteImageToThumbnail( $pRelativePath )` — see above.

Each content class implements these against its own storage root and its own understanding of
which `item` values apply — the controller just calls them generically if they exist.

## Video playback

A film's own video file is a real `LibertyMime` attachment, played via the standard
`liberty/mime/video/player.tpl` (`<video>` tag fed `media_url`/`source_url`/`mime_type`/
`download_url`). An episode's own video file is **not** an attachment — it's a raw xref
`xkey_ext` path — so it has no equivalent serving route by default. `play_episode.php` fills this
gap: an `xref_id`-only, no-path-from-user-input streaming endpoint with real single-range HTTP
Range support (needed for a `<video>` element's own seek bar; without it, a large file often won't
even start playing in some browsers).

Deliberately a plain link rather than an inline `<video>` tag on any page listing episodes — a
real media library commonly mixes containers Chrome/Firefox play natively (`.mp4`) with ones they
don't reliably support inline (`.mkv`). A `<video src>` pointed at an unsupported container fails
silently with no visible error; a plain link lets the browser or OS decide (inline playback if it
can, an external player or a download prompt otherwise).

## Gallery view template dispatch

`view.php` → `display_fisheye_gallery_inc.php` → `$gContent->getRenderTemplate()` returns
`bitpackage:fisheye/view_gallery.tpl`, which dispatches to `gallery_views/{layout}/
fisheye_{layout}_inc.tpl`. Active layouts: `galleriffic` (dispatcher →`_1.tpl`/`_2.tpl`/`_5.tpl` by
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
- `gallery_breadcrumb_inc.tpl` — see "View/edit page pairs" above for its pretty-url limitation.
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

## Known limitations / not yet built

- Show/Artist/Composer as a genuine top-level browsable type (the `FoodDay`-pattern computed
  listing) — not built; a show today is a real gallery object, browsed by drilling down from a
  parent gallery rather than any kind of aggregated cross-show view.
- Bulk "scan the whole storage root and register anything new" import — not built; would need
  staging/batching to avoid a single request timing out against a real library's size.
- Season-level Plex metadata reload — deliberately not built; Plex's own data model has nothing
  at that level to fetch for this kind of content.
- A UI for managing the xref vocabulary itself (add/edit groups and items through bitweaver,
  rather than a hand-authored scheme applied via `LibertyXrefScheme::apply()`) — real, separate
  work, not started.
- Music/album/track build-out — `FisheyeAlbum` is registered with its own xref vocabulary, but no
  view/edit pages, Plex integration, or playback UI exist for it yet, unlike film/TV.
