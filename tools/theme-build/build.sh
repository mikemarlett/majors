#!/usr/bin/env bash
# Rebuild the 2024 design's tailwind.css exactly as NewCity's bookbinder
# container does (wichita-state-2024/docker/bookbinder), without Docker, with
# this app's templates and scripts added to Tailwind's content scan so the
# utilities they use are compiled in.
#
#   tools/theme-build/build.sh                 # development build (what www-dev serves: unminified)
#   NODE_ENV=production tools/theme-build/build.sh
#   LIVE=/data/www/main-dev/_resources/_theme/tailwind.css tools/theme-build/build.sh   # also diff against a deployed copy
#
# Inputs : THEME_SRC (default /srv/work/wichita-state-2024) — the NewCity checkout
# Output : $OUT (default /srv/work/majors-backups/theme-build/tailwind.css)
set -euo pipefail

THEME_SRC="${THEME_SRC:-/srv/work/wichita-state-2024}"
BUILD="${BUILD:-/srv/work/wichita-state-2024-build}"
OUT="${OUT:-/srv/work/majors-backups/theme-build/tailwind.css}"
MAJORS="$(cd "$(dirname "$0")/../.." && pwd)"
BB="$THEME_SRC/docker/bookbinder"
[ -f "$BB/postcss.config.js" ] || { echo "NewCity checkout not found at $THEME_SRC" >&2; exit 1; }

mkdir -p "$BUILD" && cd "$BUILD"

# 1. Toolchain pinned to bookbinder's lockfile versions.
python3 - "$BB/package-lock.json" > package.json <<'PY'
import json,sys
lock=json.load(open(sys.argv[1]))['packages']
want=['tailwindcss','@tailwindcss/typography','postcss','postcss-cli','postcss-import','postcss-import-ext-glob','postcss-focus-visible','autoprefixer','cssnano','chroma-js']
deps={n: lock['node_modules/'+n]['version'] for n in want}
print(json.dumps({"name":"wichita-state-2024-css-build","private":True,
  "description":"Docker-free rebuild of theme/css/tailwind.css, pinned to docker/bookbinder/package-lock.json",
  "devDependencies":deps}, indent=2))
PY
if [ ! -d node_modules ] || ! diff -q package.json node_modules/.built-from 2>/dev/null >/dev/null; then
  npm install --no-audit --no-fund --loglevel=error && cp package.json node_modules/.built-from
fi

# 2. The container's /app layout: config, helpers, css entry, component css/twig, custom postcss plugins.
rm -rf css postcss-custom-plugins tailwind-helpers components majors
cp -r "$THEME_SRC/theme/css" css
cp -r "$THEME_SRC/.storybook/storybook-config/tailwind-helpers" tailwind-helpers
ln -s "$THEME_SRC/theme/components" components
cp -r "$BB/postcss-custom-plugins" .
cp "$BB/expose-tailwind.config.js" .
sed "s#require('/app/postcss-custom-plugins/#require(__dirname + '/postcss-custom-plugins/#g" "$BB/postcss.config.js" > postcss.config.js
sed -i "s#'/app/full-tailwind-config.json'#require('path').join(__dirname, '..', 'full-tailwind-config.json')#g" postcss-custom-plugins/*.js
mkdir -p majors && ln -s "$MAJORS/app/templates" majors/templates && ln -s "$MAJORS/docroot/academics/majors/assets" majors/assets
cat > tailwind.config.js <<JS
// NewCity's config unchanged, plus the Majors app in the content scan.
const config = require('$THEME_SRC/.storybook/storybook-config/tailwind.config.js');
config.content = [...config.content, './majors/templates/**/*.php', './majors/assets/**/*.js'];
module.exports = config;
JS

# 3. Build (same two steps as docker-entrypoint.sh, minus JS/Storybook).
export NODE_PATH="$BUILD/node_modules"
node expose-tailwind.config.js
NODE_ENV="${NODE_ENV:-development}" npx postcss 'css/**/*.css' --base css --dir dist/css
mkdir -p "$(dirname "$OUT")" && cp dist/css/tailwind.css "$OUT"
echo "built: $OUT ($(wc -c < "$OUT") bytes, NODE_ENV=${NODE_ENV:-development})"

# 4. Optional: what changed versus a deployed copy.
if [ -n "${LIVE:-}" ] && [ -f "$LIVE" ]; then
  python3 - "$LIVE" "$OUT" <<'PY'
import re,sys
def rules(p):
    css=re.sub(r'/\*.*?\*/','',open(p).read(),flags=re.S); out=[]; st=[]; pos=0
    for m in re.finditer(r'[{}]',css):
        seg=css[pos:m.start()]; pos=m.end()
        if m.group()=='{': st.append(' '.join(seg.split()))
        else:
            sel=st.pop() if st else ''; body=' '.join(seg.split())
            if body: out.append((' / '.join(s for s in st if s.startswith('@')), sel, body))
    return out
a=set(rules(sys.argv[1])); b=set(rules(sys.argv[2]))
print(f"vs live: {len(a&b)} rules common, {len(b-a)} new in build, {len(a-b)} only in live")
for m,s,bd in sorted(b-a)[:80]: print("   +", (m+' | ') if m else '', s[:90])
PY
fi
