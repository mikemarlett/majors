# Deploying

No composer or npm on the servers; PHP cannot write inside the app tree; the
docroot is managed by Modern Campus. Deploy = copy files, run one script.

## 1. Files

| from (repo)                | to (server)                                   |
|----------------------------|-----------------------------------------------|
| `app/`                     | `/data/www/config/majors/`                    |
| `docroot/academics/majors/`| `<docroot>/academics/majors/` — `/data/www/main/` on both the test box (www-test) and the production box (www); `/data/www/main-dev/` for www-dev |

One app root serves every docroot on the box, the same way `/data/www/config`
already serves www-dev and www-test. Settings layer as
`config/app.php` → `config/app.local.php` (box-wide) → `config/app.<site>.php`,
where `<site>` is the first label of the request host: `www`, `www-dev`,
`www-test`. Everything else that differs between sites (docroot, theme
include dirs, cookies) already follows `$_SERVER['DOCUMENT_ROOT']` and the host.

Once per box:

```bash
cd /data/www/config/majors/config
cp app.www-dev.example.php  app.www-dev.php    # design => 'new', image_base => 'https://www.wichita.edu'
cp app.www-test.example.php app.www-test.php   # design => 'old', image_base => 'https://www.wichita.edu' (photos are published to www only)
# app.local.php is not needed on the servers: the defaults already use
# /data/www/config/functions.php and /data/www/config/phpCAS/config.php.
```

Nothing is written into the docroot: `_bootstrap.php` looks for the app at
`/data/www/config/majors` on its own (an `approot.php` beside it is only
needed if the app is deployed somewhere else).

CLI scripts (`bin/*.php`) have no request host, so tell them which site they
run for: `MAJORS_SITE=www-test php bin/migrate.php`. Without it they fall back
to the docroot name and then to `local`; with one shared database per box the
site only changes `design`, so the scripts work either way.

Do **not** copy `app/dev/`, `app/tests/` or any `app/config/app.*.php`
override from another box. `app/bin/` and `app/sql/` do come along, so the
commands below run from `/data/www/config/majors`. `_images/` is copied once (it is not in git).

The CMS-published files in the same folder (`degree_maps/index.php`,
`degree_maps/_nav.ounav`) are untouched; the app reads `_nav.ounav` at runtime
for the section menu.

## 2. Database (www-test first, then www when the tables are copied)

```bash
cd /data/www/config/majors
MAJORS_SITE=www-test php bin/migrate.php --dry-run
MAJORS_SITE=www-test php bin/migrate.php
```

Or, without PHP, the same migration as plain SQL (idempotent, safe to re-run
or to finish an interrupted run):

```bash
mysql formshandlerdb < /data/www/config/majors/sql/003_migration_plain.sql
```

`bin/migrate.php` connects the way the app does (through
`/data/www/config/functions.php`), inspects `information_schema`, and only
applies what is missing: `majors_users.netid / role / is_active / last_login_at`,
the `majors_user_colleges` table (seeded from `default_college_id`), and three
indexes on `degree_maps`. It normalizes legacy role values (`admin` →
`super_admin`, `editor`/`approver` → `advisor`) and copies a netid out of
`ouauth_id` when one is there. Safe to re-run.

Also once, on www-test and then www:

```bash
mysql formshandlerdb < /data/www/config/majors/sql/002_remove_duplicate_maps.sql   # drops the two double-cloned 2026-27 maps (770, 789)
```

Then seed the first super admin and check the advisors:

```bash
cd /data/www/config/majors
php bin/add-user.php mike.marlett@wichita.edu super_admin Mike Marlett q262t958   # netid is required
mysql formshandlerdb -e "SELECT id,email,netid,role,is_active FROM majors_users"
```

Every user needs a **netid** (verified on www-test 2026-09-15: CAS identifies
people by netid, and a row with only an email is not matched), a role, and
for advisors at least one row in `majors_user_colleges`. Manage Users
(`degree_maps/admin/manage_users.php`) does this once a super admin can sign
in; for the first super admin:

```bash
mysql formshandlerdb -e "UPDATE majors_users SET netid='q262t958' WHERE email='mike.marlett@wichita.edu'"
```

If a sign-in fails and the error log is out of reach,
`auth/login.php?diag=1` shows what the server sees and the last failure.

## 2b. CAS registration (once per server, by ITS)

CAS authorizes by service URL, and the test servers talk to a separate CAS
(`cas-test.wichita.edu`, set by `cas_host` in `/data/www/config/phpCAS/config.php`;
www uses `cas.wichita.edu`). "Application Not Authorized to Use CAS" means the
service below is not registered on that CAS server. Ask ITS to register:

