# Floor Maps

A WordPress plugin that shows accessible floor maps of a library or any building. Staff upload an
SVG map for each floor and add every label (rooms, services, collections, restrooms, elevators,
entrances, exits, emergency rescue areas and kits) in wp-admin, without touching code.

- **wp-admin → Floor Maps → Floors**: add, rename, reorder and delete floors, and choose each
  floor's SVG map from the Media Library.
- **wp-admin → Floor Maps → Labels**: choose a floor, then click the map to add a label. Edit, move
  or delete labels from the table under the map.
- **`[floor_maps]`**: put this shortcode on a page to show every floor. Each floor shows the map
  with its labels, followed by the same information as a text list.

Color never carries meaning. Each type of label has its own icon (offices and collections can show up to 5 characters instead),
and every label
also appears in the text list.

## Status

2.16.0 is the current release. A fresh install has no floors and no maps.

## Your SVG maps

- **SVG only.** Upload a `.svg` file for each floor. The plugin doesn't take other formats and
  doesn't convert anything. It lets editors upload SVGs, which WordPress normally blocks.
- **No words on the map.** Add every name as a label instead, so it appears in the text list that
  screen reader users rely on.
- **Dark walls on a light background,** at least 3:1 contrast.
- The SVG needs a `viewBox` (or a width and height), which normal exports from Illustrator,
  Inkscape or CAD tools include.
- For security, uploads are refused if the SVG contains scripts, event handlers, entity
  declarations, links to other files or pages, or CSS that loads other files. If an upload is
  refused, export the drawing again as a plain SVG.

## Notes for staff

- Nothing changes on the website until you click **Save**.
- **Show name on the map:** each label has this checkbox. It's on by default for rooms, services,
  studios, collections, offices and entrances, and off for printers, checkouts, computers, restrooms,
  elevators, emergency exits, rescue areas and kits, whose symbol says what they are. Turn
  it off wherever names would overlap. Every name is always in the list under the map.
- Deleting a floor deletes its labels when you click Save. Reload the page before saving to undo.
- To place a label without a mouse, use **Add a label by typing its position** (or **Edit**) and type the position in
  **Across** and **Down** (percent of the map from the left and from the top).
- After saving, purge your site's page cache if the public page doesn't show your change yet.
- **Open full-size map** shows one floor on its own page (`/?wpfloors_map=<floor>`).

## Development

Plain PHP and vanilla JS, no build step. Run the tests (stubbed WordPress functions) with:

```
php -l wpfloors.php
php tests/run.php            # add a folder path to also write preview HTML pages there
```

## Credits

