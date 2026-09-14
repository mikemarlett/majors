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
  config/app.php         defaults; app.local.php (gitignored) = per-server overrides
  src/                   Majors\ namespace: Support, Auth, View, DegreeMaps, Majors, Http
  templates/old|new/     the two site designs' chrome vocabulary (layout, classmap, partials)
  templates/shared/      data-bearing templates that work under either design
  dev/                   php -S router + stub site includes for local work
  tests/                 php -l runner and renderer/auth checks (no database needed)
docroot/academics/majors/  deployed INTO the docroot; three-line front controllers + assets
bin/                     migrate.php, add-user.php, images-audit.php
sql/                     reference SQL for the migration (bin/migrate.php is the real thing)
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

| role          | can                                                                 |
|---------------|---------------------------------------------------------------------|
| `advisor`     | create/edit/clone degree maps for **their colleges**, future years only |
| `marketing`   | the Majors admin                                                    |
| `super_admin` | everything, plus Manage Users and deleting maps                     |
| `none`        | on the list but disabled                                            |

Maps for the current and past catalog years are read-only for everyone;
"Clone to Next Year" makes the editable copy. Nobody is auto-created at
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

The dev router serves `app/dev/stub-docroot` as the docroot (stand-in site
includes for both designs) and `docroot/academics/majors` at its real URL.

## Next phase

- Majors marketing-page editor (fields, images, similar programs, links) in `_admin/`.
- Refine `templates/new/` against the live www-dev design; flip `design` there.
- Retire the compatibility shims `docroot/.../maps_functions.php` and
  `majors_functions.php` once `/_resources/php/degree_maps_search_process.php`
  and `degree_search.php` are removed from the CMS.
- Archive the unreferenced photos in `_images/`: `bin/images-audit.php` (report in
  [docs/images-manifest.txt](docs/images-manifest.txt)) found 565 referenced, 624
  unreferenced (55 MB) and 2 referenced-but-missing files (`PHS.jpg`,
  `HP_Nursing_Accelerated_Program_ITP.jpg`). `--archive <dir>` moves the orphans.
- Data hygiene spotted by the version lookup: two 2026-27 maps exist twice.
  789/791 ("American Sign Language — Structure of Language") are identical;
  770/965 ("Applied Engineering — Engineering Management") diverged — 965 has a
  reworked 3rd/4th year and clean hours rows, 770 was last edited 2026-01-28.
  Creating or cloning a second map for the same degree and year is now refused
  (the UI offers to open the existing one) and the admin listing tags existing
  duplicates, but these two pairs need a human to pick one and delete the other.
