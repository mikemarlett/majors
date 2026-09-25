# Majors & Degree Maps

Two small PHP apps sharing the `formshandlerdb` tables at Wichita State:

- **Degree Maps** — public, printable four-year plans (`/academics/majors/degree_maps/maps.php`),
  edited by advisors in `/academics/majors/degree_maps/admin/`.
- **Majors** — database-driven marketing pages for every academic program
  (`/academics/majors/index.php`), edited by marketing staff in `/academics/majors/_admin/`.

Both admins sit behind WSU single sign-on (CAS) and an approved-user list with roles.

## Layout

```
app/                     deployed OUTSIDE the docroot → /data/www/config/majors/
  bootstrap.php          autoloader + config + Kernel (no composer, no vendor/)
  config/app.php         defaults; app.local.php (box-wide) + app.<site>.php (per host) = overrides, gitignored
  src/                   Majors\ namespace: Support, Auth, View, DegreeMaps, Majors, Http
  templates/old|new/     the two site designs' chrome vocabulary (layout, classmap, partials)
  templates/shared/      data-bearing templates that work under either design
  dev/                   php -S router + stub site includes for local work
  tests/                 php -l runner and renderer/auth checks (no database needed)
  bin/                   migrate.php, add-user.php, images-audit.php, mirror-fetch.php (run from /data/www/config/majors)
  sql/                   003_migration_plain.sql = the migration for the mysql client; 001/002 reference + duplicate-map cleanup
docroot/academics/majors/  deployed INTO the docroot; three-line front controllers + assets
docs/                    baseline manifest of the pre-cleanup tree, image manifest
```

Every public URL that existed before still works: `maps.php?degree_map_id=N`
(and the older `?map_id=N`), `index.php?id=N`, `index.php?order=college&filter=online`.

### New: permanent "latest year" links

Degree maps are versioned by catalog year (a new year is a clone of the old
one). `maps.php?latest=N` — where N is the id of *any* year's version — always
redirects to the newest year of that same degree, so advisors can link once
and never update the link. Each map page also shows a catalog-year switcher
and a notice when an older version is being viewed. The family is
"same program page" or "same major + degree type + college".

## Roles

| role            | can                                                                 |
|-----------------|---------------------------------------------------------------------|
| `advisor`       | create/edit/clone degree maps for **their colleges**, future years only |
| `advisor_admin` | the same for **every college**; no Majors, no user management |
| `marketing`     | the Majors admin                                                    |
| `super_admin`   | everything, plus Manage Users, deleting maps, editing published years |
| `none`          | on the list but disabled                                            |

Maps for the current and past catalog years are read-only for advisors and
advisor admins; "Clone to Next Year" makes the editable copy. Nobody is auto-created at
sign-in: an unlisted person sees a "not on the access list" page with the
contact address from `config['site']['contact']`.

### College names

Maps and programs store the college *name*; users are scoped by the college
*id* in `majors_colleges`. Published maps are snapshots: a map keeps the name
its college had when it was published (the 2024–2025 "College of Applied
Studies" maps stay that way even though the college is the College of Education
again). `config['college_aliases']` maps former names onto the current row so
advisor scoping still works, and **Clone** writes the current name onto the
new year's copy. Add a line there whenever a college is renamed.

### Published years are read-only

Maps for the current and past catalog years cannot be edited by advisors; they
clone into next year instead. Super admins can still open the editor on a
published map (a red warning is shown) for the rare correction.

## Design switch

One app root can serve several sites on a box (www-dev and www-test both read
`/data/www/config`): `Config::load` layers `app.php`, `app.local.php`, then
`app.<site>.php` with the site taken from the request host (`www`, `www-dev`,
`www-test`; `MAJORS_SITE=` for CLI). That is where `design` differs.

`config['design']` is `old` (current wichita.edu, includes at
`_resources/includes`) or `new` (NewCity/Tailwind design on www-dev, includes at
`_resources/_theme/includes`). `View\Theme` captures the live header/footer
fragments; `View\Layout` resolves `templates/<design>/…` then
`templates/shared/…`. Shared templates never hard-code chrome classes — they ask
for tokens (`$t->cls('button.accent')`) that `templates/<design>/classmap.php`
maps. The degree map itself uses only `dm-*` classes and one stylesheet
(`assets/degree-map.css`) with the letter-portrait print rules, so it prints
the same under either design.

The `new` templates are a first pass; refine `templates/new/classmap.php`,
`layout.php` and `partials/` against the live design system on www-dev.