Marker icons are from [Font Awesome Free](https://fontawesome.com) 6.7.2 (solid style), licensed
[CC BY 4.0](https://fontawesome.com/license/free). Copyright 2024 Fonticons, Inc.

## Install

Download the zip from the latest GitHub release. In wp-admin, go to Plugins → Add New → Upload
Plugin, choose the zip, then choose **Replace current** if it's already installed.

## Changelog

### 2.16.0 (2026-10-14)

- **Restroom** uses Font Awesome's gender-neutral toilet icon in place of the man and woman.
- The restroom and elevator icons are 10% smaller.

### 2.15.0 (2026-10-14)

- Office numbers and collection characters can include hyphens, e.g. "ND-NK" (still up to 5
  characters).

### 2.14.0 (2026-10-14)

- **Collections** can show up to 5 letters and digits, e.g. a call number range like "ND", in place
  of the book icon, the same way offices show their number. Choose Collection as the type and fill
  in **Characters**. A collection with none keeps the book icon. Screen readers hear them in the
  list ("Collection ND").

### 2.13.0 (2026-10-14)

- Markers no longer have shapes around them: each label is just its icon, or for an office its
  number, in black with a thin white outline so it stays readable over walls. Icons are a little
  larger. An office with no number shows "#", which is also its symbol in the key.

### 2.12.0 (2026-10-14)

- New **Office** label type: a folder-shaped marker with the office's number inside it, in place of
  an icon. Choose Office as the type and an **Office number** field appears (up to 5 letters and
  digits, e.g. "214" or "2M201"). Longer numbers shrink to fit. Listed under "Rooms and services",
  name shown on the map by default; screen readers hear the number in the list ("Office 214").

### 2.11.0 (2026-10-14)

- Removed the **Stairs** label type. Any stairs labels no longer show, and are deleted the next
  time the floor is saved.
- "Restrooms, elevators, stairs and entrances" is now "Restrooms, elevators and entrances".

### 2.10.1 (2026-10-14)

- The lists under each map no longer spell out the type in each row ("Stairs: East stairs"). Each
  row is its icon and name; the key spells out the types. Screen readers still hear the type, since
  they skip the icons.

### 2.10.0 (2026-10-14)

- New **Computers** label type: Font Awesome computer icon in a house-shaped marker, listed under
  "Rooms and services", symbol only on the map by default.

### 2.9.0 (2026-10-14)

- **Exit** is now **Entrance and exit**, for everyday doors. Same icon and shape; it's listed under
  "Restrooms, elevators, stairs and entrances" (renamed from "Restrooms, elevators and stairs") and
  shows its name on the map by default. Labels saved before 2.9.0 keep their own setting.
- New **Emergency exit** type for emergency-only doors: Font Awesome running-person icon in a
  starburst, listed under "Emergency: exits, rescue areas and kits", symbol only on the map by
  default.

### 2.8.0 (2026-10-14)

- New **Checkout** label type: Font Awesome barcode icon in a wide rectangle, listed under "Rooms
  and services", symbol only on the map by default.
- Removed the **Text only** type added in 2.7.0. No site was using it.
- Editor: each label's name now lines up with the rest of its row in the labels table.

### 2.7.0 (2026-10-14)

- New **Studio** label type: Font Awesome headphones icon in a parallelogram, listed under "Rooms
  and services", name shown on the map by default.
- New **Text only** label type: just its name on the map, centered on the spot, with no marker or
  icon. It's always listed under "Rooms and services" and isn't in the key.

### 2.6.0 (2026-10-13)

- New **Printer** label type: Font Awesome print icon in a trapezoid. Listed under "Rooms and
  services"; shows only its symbol on the map unless you turn its name on.

### 2.5.0 (2026-10-13)

- The shortcode no longer adds links to each floor above the maps. The page starts with the first
  floor's heading.

### 2.4.0 (2026-10-13)

- "Room or service" is now two types: **Room** (door icon, circle) and **Service** (service-desk
  bell icon, badge shape). Both are listed under "Rooms and services". Labels saved before 2.4.0
  become Rooms; edit any that are services and change their type.
- (2.3.0 was released and withdrawn the same day, so it's skipped.)

### 2.2.0 (2026-10-13)

- Markers show a Font Awesome icon in place of a letter: door (room), book
  (collection), restroom, elevator, stairs, person walking to an arrow (exit), wheelchair
  (emergency rescue area) and first-aid kit (emergency kit). Each type keeps its own shape.
- Markers are a little larger so the icons stay legible; names stay the same size.

### 2.1.1 (2026-10-13)

- The Add a label button is now **Add a label by typing its position**, with "Or click the map."
  next to it. Clicking the map is the usual way; the button is the keyboard way.

### 2.1.0 (2026-10-13)

- New "Show name on the map" checkbox for each label, so crowded maps can show just the symbol.
  It's on by default for rooms, services and collections, and off for the other types. Labels
  saved before 2.1.0 follow those defaults. The list under the map always shows every name.

### 2.0.0 (2026-10-13)

- The plugin no longer ships any maps. A fresh install has no floors; each floor's map is an SVG
  you upload. The built-in Wallace Library drawings are gone.
- SVG only: editors can upload `.svg` files, and unsafe SVGs are refused at upload.
- Floors from 1.1.0 keep their names and labels but need an SVG map. Data from 1.0.0 isn't carried
  over.

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
