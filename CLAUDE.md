# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**Floor Maps**: a single-file WordPress plugin (`wpfloors.php`), built for the Wallace Library site
(library.wheatoncollege.edu, WP Engine) but usable by any site. Staff add floors, upload an SVG map
for each one, and add every label themselves in wp-admin. It is the fourth in-house plugin, after
`wphours` (Library Hours), `wprooms` (Reserve a Room) and the librarians plugin. Copy their
conventions, especially `Documents\wphours\CLAUDE.md`.

Name everything `wpfloors`: the file, the zip folder, the `wpfloors_` PHP prefix, the option, the
REST namespace and the CSS classes. Never use an `mcw` prefix, even though the sister plugins do.

Call them **floor maps**, never "floor plans": in code, UI text, docs and commit messages.

## Status

2.0.0 (2026-10-13). The plugin ships **no maps**: a fresh install has no floors, and each floor's map
is an SVG the site uploads. The user asked to remove the built-in Wallace drawings, and that's in
git history if ever needed (1.0.0 to 1.1.0, traced from photos that were in `source/`). Standalone
SVG exports of those five drawings were handed to the user in `Downloads\wallace-floor-maps\`.

- The option is `{floors: [{slug, name, image}], labels: {slug: [...]}}`, where `image` is the
  attachment ID of the floor's SVG. Data without a `floors` key (1.0) reads as no floors.
- A floor's slug never changes after its first save, because labels, anchors and full-size URLs
  depend on it.
- SVG only: no other formats, and no conversions. The plugin lets `edit_pages` users upload `.svg`.
  `wpfloors_svg_problem()` refuses an upload, without changing it, if it contains scripts, event
  handlers, entities, external links or CSS that loads files, or has no viewBox or size.
- Maps are drawn with SVG `<image>`, which never runs scripts, and the markers go on top.
- Map URLs carry `?v=<filemtime>` (2.22.0, `wpfloors_image()`): uploads are cached for a year and
  Cloudflare ignores no-cache, so a map edited in place would otherwise stay stale.
- Tests: `php tests/run.php [preview-dir]`. The stubs are in `run.php` and the cases in
  `tests/cases.php`. With a folder, it writes preview pages you can screenshot with headless Chrome.
  Headless Chrome won't go below about 500px wide, so check 320px inside a 320px iframe.

## Agreed design

### Maps
- Staff add, rename, reorder and delete floors, and choose one SVG map per floor from the Media
  Library. The page order is the order in the Floors table.
- A map should have **no text** (every name is a label) and walls at least 3:1 against the
  background. The admin screen says so, but the plugin can't check it.

### Data
- Store everything in one option. Follow the `wphours` pattern: one option, and every write goes
  through a single sanitize function.
- Labels are keyed by floor slug. Each label is
  `{id, name, type, note, number, show, pos, show_icon, x, y}`, with `x` and `y` as percentages of the map. `show` is
  "Show name on the map" (2.1.0). It defaults by type: on for rooms, services, studios and collections,
  off for the rest. The list always shows every name. `pos` (2.17.0) is where the name sits: top, right,
  bottom or left; without one, left when x > 65, otherwise right. `show_icon` (2.20.0, default
  on) hides the icon or characters on the map; the name is then centered on the spot, and the key
  lists only types whose icons show on that floor's map.
- `type` is exactly one of:
  - room
  - service (added in 2.4.0, when "room or service" was split in two)
  - printer (added in 2.6.0; listed under "Rooms and services")
  - studio (added in 2.7.0; listed under "Rooms and services")
  - checkout (added in 2.8.0; listed under "Rooms and services")
  - computers (added in 2.10.0; listed under "Rooms and services")
  - office (added in 2.12.0; listed under "Rooms and services"): no icon; the marker is the
    label's `number` (up to 5 letters, digits and hyphens since 2.15.0; '' for types without `chars`;
    "#" when empty), always name size (2.21.0, at the user's request; never shrink them).
    `wpfloors_chars_width()` estimates their width (digits 0.6, M/W 1, hyphen 0.4, other letters
    0.75) so a name beside them clears them. In the list they're HTML text, not SVG
  (A "text only" type was added in 2.7.0 and removed in 2.8.0 at the user's request.)
  - collection (since 2.14.0 it can have up to 5 characters in `number` too, shown in place of the
    book icon; with none it keeps the icon)
  - restroom (gender-neutral toilet icon since 2.16.0, at the user's request)
  - elevator
  - exit (shown as "Entrance and exit" since 2.9.0; listed with restrooms and elevators)
  (A "stairs" type existed until 2.11.0, when it was removed at the user's request.)
  - emergency-exit (added in 2.9.0; listed under Emergency)
  - emergency rescue area
  - emergency kit
- `note` is optional, for example "Wait here for help if you can't use the stairs."

### Admin: wp-admin → Floor Maps
- The capability is `edit_pages`, the same as the sister plugins. A regular editor must be able to
  do everything.
- Floors section: name, SVG map, Move up/down, Delete. Then choose a floor to see its map. **Click a spot to add a label.** A small form asks for name,
  type and note.
- A table under the drawing lists that floor's labels, with Edit, Delete and Move. Move means click
  "Move", then click a new spot.
- Saving uses an explicit Save button that posts to a REST endpoint, like `wphours`. It does not
  rely on the block editor or its autosave.
- The admin page is the only interactive part, so it must be keyboard-usable as well. Give it a way
  to place a label without a mouse, for example x/y fields in the form.

### Public page: `[floor_maps]` shortcode
- The shortcode goes on a new normal page, **About → Locations and places → Floor Maps** (`/about/places/floor-maps/`, page ID 2203), which the
  user or Claude creates.
- **Render on the server.** The public page needs no JavaScript.
- No links to each floor at the top (removed in 2.5.0 at the user's request; don't add them
  back). For each floor:
  - an H2 with the floor's name;
  - the drawing, with each label shown as a marker and its name;
  - the same labels as a list, grouped under these headings:
    - **Rooms and services**
    - **Collections**
    - **Restrooms, elevators and entrances** (renamed in 2.9.0 and 2.11.0)
    - **Emergency: exits, rescue areas and kits**
- Each list item is its icon, name and note. The type isn't written on screen (the key spells types
  out); in mixed groups it is screen-reader-only text, because the icons are aria-hidden (2.10.1).
- A floor with no labels shows its map and "Nothing listed on this floor yet." A floor whose SVG is
  missing shows "The map for this floor isn't available yet." and its list. With no floors, the
  shortcode says "No floor maps yet." 
- Under each drawing, an "Open full-size map" link opens that floor's drawing on its own.
- Printing puts each floor on its own page, with its list.

### Accessibility (WCAG 2.1 AA, which the whole site must meet)
- **The list is the full text version.** The SVG is `role="img"`, with a short label such as
  "Main Level floor map. Everything shown is listed below." The labels inside the SVG are hidden
  from screen readers so nothing is read twice. Add a code comment explaining why, so nobody "fixes"
  it later.
- **Color never carries meaning.** That is a firm rule from the user. Each type gets its own icon, in
  black with a white outline, all 1.5 units tall (name text is 1; 2.19.0, after trying 2 and 1.75). Markers have no shapes around them (removed in 2.13.0 as too
  cluttered; don't add them back). A short legend in text sits under each drawing. The
  icons are Font Awesome Free 6.7.2 solid (CC BY 4.0), embedded as path data in `wpfloors_types()`.
  Never load Font Awesome's CSS or JS, and keep the credit in README.
- Labels are real SVG text, not images of text. They need at least 4.5:1 contrast, with a white
  outline so they stay readable over walls.
- Phones: the drawing scales to the page width and can be pinch-zoomed. The page must never scroll
  sideways at 320px wide.
- Nothing on the public map is interactive: no tab stops and no hover-only information.

### Left out on purpose
Don't add any of these unless the user asks:
- search;
- highlighting rooms;
- routes between rooms;
- a "confirmed" step for emergency rows;
- importing the deck's labels;
- accepting image formats other than SVG, or converting files;
- an A–Z index;
- a separate emergency page.

## Build and verify

- Write it as plain PHP and vanilla JS, with no dependencies, build step or minification. Copy the
  `wphours` conventions:
  - nowdoc (`<<<'HTML'`) for markup and JS with no PHP variables;
  - config passed to inline scripts via `wp_json_encode()`;
  - ES5-style JS.

  Pick heredoc or nowdoc on purpose. A heredoc (`<<<HTML`) around JS makes PHP try to interpolate
  every `$` in the script.
- A portable PHP CLI is at `C:\Users\thoma\bin\php\php.exe`. Run `php -l` on every change:
  `C:\Users\thoma\bin\php\php.exe -l wpfloors.php`
- Test sanitize, the REST handlers and the shortcode output against a stub harness that mocks the
  WordPress functions. Include junk input and stray HTML in names and notes.
- After the user installs it, check the live `/about/places/floor-maps/`:
  - run a wpa11y rescan and axe, with no labels and with a few test labels;
  - check it at 320px and at 400% zoom;
  - check print preview.

  The user is logged in to Chrome, so the Claude in Chrome tools can use wp-admin. Never type a
  password; the user logs in.
- Purge the WP Engine cache (the "Purge Cache" item in the wp-admin bar) before checking what
  patrons see.

## Release workflow (same as wphours and wprooms)

Every code change ships as a versioned release, not just a commit:
1. Bump `Version:` in the plugin header. Use SemVer, starting at 1.0.0 for the first usable build.
2. Add a dated entry to `## Changelog` in `README.md`.
3. Commit and push to `main` on `github.com/tfscharff/wpfloors`. Commit and push after every change.
4. Create the release with `gh release create vX.Y.Z`, attaching a zip whose top-level folder is
   `wpfloors/`. That folder contains `wpfloors.php`, `README.md` and `LICENSE`. Leave
   `tests/` out of the zip. Copy the zip to `C:\Users\thoma\Downloads`. `.gitignore` already
   ignores `build/` and `*.zip`, so stage the build there (PowerShell 7):
   ```powershell
   Remove-Item -Recurse -Force build -ErrorAction SilentlyContinue
   New-Item -ItemType Directory -Force build\wpfloors | Out-Null
   Copy-Item wpfloors.php, README.md, LICENSE build\wpfloors\
   Compress-Archive -Path build\wpfloors -DestinationPath build\wpfloors.zip -Force
   gh release create vX.Y.Z build\wpfloors.zip --title vX.Y.Z --notes "..."
   Copy-Item build\wpfloors.zip $HOME\Downloads\
   ```
5. The user installs it at Plugins → Add New → Upload Plugin → Replace current.

## Site rules that apply here

- Edit this repo locally, then commit and push. Never edit it through the GitHub API.
- The theme folder `Documents\wheatonlibrary` live-syncs to production. Don't put anything for this
  plugin there.
- Never import `mysql.sql` anywhere.
- Keep designs simple and explainable. Do exactly what was asked, and raise any conflict instead of
  redesigning. Be concise with the user.
