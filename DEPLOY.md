# Deploying

No composer or npm on the servers; PHP cannot write inside the app tree; the
docroot is managed by Modern Campus. Deploy = copy files, run one script.

## 1. Files

| from (repo)                | to (server)                                   |
|----------------------------|-----------------------------------------------|
| `app/`                     | `/data/www/config/majors/`                    |
| `docroot/academics/majors/`| `<docroot>/academics/majors/` (www-test: `/data/www/main-test/…`, www: `/data/www/main/…`) |

One app root serves every docroot on the box, the same way `/data/www/config`
already serves www-dev and www-test. Settings layer as
`config/app.php` → `config/app.local.php` (box-wide) → `config/app.<site>.php`,
where `<site>` is the first label of the request host: `www`, `www-dev`,
`www-test`. Everything else that differs between sites (docroot, theme
include dirs, cookies) already follows `$_SERVER['DOCUMENT_ROOT']` and the host.

Once per box:

```bash
cd /data/www/config/majors/config
cp app.www-dev.example.php  app.www-dev.php    # design => 'new'
cp app.www-test.example.php app.www-test.php   # design => 'old' (nothing else to set)
# app.local.php is not needed on the servers: the defaults already use
# /data/www/config/functions.php and /data/www/config/phpCAS/config.php.
```

Once per docroot:

```bash
printf '%s\n' '<?php' "return '/data/www/config/majors';" > /data/www/main-dev/academics/majors/approot.php
printf '%s\n' '<?php' "return '/data/www/config/majors';" > /data/www/main-test/academics/majors/approot.php
```

CLI scripts (`bin/*.php`) have no request host, so tell them which site they
run for: `MAJORS_SITE=www-test php bin/migrate.php`. Without it they fall back
to the docroot name and then to `local`; with one shared database per box the
site only changes `design`, so the scripts work either way.

Do **not** copy `app/dev/`, `app/tests/` or any `app/config/app.*.php`
override from another box. `_images/` is copied once (it is not in git).

The CMS-published files in the same folder (`degree_maps/index.php`,
`degree_maps/_nav.ounav`) are untouched; the app reads `_nav.ounav` at runtime
for the section menu.

## 2. Database (www-test first, then www when the tables are copied)

```bash
cd /data/www/config/majors
MAJORS_SITE=www-test php bin/migrate.php --dry-run
MAJORS_SITE=www-test php bin/migrate.php
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
mysql formshandlerdb < sql/002_remove_duplicate_maps.sql   # drops the two double-cloned 2026-27 maps (770, 789)
```

Then seed the first super admin and check the advisors:

```bash
php bin/add-user.php mike.marlett@wichita.edu super_admin Mike Marlett q262t958
mysql formshandlerdb -e "SELECT id,email,netid,role,is_active FROM majors_users"
```

Every advisor needs `role='advisor'`, the email CAS releases (their
wichita.edu address), and at least one row in `majors_user_colleges` — Manage
Users (`degree_maps/admin/manage_users.php`) does this once a super admin can
sign in.

## 3. Verify on www-test

1. `https://www-test.wichita.edu/academics/majors/degree_maps/maps.php` — list renders with the site header/footer; open a map; print preview is letter portrait with no chrome.
2. `…/degree_maps/maps.php?latest=<id>` redirects to the newest year.
3. `…/degree_maps/admin/maps.php` → CAS login → your name in the admin bar. Check the Apache error log for `[majors]` lines if the CAS attribute names differ (`CasProvider::identityFromAttributes` falls back to the CAS principal).
4. As an advisor: Edit/Clone only on own-college, future-year maps; the ajax endpoint answers 403 otherwise.
5. Unlisted CAS user → "not on the access list" page.

## 4. Going live on www

Copy the same files to www. The admin is now safe to leave enabled on www
(everything is behind CAS + the list), but editing on www-test and copying the
`degree_maps*` tables across, as today, still works — run `bin/migrate.php`
on www too so `majors_users` matches.

## Rollback

The old flat files are in git history (`git show 7e0fb7b`) and the www-test
originals are untouched until you overwrite them; `docs/baseline-manifest.txt`
lists every file that was there.
