# Floor Maps

A WordPress plugin for the Wallace Library site that shows accessible maps of the library's floors.
Library staff add and edit every label (rooms, services, collections, restrooms, elevators, stairs,
exits, emergency rescue areas and kits) in wp-admin, without touching code.

- **wp-admin → Floor Maps**: choose a floor, then click the map to add a label. Edit, move or delete
  labels from the table under the map.
- **`[floor_maps]`**: put this shortcode on a page to show every floor. Each floor shows the map
  with its labels, followed by the same information as a text list.

Color never carries meaning. Each type of label has its own marker shape and letter, and every label
also appears in the text list.

## Status

1.0.0 is the first usable build. All five floors launch with no labels.

## Notes for staff

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

### 1.0.0 (2026-10-13)

- First release: hand-traced drawings of the Main Level, Periodicals Level, Stacks Level (both
  boards joined at the match line), First Mezzanine and Second Mezzanine, with no labels.
- wp-admin → Floor Maps: click the map to add a label; edit, move and delete from the table; Save.
- `[floor_maps]` shortcode: server-rendered maps, a key, and a text list of every label.
