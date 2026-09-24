# Rebuilding the 2024 theme's `tailwind.css`

The new design's stylesheet is compiled by NewCity's Tailwind setup: the
compiled file only contains the utilities found in the theme's own templates,
so anything our pages use that the theme doesn't gets silently dropped. The
fix is to include our templates in the scan and rebuild — not to hand-write
overrides for missing utilities.

`build.sh` reproduces the `docker/bookbinder` build from the
`wichita-state-2024` checkout without Docker (Node only), pinned to the
container's lockfile versions, with `app/templates/**/*.php` and
`docroot/.../assets/**/*.js` added to Tailwind's `content` list. NewCity's
config file is used unmodified.

```bash
tools/theme-build/build.sh
LIVE=/data/www/main-dev/_resources/_theme/tailwind.css tools/theme-build/build.sh   # + diff against a deployed copy
```

Output: `/srv/work/majors-backups/theme-build/tailwind.css`. Verified
2026-09-24: the rebuild of the unmodified checkout matched the file www-dev
serves rule for rule (the checkout's safelist is a few entries ahead of the
deployed build); adding this app contributed 50 rules (~4 KB).

Deploying is a CMS step: replace `/_resources/_theme/tailwind.css` on the
site and bump the `?v=` on the stylesheet link in
`/_resources/_theme/includes/headcode.inc`, then republish.

Keep in mind: whoever next rebuilds the theme without this app in the scan
loses those utilities again. Either run this script for every theme build,
or add the two globs to NewCity's config permanently.