| Server   | CAS server              | Service URL |
|----------|-------------------------|-------------|
| www-test | cas-test.wichita.edu    | `https://www-test.wichita.edu/academics/majors/auth/login.php` |
| www-dev  | cas-test.wichita.edu    | `https://www-dev.wichita.edu/academics/majors/auth/login.php` |
| www      | cas.wichita.edu         | `https://www.wichita.edu/academics/majors/auth/login.php` |

The service URL is fixed by `auth.service_host` (per-site config) and is always
https, so it does not depend on how someone reached the page. Attributes
needed: the netid (`sAMAccountName` / `UDC_IDENTIFIER`), plus `mail`,
`givenName`, `sn` if released.

## 2c. Azure / Entra ID instead of CAS (in use on www-test since 2026-09-17)

The app can sign people in through the existing Azure app registration
(`/data/www/config/phpAzure/loader.php`, the one `/_resources/authorization/azure.php`
uses) instead of CAS. In the site's config file:

```php
'auth' => ['provider' => 'azure', 'service_host' => 'www-test.wichita.edu'],
```

Prerequisites, both in the Azure app registration (Entra admin center ▸ App
registrations ▸ Authentication / Certificates & secrets):

1. Add the redirect URI `https://www-test.wichita.edu/academics/majors/auth/login.php`
   (and the www-dev / www ones when needed). Azure refuses unlisted URIs the
   way CAS refuses unregistered services.
2. A **valid client secret**. Secrets expire (24 months at most); one issued
   "a few years ago" has lapsed and must be replaced in the loader's
   `WSU_OAUTH2_SECRET`. Sign-in fails with `AADSTS7000222` when it has.

The netid comes from `onPremisesSamAccountName` (Graph `/me`, needs the
`User.Read` scope that is requested by default) or from the UPN's local part;
the row in `majors_users` is matched by netid as with CAS. `auth/login.php?diag=1`
reports the loader, the constants and the exact redirect URI to register.

## 2d. Majors: schema + import from the CMS

Once per database (www-test first; www when the tables are copied):

```bash
cd /data/www/config/majors
MAJORS_SITE=www-test php bin/migrate.php          # or: mysql formshandlerdb < sql/004_majors_v2.sql
MAJORS_SITE=www-test php bin/majors-import.php --dry-run
MAJORS_SITE=www-test php bin/majors-import.php
```

The importer reads the program pages from the CMS (`www` site, staging) with
the Modern Campus helpers that already live in `/data/www/config`
(`modern_campus_helper_functions.php` + `modern_campus_config.php`), so it runs
on any WSU box. ~1,000 link tags are resolved through the API on the first run
and cached in `/tmp`; pass `--tags=/data/www/config/majors-cms-tags.json` to
keep the cache between runs. Re-running updates what changed and retires
programs whose page is gone (never deletes; degree maps keep their links).

## 2f. In-place editor

No new columns: `basename` is already unique on `majors_academic_programs`.
One clean-up runs once per database (it deletes the import's unused
`kind='similar'` section rows and renumbers positions; safe to re-run):

```bash
mysql formshandlerdb < /data/www/config/majors/sql/007_drop_similar_sections.sql
```

Deploy the app bundle and the docroot bundle as usual. The editor loads
CKEditor 5 (balloon-block build, pinned with an integrity hash) from
jsdelivr, so the editors' browsers need to reach `cdn.jsdelivr.net`. Old
`index.php?id=N` links keep working (301 to `?program=<basename>`); retired
programs answer 404 publicly.

## 2e. Graduate Program Details from the catalog

The graduate template's Program Details box (degree, modality, credit hours,
entry term) starts from the catalog: `bin/majors-catalog-seed.php` reads each
graduate program's catalog page (the Curriculum link marketing already put on
the page) and fills `catalog_url`, `degree_title` and `credit_hours`. Modality,
entry terms, the STEM flag and the coordinator are not in the catalog and are
filled by marketing (columns on `majors_academic_programs`; editor to come).

```bash
cd /data/www/config/majors
mysql formshandlerdb < sql/005_program_details.sql        # or bin/migrate.php again
MAJORS_SITE=www-test php bin/majors-catalog-seed.php --dry-run
MAJORS_SITE=www-test php bin/majors-catalog-seed.php       # ~200 catalog fetches, ~1 min; add --overwrite to re-seed
```

The web servers cannot reach catalog.wichita.edu, so in practice the seed runs
on the sandbox and ships as SQL keyed by basename:

