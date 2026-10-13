# Floor Maps

A WordPress plugin that shows accessible floor maps of a library or any building. Staff set up the
floors and add every label (rooms, services, collections, restrooms, elevators, stairs, exits,
emergency rescue areas and kits) in wp-admin, without touching code. Built for the Wallace Library
site, but any site can use its own maps.

- **wp-admin → Floor Maps → Floors**: add, rename, reorder and delete floors. Each floor uses a
  built-in drawing (the five Wallace Library floors) or an image from the Media Library.
- **wp-admin → Floor Maps → Labels**: choose a floor, then click the map to add a label. Edit, move
  or delete labels from the table under the map.
- **`[floor_maps]`**: put this shortcode on a page to show every floor. Each floor shows the map
  with its labels, followed by the same information as a text list.

Color never carries meaning. Each type of label has its own marker shape and letter, and every label
also appears in the text list.

## Status

1.1.0 is the current release. A new site starts with no floors. A site upgrading from 1.0 keeps
the five built-in Wallace Library floors and their labels.

## Notes for staff

- **Using your own map image:** upload a clean drawing of the floor with no words on it, with dark
  walls on a light background. Put every room name on the map as a label instead, so it also
  appears in the text list that screen reader users rely on. PNG, JPG or GIF works. WordPress
  blocks SVG uploads by default.
- Deleting a floor deletes its labels when you click Save. Reload the page before saving to undo.

- Nothing changes on the website until you click **Save**.
- To place a label without a mouse, use **Add a label** (or **Edit**) and type the position in
  **Across** and **Down** (percent of the map from the left and from the top).
- After saving, purge the WP Engine cache ("Purge Cache" in the admin bar) if the public page
  doesn't show your change yet.
- **Open full-size map** shows one floor on its own page (`/?wpfloors_map=main`, and so on).

## Development

Plain PHP and vanilla JS, no build step. Run the tests (stubbed WordPress functions) with:

```
php -l wpfloors.php
php tests/run.php            # add a folder path to also write preview HTML pages there
```

## Install

Download the zip from the latest GitHub release. In wp-admin, go to Plugins → Add New → Upload
Plugin, choose the zip, then choose **Replace current** if it's already installed.

## Changelog

### 1.1.0 (2026-10-13)

- Floors are now set up in wp-admin: add, rename, reorder and delete floors, and give each one a
  built-in drawing or an image from the Media Library. Other sites can use their own maps.
- Sites upgrading from 1.0 keep the five Wallace Library floors and their labels.
- A floor whose image is missing still shows its list, with a note that the map isn't available.

### 1.0.1 (2026-10-13)

- Print: smaller map so a floor's key and list fit on the same page more often; the key no longer
  splits across pages.

### 1.0.0 (2026-10-13)

- First release: hand-traced drawings of the Main Level, Periodicals Level, Stacks Level (both
  boards joined at the match line), First Mezzanine and Second Mezzanine, with no labels.
- wp-admin → Floor Maps: click the map to add a label; edit, move and delete from the table; Save.
- `[floor_maps]` shortcode: server-rendered maps, a key, and a text list of every label.
