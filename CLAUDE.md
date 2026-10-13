# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**Floor Maps**: a single-file WordPress plugin (`wpfloors.php`) for the Wallace Library site
(library.wheatoncollege.edu, WP Engine). It shows clean, label-free drawings of the library's five
floors, and staff add every label themselves in wp-admin. It is the fourth in-house plugin, after
`wphours` (Library Hours), `wprooms` (Reserve a Room) and the librarians plugin. Copy their
conventions, especially `Documents\wphours\CLAUDE.md`.

Name everything `wpfloors`: the file, the zip folder, the `wpfloors_` PHP prefix, the option, the
REST namespace and the CSS classes. Never use an `mcw` prefix, even though the sister plugins do.

Call them **floor maps**, never "floor plans": in code, UI text, docs and commit messages.

## Status

1.0.0 built 2026-10-13. The drawings were traced in photo pixel coordinates, so each floor's
`viewBox` is a crop of its photo. The Stacks archives board sits in a `translate(160 831)` group
that joins it at the match line. Tests: `php tests/run.php [preview-dir]`. It stubs WordPress, and
with a folder it writes preview pages you can screenshot with headless Chrome. Headless Chrome won't
go below about 500px wide, so check 320px inside a 320px iframe.

## Source drawings

`source/` holds the four images from the library's PowerPoint deck *Wallace Library Floor Plan
(August 2022)*. Each is a photo of an old architect's presentation board.

| File | Floor(s) |
|---|---|
| `main-level.png` | Main Level |
| `periodicals-level.png` | Periodicals Level |
| `stacks-level.png` | Stacks Level. Drawn in two pieces joined at a "MATCH LINE" |
| `fine-arts-mezzanines.png` | First Mezzanine (left) and Second Mezzanine (right) |

The printed room names in the photos are out of date. **Do not trace them, and do not add any
labels.** The user wants every floor to launch with no labels, so they can test adding and editing
labels as a regular staff editor. That includes the emergency details from the deck: exits, rescue
areas and kits.

## Agreed design

### Drawings
- There are **five floor maps**: Main Level, Periodicals Level, Stacks Level, First Mezzanine and
  Second Mezzanine. The default page order is the one in that list.
- Hand-trace walls, doors, stairs and elevator shafts from the photos into inline SVG, built into
  the plugin. The drawings have **no text**. They need to be accurate enough to find your way, not
  to scale.
- Correct the photos' perspective and skew by eye. Join the two Stacks pieces at the match line into
  one continuous drawing.
- Walls must have at least 3:1 contrast against the drawing background.
- Staff never edit the drawings. If Facilities later provides CAD files, they could replace the
  drawings without changing the data model.
- Check every drawing against its photo, side by side, for missing walls, doors or stairs. Show the
  user the comparison.

### Data
- Store everything in one option. Follow the `wphours` pattern: one option, and every write goes
  through a single sanitize function.
- The stored data is keyed by floor. Each label is
  `{id, name, type, note, x, y}`, with `x` and `y` as percentages of the drawing.
- `type` is exactly one of:
  - room or service
  - collection
  - restroom
  - elevator
  - stairs
  - exit
  - emergency rescue area
  - emergency kit
- `note` is optional, for example "Wait here for help if you can't use the stairs."

### Admin: wp-admin → Floor Maps
- The capability is `edit_pages`, the same as the sister plugins. A regular editor must be able to
  do everything.
- Choose a floor to see its drawing. **Click a spot to add a label.** A small form asks for name,
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
- Links to each floor at the top. Then, for each floor:
  - an H2 with the floor's name;
  - the drawing, with each label shown as a marker and its name;
  - the same labels as a list, grouped under these headings:
    - **Rooms and services**
    - **Collections**
    - **Restrooms, elevators and stairs**
    - **Emergency: exits, rescue areas and kits**
- Each list item includes its note.
- A floor with no labels shows its drawing and "Nothing listed on this floor yet." All five floors
  launch like this.
- Under each drawing, an "Open full-size map" link opens that floor's drawing on its own.
- Printing puts each floor on its own page, with its list.

### Accessibility (WCAG 2.1 AA, which the whole site must meet)
- **The list is the full text version.** The SVG is `role="img"`, with a short label such as
  "Main Level floor map. Everything shown is listed below." The labels inside the SVG are hidden
  from screen readers so nothing is read twice. Add a code comment explaining why, so nobody "fixes"
  it later.
- **Color never carries meaning.** That is a firm rule from the user. Each type gets its own marker
  shape with a letter in it, in black on white. A short legend in text sits under each drawing.
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
   `source/` out of the zip. Copy the zip to `C:\Users\thoma\Downloads`. `.gitignore` already
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