## Local development

```bash
cp app/config/app.local.example.php app/config/app.local.php   # set db.* and auth.provider=dev
php -S 127.0.0.1:8080 app/dev/router.php
open http://127.0.0.1:8080/academics/majors/degree_maps/maps.php
open "http://127.0.0.1:8080/academics/majors/auth/login.php?as=you@wichita.edu"
php app/tests/run.php
```

`app/tests/e2e.sh` runs the HTTP checks against the real local database, and
`app/tests/browser/inplace-drive.js` clicks through the in-place editor in
headless Chromium (see its header for setup).

The dev router serves `app/dev/stub-docroot` as the docroot (stand-in site
includes for both designs) and `docroot/academics/majors` at its real URL.

### Server mirror

The sandbox box also mirrors the servers' layout so the app runs exactly as it
will in production, with the real site includes, CSS and the CMS's own
section-nav renderer:

| Path                          | Stands in for                          | Contents |
|-------------------------------|----------------------------------------|----------|
| `/data/www/main/`             | www (`/data/www/main` on that box)     | `_resources/`, `calendar/`, `search/` |
| `/data/www/main-test/`        | www-test (`/data/www/main` there)      | `_resources/`, `academics/`, `calendar/` |
| `/data/www/main-dev/`         | www-dev (`/data/www/main-dev`)         | complete copy |
| `/data/www/config/`           | the shared config dir                  | `functions.php`, `mysql.php` (local creds), `phpCAS/` |

`<docroot>/academics/majors` in each mirror is a symlink to this repo's
`docroot/academics/majors`, `/data/www/config/majors` is a symlink to `app/`,
and the gitignored `docroot/academics/majors/approot.php` points there, so the
front controllers resolve the app the same way they do on a server. Site
detection uses the docroot name (`main` → www, `main-test` → www-test,
`main-dev` → www-dev) when the request's Host is only an IP address, and the
gitignored `app/config/app.www*.php` files pick the design per site.

```bash
php -S 0.0.0.0:8089 -t /data/www/main        # www       (current design)
php -S 0.0.0.0:8090 -t /data/www/main-test   # www-test  (current design)
php -S 0.0.0.0:8091 -t /data/www/main-dev    # www-dev   (new design)
```

Refresh a mirror by copying `_resources/` from the server; nothing under
`/data/www` is in this repo. Individual files can be pulled from the CMS
staging site with `app/bin/mirror-fetch.php <remote_dir> <dest,dest> <names…>`
(uses the Modern Campus workspace's `McClient` and its credentials; it reads
binaries through `GET /pages/content`, since `_resources/images/` on the
server is too large to copy whole).

## Majors data model (since 2026-09-24)

The CMS program pages under `/academics/majors/` are imported into the
database, which is now the source the Majors pages render from:

