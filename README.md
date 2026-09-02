# Fisheye

A [Bitweaver](https://github.com/lsces/bitweaver) photo/media gallery package — galleries of
images with several browsing layouts, extended to also catalogue a film/TV/music library, currently
bootstrapped from a local [Plex Media Server](https://www.plex.tv/) install as a convenient,
already-populated metadata/artwork source.

**Status**: the photo-gallery side is mature and in production use. The media-cataloguing
extension (film/TV, with music planned) is early, active development, personal use — built
primarily for the author's own media library.

## Why the media extension exists

A media library scanned from disk needs the same things a photo gallery already has: browsable
thumbnail grids, per-item detail pages, and structured metadata (genre, cast, external links).
Rather than a separate package duplicating that machinery, fisheye's existing gallery/xref system
was extended to cover it — the actual gap over plain photo galleries was just a handful of new
content types (a film, a TV season, a TV show) and their own metadata vocabulary, both of which the
existing framework already supports generically.

Plex isn't required to use fisheye's photo-gallery features at all, and even for the media side it
should be thought of as a *source*, not a dependency — every reload action copies its data
(title, genre, cast, description, poster/backdrop images, external IDs) into fisheye's own storage
rather than reading from Plex live, so nothing here actually depends on Plex staying installed
once a library's been backfilled. It's used purely because it's a convenient, already-populated
place to backfill from instead of typing all of that in by hand — the intent is to switch it off
entirely once real local metadata/artwork exists for everything it's fed.

## What it does

- **Photo galleries** — several browsing layouts (grid, flow, paginated list, and others), gallery
  and per-image permissions, image upload/rotate/resize, comments
- **Film cataloguing** — a film as its own content type (title, genre/director/writer/cast,
  content rating, duration, external links), backed by its own video file; a "Reload Metadata"
  action backfills all of that from a local Plex library in one click, and a "Reload Images" action
  fetches alternate poster/backdrop artwork
- **TV show cataloguing** — a show/season/episode hierarchy, each level with its own metadata where
  Plex actually has it (a season inherits nothing invented — Plex's own data model has real facts
  at the show and episode level, not the season level, and the UI reflects that rather than
  synthesizing something that isn't there); a "Load Episodes" action pulls a season's full episode
  list including each episode's own cast/rating/duration and screenshot thumbnail
- **Direct playback links** for episode video files with real HTTP Range support, rather than
  requiring a separate media player for the underlying files

See [`MANUAL.md`](MANUAL.md) for the full current architecture — the content-type hierarchy, how
storage roots and Plex matching work, and the complete list of what isn't built yet.

## What's planned

- Music/album/track cataloguing, mirroring the film/TV build-out (the content type is registered
  but has no view/edit pages or Plex integration yet)
- A "Show"/"Artist" browsing level that isn't itself a stored record — computed live from its
  seasons/albums, rather than the real gallery object a show currently has to be
- A bulk "scan the whole library and register anything new" importer, staged/batched for a
  real-sized library rather than one request per item
- Managing the metadata vocabulary (which fields exist per content type) through a bitweaver admin
  UI, rather than a hand-authored scheme file applied once

## Requirements

- [Bitweaver](https://github.com/lsces/bitweaver) 5.x
- [`liberty`](https://github.com/lsces/liberty) package — fisheye's gallery/image content types and
  the media extension's own metadata vocabulary are both built on Liberty's generic content/xref
  framework
- An `image_processor` configured (`gd` or `imagick`) for thumbnail generation
- For the media extension's backfill actions only, and only until a library's been backfilled: a
  local Plex Media Server install, with its library database path and (for artwork/external-ID
  lookups specifically) an API token configured — not needed at all once metadata/artwork already
  exists locally for everything you care about

Since this package isn't through a stable install/upgrade cycle yet, see `MANUAL.md` in this repo
for the current schema-deployment approach if you're installing it fresh (`CLAUDE.md` is a dated
development log, not a reference — useful for *why* something's built the way it is, not *how* to
set it up).
