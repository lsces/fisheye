# Fisheye Package — Reference Manual

How the package actually works today. For the history of *why* — decisions, bugs found, wrong
turns — see `CLAUDE.md`'s dated session log instead; this file only tracks current behaviour.

## What this is

A photo/media gallery package built on two base classes:

- **`FisheyeGallery`** — a container. Holds members (photos, films, other galleries) via
  `addItem()`/`loadImages()`, extends `LibertyMime` (so it also has its own optional attachment
  slot, used by real "collection"-style content — see below).
- **`FisheyeImage`** — a single item: a photo, or (via a phantom subclass) a film or a TV season.

Fisheye also covers real media cataloguing (film/TV/music libraries scanned from disk, backed by a
local Plex Media Server install as the metadata/artwork source) without a separate package — the
gap over plain photo galleries was just extra mime types, extra `gallery_views` layouts, and
per-content-type xref vocabulary, all of which the existing xref/gallery machinery already
supports.

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
| `FisheyeAlbum` | `FisheyeImage` | A music album | Yes — real thumbnail attachment (cover art), tracks are raw xrefs like Season's episodes |

Hierarchy: **Show → Season → Episode** / **Artist → Album → Track**; a Film stands alone
(single-level). Show/Artist/Composer as a genuine top-level browsable concept (a computed listing
with no `liberty_content` row of its own, same idea as `food`'s `FoodDay` pattern) is designed but
not built — for now, a show is just a real `FisheyeProgram` gallery holding `FisheyeSeason`
members.

**Flat single-season shows** — some shows (a one-off documentary registered as a show rather than a
film, e.g. to get real cast/episode metadata) have their episode file(s) sitting directly in the
show folder, no `Season 01/` subfolder at all. `load_program.php` treats this as an implicit
season using the sentinel folder name `'.'` (unambiguous — `scandir()` already skips it as a real
entry); `FisheyeSeason::registerFromDisk()` resolves that sentinel to "season dir == show dir" and
titles the season plainly `"<show> - Season 1"` rather than `"<show> - ."`.

**Deleting a show** (`FisheyeProgram::expunge()`, an override — not the shared
`FisheyeGallery::expunge()`, which only ever recurses into sub-*galleries*, never plain gallery
items like a season) cascades: every season is expunged first (which itself cleans up its own
episode xrefs and images via the normal `FisheyeImage`/`LibertyMime` expunge path), then the show's
own gallery/content rows. Scoped to `FisheyeProgram` deliberately, not the shared base class — a
season is never meaningfully linked into more than one show the way a photo can be linked into more
than one gallery, so there's no case here needing the "keep it if it's still in another gallery"
check `FisheyeGallery::expunge()`'s sub-gallery recursion already does.

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

**Single-season shows skip the season page entirely.** `view_program.php` checks the show's own
season count; when there's exactly one, it loads that season's episode/image xref data itself
(same shape `view_season.php` uses) and dispatches to `view_program_single_season.tpl` instead of
the normal season-grid template — no click-through to a dummy "Season 1". Layout: show
thumbnail/summary 50/50 on the left, the episode detail panel (swaps per-episode) on the right,
episode grid along the bottom. The real `FisheyeSeason` object still exists underneath (`edit_season.php`
still reachable directly if ever needed) — this is display-level only, not a storage change. The
episode-detail-panel and episode-grid+JS blocks are shared includes (`episode_detail_panels_inc.tpl`,
`episode_grid_inc.tpl`) used by both this template and `view_season.tpl`, not duplicated.

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

## Bulk import (`load_film.php` / `load_program.php`)

Discover-and-pick admin pages, capped (`LOAD_FILM_LIMIT`/`LOAD_PROGRAM_LIMIT`, both 20) — not a
"scan and register everything" tool. Each registration is cheap (a `store()`/gallery-link/Plex
backfill), the one genuinely expensive step (thumbnail generation) is never triggered here, only
lazily per item on first view.

- `load_film.php` — flat: pick a folder under `Films/` (or a real subfolder standing in for a
  collection), pick films from a checkbox list, `$pFetchImages` opt-in (a bulk 20-film import
  paying for N image downloads at once is a real cost worth choosing explicitly).
- `load_program.php` — two levels, matching the real show → season → episode structure: pick an
  unregistered show folder (registers the show, cheap, no images fetched yet — see the halt below),
  then pick season folders under it (each selected season is created, seeded with one real episode
  file, and immediately synced against Plex for its full episode list — no separate "fetch episodes
  later" step, since a season with no episodes at all isn't useful). Handles the flat single-season
  case (no `Season 01/` subfolder) via the `'.'` sentinel described above.

Both pages resolve their own "top level, not a real collection/show" gallery id via
`FisheyeGallery::getTopGalleryId( $pTitle )` (a plain title lookup against `fisheye_gallery`/
`liberty_content`) rather than a hardcoded, install-order-dependent gallery_id constant — the very
first two galleries created on a given install happen to be "Films"/"TV Shows", so a literal `1`/`2`
worked by coincidence but wasn't a safe assumption for another install.

**`FisheyeProgram::registerFromDisk()` halts before fetching metadata/images if there's no Plex
match** (case/spacing mismatches like Plex's own `"Dinnerladies"` vs an on-disk `"Dinner Ladies"`
folder are common, and a folder name genuinely can't hold a colon Plex's own title might have) —
the show record itself still gets created (cheap, and gives the manual-match tools below something
to attach a match to), but no metadata/image fetch runs until a match is actually confirmed,
automatic or manual. `load_program.tpl` surfaces this with a link straight to the show's edit page
to fix it.

**Manual Plex match** (`edit_program.php`, shown whenever `$gContent->hasPlexMatch()` is false) —
`FisheyeProgram::searchPlexShows( $pQuery )` runs a plain `LIKE` search against the local Plex
SQLite db (same one every other Plex lookup in this class already reads directly — no need for
Plex's own HTTP search API); picking a result calls `setPlexMatchOverride( $pMetadataItemId )`,
which stores it as a `plex_match` xref (`xkey` = the Plex `metadata_items.id`) that
`matchPlexShowMetadataItem()` checks first from then on, ahead of the automatic title lookup.
`plex_match` is a purely internal bookkeeping value — never shown through the generic xref grid, no
`liberty_xref_item` config row registered for it, read/written via plain direct SQL rather than the
generic `lookupXrefByItem()`/`loadXrefInfo()` helpers (both require that config row to exist).
Confirming a match immediately runs the metadata/image fetch that was held back by the halt above.

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

**The Images tab has its own group-tab override**, `templates/xref/view_images_group.tpl` (Film/
Season/Program all share the one file — identical to liberty's generic `list_xref.tpl` except the
Add link), which replaces the generic add-a-bare-row-then-edit-it flow with a real one-step upload
(`add_image_xref.php` + `FisheyeBase::addImageXrefFile()`) and, where supported, a "Grab Thumbnail
from Video" action (see below). **This only fires when `liberty_xref_group.template = 'images'`
for the `images` x_group row on each of the three content types** — that's a per-site DB config
value (same table the Xref Groups admin page itself writes to directly, no history/schema-file
tracking), not something schema/install files set, so a fresh install or another server needs it
applied by hand:
```sql
UPDATE liberty_xref_group SET template='images'
WHERE x_group='images' AND content_type_guid IN ('fisheyefilm','fisheyeseason','fisheyeprogram');
```
**Templates here can't call a bare PHP function inside `{if}`** (`{if method_exists(...)}` fails as
"unknown modifier" on this Smarty setup) — only a real method call on an object works. Capability
checks (`$gContent->supportsAddImage()`, `$gContent->canGrabVideoFrame()`) are real `FisheyeBase`
methods for exactly this reason, not a `method_exists()` call inlined into the template.

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
  (checked via an empty attachment slot, so a later manual override is never clobbered). **Season
  only**: if Plex genuinely has zero photos (no match, no `fisheye_plex_token` configured, or a
  real match with no artwork at all — not uncommon for a barebones single-episode entry), falls
  back to grabbing a video frame — see below.
- **Load Episodes** (season only) — pulls the season's full episode list from Plex, including each
  episode's own text metadata and its own Plex-generated screenshot thumbnail.

## Video frame-grab fallback

`\Bitweaver\Liberty\mime_film_grab_video_frame( $pSourceFile, $pDestJpegPath, $pSeekSeconds=60 )`
(`liberty/plugins/mime.film.php`) — ffmpegthumbnailer first, falling back to a plain `ffmpeg` seek-
and-grab. Originally built for a plain film's own attachment thumbnail
(`mime_film_get_thumbnail_url()`), factored out so fisheye content types with no attachment of
their own can call it directly against a video file.

`FisheyeBase::grabVideoFrameIntoImageXref( $pVideoFile )` — the shared "grab a frame from this
video and store it as a new `image` xref on this content object" engine (resize, next-`xorder`,
`storeXref()`). Two callers, both public and exposed as an on-demand "Grab Thumbnail from Video"
link on the Images tab (`$gContent->canGrabVideoFrame()` gates it — see above) as well as internally
by Reload Images' own automatic fallback:
- `FisheyeSeason::grabVideoFrameImage()` — grabs from its own seed episode file.
- `FisheyeProgram::grabVideoFrameImage()` — a show has no video of its own, so walks its seasons
  (`loadImages()`) for the first one with a usable episode file.

A manual click always grabs a fresh one (no "already has an image" check — a deliberate "add one
more", same as uploading via Add Image); the automatic fallback inside Reload Images checks first
and is a no-op once a real `image` xref already exists.

## Generic xref-file hooks (`liberty/edit_xref.php`)

The shared xref controller knows nothing about fisheye specifically — three `method_exists()`-
gated hooks let a content class handle its own file lifecycle for an xref row that references a
file:
- `replaceXrefFile( $pItem, $pXkeyExt, $pTmpPath )` — an uploaded file replaces what an xref row
  already references, in place (the row's own `xkey_ext` never changes). **Refuses when
  `$pXkeyExt` is empty** — correct for its actual job, but means a row with no file yet (e.g. one
  created via the old generic add-then-edit flow, before Add Image existed) can never be fixed
  through this route; use Add Image to create a fresh row instead of trying to "edit" a blank one.
- `deleteXrefFile( $pItem, $pXkeyExt )` — cleans up the physical file on a real hard-delete
  (`expunge=3`) of the row, distinguished from an Archive (soft-delete via `update` permission).
- `promoteImageToThumbnail( $pRelativePath )` — see above.

Each content class implements these against its own storage root and its own understanding of
which `item` values apply — the controller just calls them generically if they exist.

**`FisheyeBase::addImageXrefFile( $pTmpPath, $pOriginalName )` is a related but separate
mechanism** — not one of `edit_xref.php`'s three hooks (it *creates* a new row rather than acting
on an existing one), called instead from the dedicated `add_image_xref.php` page the Images tab's
own group-tab override links to. Guarded by `method_exists( $this, 'getImageStorageRoot' )`
internally, and by `$gContent->supportsAddImage()` for the template-visible check (see above).

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
- Fully-automatic "scan the whole storage root and register anything new, no picking" import —
  `load_film.php`/`load_program.php` (see above) cover discover-and-pick, capped at 20 at a time;
  nothing yet walks a whole library unattended.
- Season-level Plex metadata reload — deliberately not built; Plex's own data model has nothing
  at that level to fetch for this kind of content.
- A UI for managing the xref vocabulary itself (add/edit groups and items through bitweaver,
  rather than a hand-authored scheme applied via `LibertyXrefScheme::apply()`) — real, separate
  work, not started.
- Music/album/track build-out — `view_album.php`/`edit_album.php`/`load_album.php`/
  `play_track.php` exist, `FisheyeAlbum::registerFromDisk()` reads embedded ffprobe tags as the
  primary metadata source and `reloadPlexImages()` covers Plex cover/alternate-image fetch, same
  shape as Film/Season/Program. Still not built: `load_collection.php`/`load_discography.php`
  bulk importers (single-CD registration only so far, one folder at a time), Discogs as an actual
  image source (the `discogs` xref item is just an external link today, same as `mbid`), and the
  Artist/Composer top-level browsable concept mentioned above.
- A one-off single-video show is currently registered as a full show/season/episode, faking an
  `S01E01`-style episode number just to fit the model — a plain "Videos" gallery (load_film.php-
  style, no season/episode modeling at all) would fit these better. Not started.
