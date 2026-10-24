# Fisheye Package — Developer Reference

## Gallery defaults: where the admin settings are stored

`admin/admin_fisheye_inc.php` stores the **Default Gallery Display Settings** form in `kernel_config`
(package `fisheye`). New galleries read them back in `FisheyeGallery::verifyGalleryData()` and
`getLayout()`, so the stored names must match exactly:

| Form field | `kernel_config` name | Read by |
|---|---|---|
| `default_gallery_pagination` | `default_gallery_pagination` | `FisheyeGallery::getLayout()` |
| `rows_per_page` | `fisheye_gallery_default_rows_per_page` | `verifyGalleryData()` |
| `cols_per_page` | `fisheye_gallery_default_cols_per_page` | `verifyGalleryData()` |
| `galleriffic_style` | `fisheye_default_galleriffic_style` | admin and edit templates |
| `total_per_page`, `lines_per_page` | same names | admin template |

- The form field names are short; the stored names carry a prefix. Storing a field under its bare name saves
  without error but changes nothing for new galleries.
- **`kernel_config.config_name` holds 40 characters.** `fisheye_gallery_default_galleriffic_style` is 41, so
  saving it raises a string-truncation error; that setting uses the shorter name above. Check the length of
  any new setting name.
- A gallery's own rows and columns are stored in `fisheye_gallery`; the gallery's layout is the
  `gallery_pagination` preference, falling back to the configured default. A registered grid layout
  (`getGridPaginationTypes()`) overwrites rows and columns with its own on every save.
- Posting this form with only some fields switches the omitted checkboxes off (`simple_set_toggle`); test it
  with the complete form.
