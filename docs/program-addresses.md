# Program pages at their original addresses

For Erik and Mike. Written 2026-10-27.

## What changes and why

Since the Majors switch on October 8, every old program address such as
`https://www.wichita.edu/academics/majors/accounting_minor_2.php` answers with a
301 to `https://www.wichita.edu/academics/majors/index.php?program=accounting_minor_2`.
That costs three things:

- **Analytics.** GA4's standard page report drops the query string, so all 499
  program pages have reported as one page, `/academics/majors/index.php`.
- **Campaign tags.** The 301 rule has no `QSA` flag, so a link such as
  `accounting_minor_2.php?utm_source=newsletter` loses its `utm_` parameters on the
  way through, and the visit is not attributed to the campaign.
- **Search engines and the site's ai-meta include.** Search engines are being moved
  to new addresses, and the include that writes each program's schema.org data only
  recognizes the old `<name>.php` form, so it has written nothing since October 8.

The fix is to have the old address serve the program page itself, with no redirect.
The visitor's address bar, analytics and search engines all keep
`/academics/majors/<name>.php`. The app then links to those addresses and names them
as canonical.

## The rule (www, redirects.conf)

Replace the current two lines:

```apache
RewriteCond %{REQUEST_URI} !^/academics/majors/(index|search|majors|graduate|online|certificates|index_by_college|majors_by_college|graduate_by_college|online_by_college|majors_functions|approot|program-list)\.php$ [NC]
RewriteRule ^/academics/majors/([a-z0-9][a-z0-9_-]*)\.php$ /academics/majors/index.php?program=$1 [NC,R=301,L]
```

with these:

```apache
# Majors program pages: /academics/majors/<page name>.php is the program page itself (no redirect).
# The app's own files in that folder are excluded; anything else that matches goes to the app,
# which forwards renamed or combined programs and answers 404 for unknown names.
RewriteCond %{REQUEST_URI} !^/academics/majors/(index|search|majors|graduate|online|certificates|index_by_college|majors_by_college|graduate_by_college|online_by_college|majors_functions|approot|program-list)\.php$ [NC]
RewriteRule ^/academics/majors/([a-z0-9][a-z0-9_-]*)\.php$ /academics/majors/index.php?program=$1 [NC,PT,QSA,L]
```

Only the flags change: `R=301` becomes `PT,QSA`.

- `PT` (pass through) makes it an internal rewrite: Apache serves
  `index.php?program=<name>` while the browser keeps the address it asked for. In
  server or virtual-host context it also hands the new path on to the usual
  mapping (aliases, the PHP handler), which a bare internal rewrite there does not.
- `QSA` keeps the visitor's own query string, so campaign tags reach the page and
  analytics.

The patterns start with `/` because redirects.conf is read in server or
virtual-host context, as the current rule already assumes. If it were included
inside a `<Directory>` block, both patterns would lose the leading `/`.

Test with `apachectl -t` before the graceful reload.

## Order of steps

The two halves are independent: neither order can produce a redirect loop, and
each half alone is safe. The suggested order:

1. **Erik: the rule.** Replace the two lines, `apachectl -t`, graceful reload (or
   Mike's git and the deploy timer). Then check:
   ```bash
   curl -sI https://www.wichita.edu/academics/majors/accounting_minor_2.php | grep -i '^HTTP\|^location'
   ```
   Expect `HTTP/1.1 200` (or `HTTP/2 200`) and no `location` line. And these:
   ```bash
   curl -s https://www.wichita.edu/academics/majors/accounting_minor_2.php | grep -o '<title>[^<]*'
   curl -sI 'https://www.wichita.edu/academics/majors/accounting_minor_2.php?utm_source=test' | grep -i '^HTTP'
   curl -sI https://www.wichita.edu/academics/majors/no_such_page_zz.php | grep -i '^HTTP'
   curl -sI https://www.wichita.edu/academics/majors/graduate.php | grep -i '^HTTP'
   ```
   Expect `<title>Details: Accounting, Minor, BUS`, then 200, 404 and 200. At this
   point the page already works at its old address; its links and canonical tag
   still name the `?program=` form.

2. **Mike: the app setting.** With the app bundle that has the setting (5e509b9 or
   later; this change is the commit after it), add `'pretty_urls' => true` to
   `/data/www/config/majors/config/app.www.php`:
   ```php
   'majors' => ['cms_import' => false, 'pretty_urls' => true],
   ```
   Then check that the listing links and the canonical tag use the old addresses:
   ```bash
   curl -s https://www.wichita.edu/academics/majors/graduate.php | grep -o 'href="/academics/majors/[a-z0-9_-]*\.php"' | head -3
   curl -s https://www.wichita.edu/academics/majors/accounting_minor_2.php | grep -o '<link rel="canonical"[^>]*>'
   ```

3. **Later: retire the CMS program pages.** Their published copies still sit at
   those addresses on www, hidden behind the rule. Recycling them in the CMS stops
   editors from changing pages nobody sees (that is what happened with the Exercise
   Science photo) and means the stale copies can never resurface if the rule is ever
   removed. It can be done by API in one pass, with a backup of every page.

The test boxes need nothing: www-test and www-dev have no such rule and keep the
`?program=` addresses (the setting stays off there).

## Why `?program=` addresses keep working for good

Browsers cache a 301 indefinitely. Everyone who visited an old address since
October 8 has the old 301 to `?program=` stored, and goes straight there without
asking the server. So the app never redirects a `?program=` address to the old
form: if it did, those browsers would bounce between the two forever. Instead the
`?program=` page still answers, and its canonical tag names the old address, which
is what search engines go by. The same goes for links shared during these three
weeks.

What the app does redirect: `?id=N`, another spelling of a page name, an earlier
page name and a program combined into another, all to the canonical address.

## Only if something goes wrong

Either half can be undone alone, in any order, without a loop:

- App: remove `'pretty_urls' => true` from `app.www.php`. Links and canonical tags go
  back to the `?program=` form.
- Rule: put the flags back to `[NC,R=301,L]`. The old addresses redirect again, as
  they have since October 8.
