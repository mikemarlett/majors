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

### The listing pages, per design

Current design: the markup of the CMS listing pages (the 2018 library's
alpha-list organism, taxonomy/search filters, section headers). Only the A–Z
letter headings get the `section-header` wrapper, because `.alpha-list
.section-header h2…h6` is the round yellow letter; college and certificate-topic
headings are bare, as on the original pages. Redesign: its own template
(`templates/new/majors/listing.php`) built from the design system's pieces
(Heading and Button atoms, AlphaNav + AlphaListing / ColumnedLinkList, the
Callout for the certificate intros); no sprite icons there, and
`degree-map.css` sizes any that slip through. The search/select bar is
`majors/filters.php` in the layout's top slot on both designs. On the redesign
the results headline, its buttons, the letter index and the Certificates
"Select View" links are in that top band too (`majors/results_header.php`,
swapped by majors.js along with the results), so the listing starts beside the
sidebar; on the current design that template is empty and the header stays in
the listing, as on the CMS pages.

### The A–Z letter index

The CMS listing pages had no letter index. Each design's own pattern is used
instead (`templates/<design>/partials/alpha_nav.php`): on the current site the
2018 library's "alpha filters" molecule (`molecules/filtration/alpha-filters`
in `/srv/work/wichita`, round letter buttons; the live stylesheet still carries
its rule), on the redesign the AlphaNav organism. Only the A–Z views get one;
by-college and Certificates have none, like the pages they replaced.

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
   honoured on these servers; the rule goes in www's Apache `redirects.conf`
   (server/vhost context, so patterns start with `/`). It must not go live
   before the switch: until then www's `index.php` is the CMS listing page.
   Every program basename matches `[a-z0-9][a-z0-9_-]*`, so the rule forwards
   every such page except the app's own files; names starting with `_` and
   the subfolders never match. The app then handles the rest (case, earlier
   names, retired programs that forward, 404 for the unknown):

   ```apache
   RewriteCond %{REQUEST_URI} !^/academics/majors/(index|search|majors|graduate|online|certificates|index_by_college|majors_by_college|graduate_by_college|online_by_college|majors_functions|approot|program-list)\.php$ [NC]
   RewriteRule ^/academics/majors/([a-z0-9][a-z0-9_-]*)\.php$ /academics/majors/index.php?program=$1 [NC,R=301,L]
   ```

   Before enabling it, list the `.php` files in that folder on www that are
   not program pages and add any CMS extras to the exclusion:

   ```bash
   comm -23 <(ls /data/www/main/academics/majors/*.php | xargs -n1 basename | sed 's/\.php$//' | sort) <(mysql -N formshandlerdb -e "SELECT basename FROM majors_academic_programs WHERE basename <> ''" | sort)
   ```

   Test with `R=302` first (browsers cache a 301), then switch to 301.
4. Anything else that links the old pages (CMS navigation, other sites) can
   then move to the `?program=` addresses at leisure.

## 2h. Degree map approval (sql/009)

A map is on the public site only once an advisor admin or a super admin has
approved it (`degree_maps.approved` = 1, with `approved_by` / `approved_at`).
The public viewer, the search, the CMS intro-page shim and the program pages
all read through the approved-only repository; the admin sees every map and
approves or withdraws from the map's Actions row or, several at once, from
the listing table (filter by year, college, approval; tick; Approve selected).
Until approved, a map's public address answers 404 to students and shows a
"Not approved" preview to signed-in advisors. New maps and clones start out
not approved.

Order matters on every box (the shared test database, then www): **run the SQL
first, then unpack the bundles**. The new code filters on `approved`, and
nothing is approved until the SQL has run.

```bash
mysql formshandlerdb < /data/www/config/majors/sql/009_degree_maps_approval.sql
```

First run: every map for the current catalog year and earlier is approved
(no name or date), so students see no change; later years wait for a real
approval. Re-running is harmless. `bin/migrate.php` applies the same step on
the test boxes; on www use the SQL file (section 4: no `migrate.php` there
before the Majors switch).

A consequence worth telling the advisors: the public page's catalog-year menu
lists a year once it has one approved map, and after August 1 the public
default stays on the previous year until something in the new year is
approved. Approving a year's maps together is the intended workflow.

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