| table | holds |
|---|---|
| `majors_academic_programs` | one row per program, **stable ids** (degree maps point at them): name, credential (Major, Minor, Master's…), type (BS, MACC…), college/department (+ links), the Program Card (description, "Learn how…" buttons, hero image), meta tags, `basename` of the CMS page, `catalog_number` from the slug, `status` active/retired, CMS file date |
| (same table) | the graduate template's **Program Details**: `catalog_url`, `degree_title`, `credit_hours` (seeded from the catalog by `app/bin/majors-catalog-seed.php`), `modality`, `entry_terms`, `is_stem`, `coordinator_*` (marketing) |
| `majors_program_sections` | the page body in order: `teaser` (Curriculum, Careers, Admission…), `feature` (Inside the Program, with image), `similar`; each with headline, HTML body, links, image — or a `block_id` |
| `majors_content_blocks` | text that appeared verbatim on many pages, stored once (Applied learning at Wichita State: 268 pages; Making your graduate education affordable: 92; each college's Admission paragraph…). A section that points at a block shows the block, so **editing the block changes every page that uses it** |
| `majors_similar_programs` | from each page's Similar Programs card |
| `majors_programs_content` | the old flat row per program, kept in step by the importer because `ai-meta.php` on www reads it |

`app/bin/majors-import.php` does the import (parse the PCF source through the
MC API, resolve `{{f:…}}` links, match rows by basename then by catalog
number so renamed pages keep their id, retire the rest). Re-runs are safe.
`docs/majors-reconciliation-2026-09-24.md` records what the first import found.

## Rebuilding the theme stylesheet

The new design's compiled Tailwind only carries the utilities the theme's own
templates use. `tools/theme-build/build.sh` rebuilds it from the
`wichita-state-2024` checkout with this app's templates in the scan (see
[tools/theme-build/README.md](tools/theme-build/README.md)); the result is
what `/_resources/_theme/tailwind.css` on the site should be.

## Majors editor (marketing role)

Programs are addressed by their **page name** (`basename`, the CMS page's
name, unique): `index.php?program=aerospace_engineering_bs_101`. The old
`index.php?id=N` links redirect (301) to that address. A new program gets
`<name>_<type>` (e.g. `data_science_ms`), made unique with `_2`, `_3`… and
editable before it is created; Page settings can change it later (old links
then break, and the importer matches on it).

- `_admin/index.php` — the **control panel**: every program in one table.
  Sort any column; filter by search, level (undergraduate/graduate),
  credential, college, status and "needs attention" (no text, no photo, no
  alt text, no department, no sections, no similar programs, overlong
  headline, online); the view is bookmarkable (`?level=graduate&status=all`).
  Two things are edited right there: status (active/retired) and the similar
  programs (a popover with remove and search-to-add). Everything else is on
  the page.
- `_admin/program.php?program=<basename>` — the **in-place editor**: the
  public page itself (same templates and site chrome, both designs) with an
  editing layer. Click a headline or a paragraph and type (rich text uses
  CKEditor 5 in place: select text for bold/italic/link, the ⋮ handle at
  the left of a paragraph for lists and headings); click the program type,
  the college/department links, the buttons, the photo, the details box or
  the coordinator line and a small form opens next to it; empty optional
  things show a dashed placeholder only editors see. Every section has a tool
  strip (move up/down, add after, and a ⋯ menu with Customize / Use shared
  text / Remove); an "Add a section" bar closes the column; similar programs
  are removed with × on the card and added from the "+ Add a similar
  program" tile. **Page settings** (edit bar) holds what is not on the page:
  sort-as, page name, listing note, status, listing flags, search-engine
  description and keywords, catalog link. Every change saves as you go
  (Enter or click away; Esc cancels) and the server answers with the
  re-rendered page, so what you see is what the public gets; text changes get
  an **Undo** in the toast.
- Shared text (a section that uses a shared block) is badged "Shared text ·
  N pages". Clicking it asks whether to customize this page only (the
  default; the page gets its own copy) or to change the shared text on all
  N pages (asks again for big blocks). Removing a section or a similar
  program can be undone from the toast. New sections start blank and stay
  off the public page until they have text.
- Rich text is sanitised on save (paragraphs, emphasis, links, lists, small
  headings; no scripts, styles or unsafe link targets).
- The control panel's "Needs attention" filter flags missing text/photo/alt
  text/department/sections/similar programs and overlong headlines; its
  Credential filter offers the standard credentials plus "Other / not set"
  for the odd values still in the data (BUS, CFA, ENG/CED…).
- `_admin/blocks.php` — the shared content blocks themselves; a block in use
  cannot be deleted.
- Photos: the photo form accepts any address and can browse the photos
  already in `docroot/academics/majors/_images` (thumbnails come from
  `site.image_base` on boxes without the CMS-published files). Where new
  photos should be uploaded is still to be decided with marketing.
- Every save refreshes the legacy flat row so `ai-meta.php` on www stays
  right. Design notes: `docs/inplace-editor-design.md`.

## Next phase

- Refine `templates/new/` against the live www-dev design; flip `design` there.
- Retire the compatibility shims `docroot/.../maps_functions.php` and
  `majors_functions.php` once `/_resources/php/degree_maps_search_process.php`
  and `degree_search.php` are removed from the CMS.
- Photos: `app/bin/images-audit.php --archive` moved the 624 unreferenced files
  (55 MB) out of `_images/` on 2026-09-14 into `/srv/work/majors-backups/images-archive-20260914`;
  565 referenced files remain (see [docs/images-manifest.txt](docs/images-manifest.txt)).
  Deploy the trimmed `_images/` to the servers, or run the same command there.
  Two referenced files are missing everywhere: `PHS.jpg` and
  `HP_Nursing_Accelerated_Program_ITP.jpg` (those pages show broken images today).
- Two 2026-27 maps had been cloned twice (770/965, 789/791). Decision: keep
  the later id. Removed on the sandbox; run `app/sql/002_remove_duplicate_maps.sql`
  on www-test and www. Creating or cloning a second map for the same degree and
  year is now refused (the UI offers to open the existing one) and the admin
  listing tags any duplicates that do appear.
