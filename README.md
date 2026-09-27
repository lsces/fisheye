# Fisheye

A [Bitweaver](https://github.com/lsces/bitweaver) photo gallery package — galleries of images with
several browsing layouts, gallery and per-image permissions, image upload/rotate/resize, comments.

**Status**: mature, in production use.

[`fisheyemedia`](https://github.com/lsces/fisheyemedia) extends this package's gallery/image
content types to catalogue a film/TV/music library (Plex-backed metadata/artwork) — a separate
package, so a plain photo-gallery install never gets those content types registered. Fisheye itself
has no knowledge of it beyond a generic extension point (`registerService()`/`getServiceValues()`
for gallery layouts, and polymorphic `getDisplayUrl()`/`getEditUrl()` overrides for content types)
that any package could use the same way.

## What it does

- Several browsing layouts (grid, flow, paginated list, and others), selectable per gallery
- Gallery and per-image permissions, image upload/rotate/resize, comments
- A generic xref/metadata framework (via [`liberty`](https://github.com/lsces/liberty)) available
  to any gallery or image for structured, per-content-type vocabulary

See [`MANUAL.md`](MANUAL.md) for the full current architecture.

## Requirements

- [Bitweaver](https://github.com/lsces/bitweaver) 5.x
- [`liberty`](https://github.com/lsces/liberty) package — fisheye's gallery/image content types are
  built on Liberty's generic content/xref framework
- An `image_processor` configured (`gd` or `imagick`) for thumbnail generation

Since this package isn't through a stable install/upgrade cycle yet, see `MANUAL.md` in this repo
for the current schema-deployment approach if you're installing it fresh.