```bash
# sandbox
MAJORS_SITE=www-test php bin/majors-catalog-seed.php --overwrite --sql=/srv/work/majors-backups/majors-catalog-seed-$(date +%Y%m%d).sql
# server
mysql formshandlerdb < majors-catalog-seed-YYYYMMDD.sql
```

## 2g. Listings, full-width sections, departments, renames (sql/008)

Run the migration **before** unpacking the new app bundle: the listing pages
read the new `majors_listing_entries` table. It only adds tables and
nullable columns, so the code already on the server is unaffected, and it is
safe to re-run.

```bash
cd /data/www/config/majors
mysql formshandlerdb < sql/007_drop_similar_sections.sql   # only if 2f has not run on this database
mysql formshandlerdb < sql/008_listings_and_more.sql        # then unpack the app and docroot bundles
```

The first run gives every active program one listing line on the lists its
credential implies. Then bring in the hand-kept CMS listing pages (who is on
which list, under which names, the Certificates topics and intro callouts):

```bash
mysql formshandlerdb < majors-listings-YYYYMMDD.sql         # generated on the sandbox, keyed by page name
# or read the CMS from the server:
MAJORS_SITE=www-test php bin/majors-listing-import.php --dry-run
MAJORS_SITE=www-test php bin/majors-listing-import.php
```

And add what the first page import missed (the two full-width sections and
the Similar Programs background photos). It changes nothing else. Run it once,
right after 008:

```bash
mysql formshandlerdb < majors-supplement-YYYYMMDD.sql
# or: MAJORS_SITE=www-test php bin/majors-import.php --supplement
```

The listing import leaves editor work alone: a program whose listings were
saved in the editor keeps them. The supplement only fills photos that were
never set (one cleared in the editor stays cleared) and adds a band only to a
page that has no band yet, so a second run changes nothing unless an editor
has removed one of the two bands since.

### When the CMS program pages retire (cutover; plan, not done)

1. Set `'majors' => ['cms_import' => false]` in the site config
   (`app.www.php` etc.). Imported page names become editable in Page
   settings (earlier names keep forwarding), and both importers refuse to
   run unless given `--force`. A forced page import still finds a renamed
   page by its earlier name and keeps the new name, but it replaces the
   page's content with the CMS copy, so it is for emergencies only.
2. The app's listing front controllers use the CMS listing pages' own
   addresses (`/academics/majors/index.php`, `majors.php`, `graduate.php`,
   `online.php`, `certificates.php`, the `*_by_college.php` pages). On www a
   CMS publish of those pages would overwrite the app's files, so they have to
   be retired in the CMS (or excluded from publishing) before the app goes
   live there.
3. Each CMS program page (`/academics/majors/<basename>.php`) needs a
   permanent forward to `index.php?program=<basename>`. `.htaccess` is not
   honoured on these servers, so either ITS adds one rewrite rule to the
   Apache config, or a script writes a two-line PHP forward at each old
   address from the database (page names never change while
   `cms_import` is on, so the list is exact on cutover day).
4. Anything else that links the old pages (CMS navigation, other sites) can
   then move to the `?program=` addresses at leisure.

## 3. Verify on www-test

1. `https://www-test.wichita.edu/academics/majors/degree_maps/maps.php` — list renders with the site header/footer; open a map; print preview is letter portrait with no chrome.
2. `…/degree_maps/maps.php?latest=<id>` redirects to the newest year.
3. `…/degree_maps/admin/maps.php` → CAS login → your name in the admin bar. Check the Apache error log for `[majors]` lines if the CAS attribute names differ (`CasProvider::identityFromAttributes` falls back to the CAS principal).
4. As an advisor: Edit/Clone only on own-college, future-year maps; the ajax endpoint answers 403 otherwise.
5. Unlisted CAS user → "not on the access list" page.

## 4. Going live on www

Two separate steps. **Degree Maps** can go any time. **The Majors switch** waits
for marketing, and has to happen together with retiring the CMS listing pages.

**Do not unpack the full docroot bundle on www before the Majors switch.** Its
listing pages (`index.php`, `majors.php`, `graduate.php`, `online.php`,
`certificates.php`, the `*_by_college.php` pages) use the same addresses as the
CMS's live listing pages there and would replace them. For Degree Maps alone,
build a bundle without them:

```bash
git archive --format=tar --prefix=majors/ HEAD:docroot/academics/majors _bootstrap.php approot.example.php assets auth degree_maps ':(exclude)degree_maps/_nav.ounav' | gzip > majors-docroot-www-degreemaps-$(git rev-parse --short HEAD).tgz
```

### Degree Maps on www

Done 2026-10-07 with the app and docroot bundles from c744e89. Maps are edited on
www from that date; the test box's map tables are a stale copy and must not be
copied over www again.