Later releases on www: `sql/` files first (section 2h's 009 was the first), then
the app bundle, then the docroot bundle. Before the Majors switch that had to be
the Degree-Maps-only bundle built with the command above; since the switch
(2026-10-08) www takes the full docroot bundle like the test box.

### The Majors switch

Done 2026-10-08 (tables, the full docroot bundle ddf92a8, `cms_import` off).
The nine CMS listing pages were recycled through the CMS API from the sandbox
(their sources are in `/srv/work/majors-backups/cms-listing-pages-20261008/`);
the rewrite in redirects.conf goes live by the deploy timer the same evening.
The CMS program pages are still published and still to be retired at leisure.

The Majors tables travel from the test box once, in this order; after that the
www database is the source for program pages too. Not in the copy: the degree
map tables (edited on www since 2026-10-07), the users tables and
`majors_colleges` (on www since the Degree Maps go-live). `majors_departments`
does come along: the importer adds rows to it, and the users' default
department ids point into the test box's copy.

1. On the test box, dump the eight Majors tables and copy the file to www:
   ```bash
   mysqldump --single-transaction formshandlerdb majors_academic_programs majors_programs_content majors_similar_programs majors_program_sections majors_content_blocks majors_listing_entries majors_program_aliases majors_departments > majors-tables-$(date +%Y%m%d).sql
   ```
2. On www, see which site scripts still read the old tables or the old
   `majors_functions.php`; they keep working (the new tables keep every old
   column; the shim serves the old functions), but you want to know they exist:
   ```bash
   grep -rl "majors_academic_programs\|majors_programs_content\|majors/majors_functions.php" /data/www/main/_resources /data/www/main/academics 2>/dev/null
   ```
3. On www, back up the tables and the folder:
   ```bash
   mysqldump --single-transaction formshandlerdb $(mysql -N formshandlerdb -e "SHOW TABLES LIKE 'majors_%'" | tr '\n' ' ') | gzip > ~/majors-tables-www-before-$(date +%Y%m%d).sql.gz
   tar czf ~/academics-majors-before-switch-$(date +%Y%m%d).tgz -C /data/www/main/academics --exclude=majors/_images majors
   ```
4. Load the tables (the dump drops and recreates each one), then compare the
   count with the test box:
   ```bash
   mysql formshandlerdb < majors-tables-YYYYMMDD.sql
   mysql formshandlerdb -e "SELECT status, COUNT(*) FROM majors_academic_programs GROUP BY status; SELECT COUNT(*) AS sections FROM majors_program_sections; SELECT COUNT(*) AS listing_lines FROM majors_listing_entries"
   ```
5. In the CMS, retire the listing pages whose addresses the app takes:
   `index.php`, `majors.php`, `graduate.php`, `online.php`, `certificates.php`,
   `index_by_college.php`, `majors_by_college.php`, `graduate_by_college.php`,
   `online_by_college.php`, all directly under `/academics/majors/`. If
   retiring also removes the published file, do it right before the next step.
   Leave the program pages, `degree_maps/`, `_images/`, `_props.php` and
   `_nav.ounav` alone.
6. Unpack the full docroot bundle (the only time it goes on www):
   ```bash
   tar xzf majors-docroot-<hash>.tgz -C /data/www/main/academics --overwrite
   ```
7. In `/data/www/config/majors/config/app.www.php`, add
   `'majors' => ['cms_import' => false],` (the example file has the line
   commented out). The importers then refuse to run and page names become
   editable in Page settings.
8. `MAJORS_SITE=www php bin/migrate.php --dry-run` from
   `/data/www/config/majors` should now print only "dry run complete".
9. Check on www: the nine listing pages; a program page by name and the
   `?id=` address redirecting to it; photos (run
   `MAJORS_SITE=www php bin/images-audit.php --images /data/www/main/academics/majors/_images`
   from the app root: report only, "missing" should be 0); `_admin/index.php`
   as a marketing user or super admin, and one page in the in-place editor;
   the Degree Maps pages, which share the folder, still fine.
10. Only now let the rewrite in redirects.conf (section 2g, step 3) go live,
    then test one old program address.
11. At leisure: retire the CMS program pages (the rewrite forwards their
    addresses either way), and tell marketing the editor is at
    `/academics/majors/_admin/`.

## 5. Revised maps for the current year, then the maps to www

Advisors cannot edit a published catalog year, so a revision to a current-year
map is built as a copy in next year's catalog. Since sql/009 that copy is not
approved, so building it on www exposes nothing. Two tools in `sql/tools` move
it back (since 2026-10-07 on www itself, where the maps now live; the replace
step keeps the current map's approval, so the revision is public at once):

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
