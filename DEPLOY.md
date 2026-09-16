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

## 2c. Azure / Entra ID instead of CAS (optional)

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
   `WSU_OAUTH2_CLIENT_SECRET`. Sign-in fails with `AADSTS7000222` when it has.

The netid comes from `onPremisesSamAccountName` (Graph `/me`, needs the
`User.Read` scope that is requested by default) or from the UPN's local part;
the row in `majors_users` is matched by netid as with CAS. `auth/login.php?diag=1`
reports the loader, the constants and the exact redirect URI to register.

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