1. Sign-in: add `https://www.wichita.edu/academics/majors/auth/login.php` to the
   Azure app registration's redirect URIs. The box needs the site's own
   `/data/www/config/phpAzure/` folder (the loader with the `WSU_OAUTH2_*`
   constants plus the OAuth library it loads); www did not have it, so copy the
   whole folder from the test box with `scp -rp`, then match the test box's
   owner/group so the web server can read the loader and nobody else can. The same
   client id, secret and tenant serve every box; only the redirect URI is per host.
   `auth/login.php?diag=1` confirms all of it without signing in. (Or have ITS
   register the URL on cas.wichita.edu and use `'provider' => 'cas'`.)
2. Users: copy `majors_users`, `majors_user_colleges` and `majors_colleges` from the
   test box (mysqldump there, load on www). The college ids in the first two refer to
   the third, so they travel together. The maps themselves came over in section 5.
3. Move the old admin out of the docroot. The original folder had an admin with no
   real sign-in, including 27 scripts in `degree_maps/admin/ajax/`; unpacking does not
   delete them. Move `degree_maps/admin` and `_admin` aside if they exist.
4. Unpack the app to `/data/www/config/majors` and copy `config/app.www.example.php`
   to `config/app.www.php`. Do **not** run `bin/migrate.php` on www yet: Degree Maps
   needs nothing from it once the users tables are copied, and the rest of what it
   lists is the Majors schema, which belongs to the Majors switch (it reshapes tables
   the CMS's old scripts on www still read).
5. Unpack the Degree-Maps-only docroot bundle into `/data/www/main/academics`.

From then on, maps are edited on www. Do not copy the test box's map tables over
www again; that would undo www's edits. Advisors still cannot edit a published
year: a revision is still a next-year copy moved back with section 5's
`degree-maps-replace.sql`, or a super admin's correction.

### The Majors switch

Copy the Majors tables from the test box, unpack the full docroot bundle, retire the
CMS listing pages in the CMS at the same time (or a publish overwrites the app's
files), set `'majors' => ['cms_import' => false]` once the CMS program pages are
retired, and forward each old program page (see "When the CMS program pages retire"
in section 2g).

## 5. Revised maps for the current year, then the maps to www

Advisors cannot edit a published catalog year, so a revision to a current-year
map is built as a copy in next year's catalog. Two tools in `sql/tools` move it
back (run on the test box; www-test and www-dev share the database):

```bash
cd /data/www/config/majors/sql/tools
mysqldump --single-transaction formshandlerdb degree_maps degree_maps_courses degree_maps_footnotes degree_maps_semester_hours degree_maps_year_hours | gzip > ~/degree-maps-before-$(date +%Y%m%d).sql.gz
mysql formshandlerdb -t < degree-maps-revisions.sql          # read-only: each copy and the current map it replaces
mysql formshandlerdb -e "SET @current=<current_id>, @revised=<revised_id>; source degree-maps-replace.sql"   # once per pair
```

`degree-maps-replace.sql` puts the revised content onto the current map, so the
map keeps its id and every link to it, and removes the copy. It changes nothing
unless the two ids are the current map and next year's copy of the same degree.
A copy with no current map (a new degree) only needs its year changed:
`UPDATE degree_maps SET academic_year = <current> WHERE id = <id> AND academic_year = <next>`.

Then copy the five tables to www. The dump drops and recreates them there, so
www ends up identical to the test box, including any next-year maps already
started (the viewers list every year in the table):

```bash
# test box
mysqldump --single-transaction formshandlerdb degree_maps degree_maps_courses degree_maps_footnotes degree_maps_semester_hours degree_maps_year_hours > degree-maps-$(date +%Y%m%d).sql
# www, after copying the file over
mysqldump --single-transaction formshandlerdb degree_maps degree_maps_courses degree_maps_footnotes degree_maps_semester_hours degree_maps_year_hours | gzip > degree-maps-www-before-$(date +%Y%m%d).sql.gz
mysql formshandlerdb < degree-maps-YYYYMMDD.sql
mysql formshandlerdb -e "SELECT academic_year, COUNT(*) FROM degree_maps GROUP BY academic_year"   # same counts as the test box
```

Roll back with `gunzip < degree-maps-www-before-YYYYMMDD.sql.gz | mysql formshandlerdb`.
Neither viewer reads `program_id`, so program ids that differ between the two
databases do no harm.

## Rollback

The old flat files are in git history (`git show 7e0fb7b`) and the www-test
originals are untouched until you overwrite them; `docs/baseline-manifest.txt`
lists every file that was there.
