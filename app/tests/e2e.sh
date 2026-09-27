#!/bin/bash
# End-to-end HTTP checks against the dev server with the real local database.
# Needs: app/config/app.local.php (dev auth), the three seeded users from DEPLOY.md,
# and MYSQL_PWD for formsuser in the environment.  Usage: php app/tests/run.php; MYSQL_PWD=... app/tests/e2e.sh
cd /srv/work/majors
S=${TMPDIR:-/tmp}/majors-e2e; mkdir -p $S
[ -n "${MYSQL_PWD:-}" ] || { echo "set MYSQL_PWD for formsuser first"; exit 1; }
Q() { mysql -h localhost -u formsuser formshandlerdb -N -e "$1"; }
B=http://127.0.0.1:8087/academics/majors
php -S 127.0.0.1:8087 app/dev/router.php >$S/e2e-server.log 2>&1 & SRV=$!; sleep 1
PASS=0; FAIL=0
ok()   { echo "  ok   $1"; PASS=$((PASS+1)); }
bad()  { echo "  FAIL $1"; FAIL=$((FAIL+1)); }
chk()  { if [ "$1" = "$2" ]; then ok "$3 ($1)"; else bad "$3 (got: $1, want: $2)"; fi; }
has()  { if grep -q -- "$2" "$1"; then ok "$3"; else bad "$3 (missing: $2)"; fi; }
hasnt(){ if ! grep -q -- "$2" "$1"; then ok "$3"; else bad "$3 (unexpected: $2)"; fi; }
GET()  { curl -s -o $S/out.html -w "%{http_code}" "$@"; }

ENG2027=$(Q "SELECT id FROM degree_maps WHERE college='College of Engineering' AND academic_year=2027 ORDER BY id LIMIT 1")
FAM_OLD=$(Q "SELECT d.id FROM degree_maps d JOIN (SELECT major,degree_type,college FROM degree_maps GROUP BY 1,2,3 HAVING COUNT(DISTINCT academic_year)=4 LIMIT 1) f USING(major,degree_type,college) WHERE d.academic_year=2024 LIMIT 1")
FAM_NEW=$(Q "SELECT d.id FROM degree_maps d JOIN degree_maps o ON o.id=$FAM_OLD AND d.major=o.major AND d.degree_type=o.degree_type AND d.college=o.college ORDER BY d.academic_year DESC, d.id DESC LIMIT 1")
LAS2027=$(Q "SELECT id FROM degree_maps WHERE college LIKE 'Fairmount%' AND academic_year=2027 ORDER BY id LIMIT 1")
PROG=$(Q "SELECT p.id FROM majors_academic_programs p WHERE p.status='active' AND p.basename<>'' AND p.description<>'' AND p.image_url<>'' ORDER BY p.id LIMIT 1")
PBN=$(Q "SELECT basename FROM majors_academic_programs WHERE id=$PROG")
echo "ids: eng2027=$ENG2027 famOld=$FAM_OLD famNew=$FAM_NEW las2027=$LAS2027 program=$PROG"

echo "[public degree maps]"
chk "$(GET "$B/degree_maps/maps.php")" 200 "listing"; has $S/out.html 'dm-listing' 'listing markup'; grep -qE 'STUB SITE HEADER|id="site-header"' $S/out.html && ok "chrome included" || bad "chrome included"
chk "$(GET "$B/degree_maps/maps.php?order=college")" 200 "by college"; has $S/out.html 'Fairmount College' 'college groups'
chk "$(GET "$B/degree_maps/maps.php?degree_map_id=$ENG2027")" 200 "one map"; has $S/out.html 'dm-table' 'map table'; has $S/out.html 'Systemwide General Education' 'SGE key'; has $S/out.html "?latest=$ENG2027" 'permalink shown'
chk "$(GET "$B/degree_maps/maps.php?map_id=$ENG2027")" 200 "legacy map_id alias"
chk "$(GET "$B/degree_maps/maps.php?degree_map_id=$FAM_OLD")" 200 "old version"; has $S/out.html 'You are viewing the 2023 - 2024' 'older-version notice'; has $S/out.html 'dm-versions__list' 'year switcher'
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/degree_maps/maps.php?latest=$FAM_OLD")" "302 $B/degree_maps/maps.php?degree_map_id=$FAM_NEW" "?latest redirects to newest"
chk "$(GET "$B/degree_maps/maps.php?degree_map_id=999999")" 404 "missing map"
chk "$(GET -X POST -d "searchList=Account&selected_year=2027" "$B/degree_maps/search.php")" 200 "search json"; has $S/out.html '"success":true' 'search success'
chk "$(GET -X POST -d "searchList=Zzzqqq&selected_year=2027" "$B/degree_maps/search.php")" 200 "search no results"; has $S/out.html 'No results' 'no results message'
chk "$(GET -X POST -d "searchList=A&selected_year=2027" "$B/degree_maps/search.php")" 200 "short search"; has $S/out.html 'two or more' 'short search message'

echo "[public majors]"
chk "$(GET "$B/index.php")" 200 "programs listing"; has $S/out.html 'All Degrees' 'headline'; has $S/out.html 'majors-jump' 'jump nav'
chk "$(GET "$B/index.php?order=college&filter=online")" 200 "filtered"; has $S/out.html 'Online Programs' 'filter headline'
chk "$(GET "$B/index.php?program=$PBN")" 200 "program page"; grep -qE 'program-card|majors-program-intro' $S/out.html && ok "program page body (either design)" || bad "program page body"
chk "$(GET "$B/search.php?filter=graduate")" 200 "majors search json"; has $S/out.html '"title":"Graduate Degrees"' 'json title'

echo "[listings]"
chk "$(GET "$B/index.php")" 200 "All Programs"; has $S/out.html '>Nursing Practice - Family Nurse Practitioner</a>' 'listing names from the CMS (DNP as Nursing Practice)'; hasnt $S/out.html 'program=business_administration_mem_to_mba"' 'a page left off the CMS listing is not listed'
chk "$(GET "$B/majors.php")" 200 "Undergrad Majors & Minors address"; has $S/out.html 'Undergraduate Majors and Minors' 'undergrad headline'
chk "$(GET "$B/graduate.php")" 200 "Graduate address"; hasnt $S/out.html 'program=administrator_in_training_ait__practicum_placement_program_338"' 'AIT is not on the Graduate list'
chk "$(GET "$B/graduate_by_college.php")" 200 "Graduate by college address"
chk "$(GET "$B/online.php")" 200 "Online address"
chk "$(GET "$B/index_by_college.php")" 200 "All by college address"
chk "$(GET "$B/certificates.php")" 200 "Certificates address"; has $S/out.html 'program=administrator_in_training_ait__practicum_placement_program_338"' 'AIT is on the Certificates list'; has $S/out.html 'Graduate Certificates' 'Certificates page halves'; has $S/out.html 'id="graduate-health"' 'Certificates topics'
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?filter=badges")" "302 https://badges.wichita.edu/badge/" "Badges goes to the badges site"
LP=$(Q "SELECT id FROM majors_academic_programs WHERE basename='counseling_med_41'")

echo "[auth gating]"
chk "$(curl -s -o /dev/null -w "%{http_code}" "$B/degree_maps/admin/maps.php")" 302 "anonymous admin redirects"
chk "$(curl -s -o /dev/null -w "%{http_code}" -X POST "$B/degree_maps/admin/ajax.php?action=save_course")" 401 "anonymous ajax 401"
J=$S/ada.jar; rm -f $J
chk "$(curl -s -o /dev/null -c $J -b $J -w "%{http_code}" "$B/auth/login.php?as=nobody@wichita.edu")" 403 "unlisted user gets 403"
chk "$(curl -s -o /dev/null -c $J -b $J -w "%{http_code} %{redirect_url}" "$B/auth/login.php?as=ada.advisor@wichita.edu&return=/academics/majors/degree_maps/admin/maps.php")" "302 $B/degree_maps/admin/maps.php" "advisor login redirects back"
chk "$(GET -b $J "$B/degree_maps/admin/maps.php")" 200 "advisor admin page"; has $S/out.html 'Signed in as <strong>Ada Advisor' 'admin bar'
CSRF=$(grep -o 'name="csrf-token" content="[a-f0-9]*"' $S/out.html | grep -o '[a-f0-9]\{64\}'); [ -n "$CSRF" ] && ok "csrf token present" || bad "csrf token"
chk "$(GET -b $J "$B/_admin/index.php")" 403 "advisor blocked from majors admin"
chk "$(GET -b $J "$B/degree_maps/admin/manage_users.php")" 403 "advisor blocked from users"
chk "$(GET -b $J "$B/degree_maps/admin/help.php")" 200 "advisor help page"; has $S/out.html 'id="h-course"' 'help: add-a-course section'; has $S/out.html 'href="/academics/majors/degree_maps/admin/help.php"' 'help linked from admin bar'
chk "$(curl -s -o /dev/null -w "%{http_code}" "$B/degree_maps/admin/help.php")" 302 "anonymous help redirects to sign-in"

echo "[majors editor]"
M=$(mktemp); curl -s -o /dev/null -c $M -b $M -L "$B/auth/login.php?as=mia.marketing@wichita.edu&return=/academics/majors/_admin/index.php"
chk "$(GET -b $M "$B/_admin/index.php")" 200 "marketing: control panel"; has $S/out.html 'id="programs_table"' 'programs table'; has $S/out.html 'ma-panel-toolbar' 'panel toolbar'; has $S/out.html 'data-similar-btn' 'similar popover buttons'; has $S/out.html "href=\"/academics/majors/_admin/program.php?program=$PBN\"" 'Edit links by basename'
chk "$(GET -b $M "$B/_admin/program.php?program=$PBN")" 200 "marketing: in-place editor by basename"; has $S/out.html 'data-ma-part="card"' 'card part'; has $S/out.html 'data-ma-part="content"' 'content part'; has $S/out.html 'data-ma-part="similar"' 'similar part'; has $S/out.html 'data-ma-edit-bar' 'edit bar'; has $S/out.html 'data-ma-tools' 'section tools'; has $S/out.html 'data-ma-html="description"' 'description editable'; has $S/out.html 'data-ma-form="image"' 'photo form'; has $S/out.html 'admin/inplace.js' 'editor script'
chk "$(GET -b $M "$B/_admin/program.php?id=$PROG")" 200 "marketing: in-place editor by id"
chk "$(GET -b $M "$B/_admin/program.php?new=1")" 200 "marketing: new program form"; has $S/out.html 'data-ma-new-program' 'new program form'
chk "$(GET -b $M "$B/_admin/blocks.php")" 200 "marketing: shared blocks page"; has $S/out.html 'id="blocks_table"' 'blocks table'
chk "$(GET -b $J "$B/_admin/program.php?id=$PROG")" 403 "advisor blocked from the editor"
chk "$(GET "$B/index.php?program=$PBN")" 200 "public page by basename"; hasnt $S/out.html 'data-ma-' 'no editing markers on the public page'
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?id=$PROG")" "301 $B/index.php?program=$PBN" "?id redirects to the basename address"
chk "$(GET "$B/index.php?program=no_such_program_zz")" 404 "unknown basename"
RET_BN=$(Q "SELECT basename FROM majors_academic_programs WHERE status='retired' AND basename<>'' LIMIT 1"); [ -n "$RET_BN" ] && chk "$(GET "$B/index.php?program=$RET_BN")" 404 "retired program is not public"
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?program=$(echo $PBN | tr a-z A-Z)")" "301 $B/index.php?program=$PBN" "another spelling of the page name redirects to the stored one"
GET "$B/index.php?program=$PBN" >/dev/null; has $S/out.html "rel=\"canonical\" href=\"/academics/majors/index.php?program=$PBN\"" 'canonical link on the public page'
GET -b $M "$B/_admin/program.php?id=$PROG" >/dev/null; MC=$(grep -o 'name="csrf-token" content="[^"]*"' $S/out.html | head -1 | sed 's/.*content="//; s/"$//')
MP() { curl -s -b $M -H "X-CSRF-Token: $MC" "$@"; }
MA=$B/_admin/ajax.php
NAME=$(Q "SELECT academic_program FROM majors_academic_programs WHERE id=$PROG")
SIM0=$(Q "SELECT GROUP_CONCAT(similar_academic_program_id ORDER BY id) FROM majors_similar_programs WHERE main_academic_program_id=$PROG")
DESC0=$(Q "SELECT description FROM majors_academic_programs WHERE id=$PROG"); CH0=$(Q "SELECT COALESCE(credit_hours,'') FROM majors_academic_programs WHERE id=$PROG"); MOD0=$(Q "SELECT COALESCE(modality,'') FROM majors_academic_programs WHERE id=$PROG")
FLAGS0=$(Q "SELECT CONCAT_WS(',',COALESCE(graduate,'n'),COALESCE(online_learning,'n'),COALESCE(certificate,'n'),is_stem) FROM majors_academic_programs WHERE id=$PROG")
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "credit_hours=99" --data-urlencode "modality=Online" --data-urlencode "description=<p>E2E description</p>" "$MA?action=save_program"); echo "$R" | grep -q '"success":true' && ok "save_program" || bad "save_program: $R"; echo "$R" | grep -q '"parts":{' && ok "save_program returns the re-rendered parts" || bad "parts missing"
chk "$(Q "SELECT credit_hours FROM majors_academic_programs WHERE id=$PROG")" 99 "program facts saved"
chk "$(Q "SELECT CONCAT_WS(',',COALESCE(graduate,'n'),COALESCE(online_learning,'n'),COALESCE(certificate,'n'),is_stem) FROM majors_academic_programs WHERE id=$PROG")" "$FLAGS0" "partial save leaves the flags alone"
chk "$(Q "SELECT description FROM majors_programs_content WHERE academic_program_id=$PROG")" "<p>E2E description</p>" "flat row kept in step (ai-meta.php)"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "modality=Sideways" "$MA?action=save_program"); echo "$R" | grep -q 'Modality must be' && ok "save_program validates modality" || bad "modality validation: $R"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "academic_program=" "$MA?action=save_program"); echo "$R" | grep -q 'name is required' && ok "save_program refuses an empty name" || bad "empty name: $R"
MP --data-urlencode "program_id=$PROG" --data-urlencode "credit_hours=$CH0" --data-urlencode "modality=$MOD0" --data-urlencode "description=$DESC0" "$MA?action=save_program" >/dev/null
chk "$(Q "SELECT CONCAT(COALESCE(credit_hours,''),'|',COALESCE(modality,'')) FROM majors_academic_programs WHERE id=$PROG")" "$CH0|$MOD0" "test program's facts restored"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "college_url=javascript:alert(1)" "$MA?action=save_program"); echo "$R" | grep -q 'must be a web address' && ok "save_program refuses a javascript: college link" || bad "college_url: $R"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "academic_program=$NAME</script><script>alert(1)</script>" "$MA?action=save_program" >/dev/null; GET -b $M "$B/_admin/program.php?id=$PROG"); hasnt $S/out.html '</script><script>alert(1)</script>, ' 'a program name cannot break out of the inline config script'; MP --data-urlencode "program_id=$PROG" --data-urlencode "academic_program=$NAME" "$MA?action=save_program" >/dev/null
R=$(MP -d "program_id=$PROG&similar[]=999999&similar[]=$PROG" "$MA?action=save_similar"); echo "$R" | grep -q '"similar":\[\]' && ok "save_similar drops unknown ids and self" || bad "save_similar ids: $R"
R=$(curl -s -b $M "$MA?action=render_program&program_id=$PROG"); echo "$R" | grep -q '"parts":{' && ok "render_program" || bad "render_program: $R"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "kind=teaser" --data-urlencode "headline=E2E card" --data-urlencode "body=<p>e2e body</p>" --data-urlencode "links[text][]=Go" --data-urlencode "links[href][]=/x" "$MA?action=save_section"); SEC=$(echo "$R" | grep -o '"section_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$SEC" ] && ok "save_section → $SEC" || bad "save_section: $R"; echo "$R" | grep -q 'E2E card' && ok "parts carry the new card" || bad "parts html"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$SEC" --data-urlencode "headline=E2E card renamed" "$MA?action=save_section_fields"); echo "$R" | grep -q '"success":true' && ok "save_section_fields" || bad "save_section_fields: $R"
chk "$(Q "SELECT CONCAT(headline,'|',body) FROM majors_program_sections WHERE id=$SEC")" "E2E card renamed|<p>e2e body</p>" "per-field save changed only the headline"
BLK=$(Q "SELECT id FROM majors_content_blocks ORDER BY id LIMIT 1")
R=$(MP -d "program_id=$PROG&section_id=$SEC&block_id=$BLK" "$MA?action=swap_section_block"); echo "$R" | grep -q '"success":true' && ok "swap_section_block" || bad "swap: $R"
chk "$(Q "SELECT block_id=$BLK AND headline IS NULL FROM majors_program_sections WHERE id=$SEC")" 1 "section now shows the shared block"
chk "$(MP -o /dev/null -w "%{http_code}" --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$SEC" --data-urlencode "headline=x" "$MA?action=save_section_fields")" 409 "per-field save refused on a shared section"
R=$(MP -d "program_id=$PROG&section_id=$SEC" "$MA?action=detach_section"); echo "$R" | grep -q '"success":true' && ok "detach_section" || bad "detach: $R"
chk "$(Q "SELECT block_id IS NULL AND headline<>'' FROM majors_program_sections WHERE id=$SEC")" 1 "detached section owns the block's text"
chk "$(curl -s -o /dev/null -b $M -w "%{http_code}" -X POST --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$SEC" --data-urlencode "headline=x" "$MA?action=save_section_fields")" 419 "save_section_fields without the CSRF token → 419"
chk "$(curl -s -o /dev/null -b $M -w "%{http_code}" -X POST -d "program_id=$PROG&kind=feature&after=0" "$MA?action=add_section")" 419 "add_section without the CSRF token → 419"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$SEC" --data-urlencode "body=<p onclick=\"x()\">safe <script>alert(1)</script><a href=\"javascript:alert(2)\">link</a></p>" "$MA?action=save_section_fields"); echo "$R" | grep -q '"success":true' && ok "save_section_fields (unsafe html)" || bad "unsafe html: $R"
chk "$(Q "SELECT body FROM majors_program_sections WHERE id=$SEC")" "<p>safe link</p>" "rich text is sanitised on the way in"
R=$(MP -d "program_id=$PROG&kind=feature&after=$SEC" "$MA?action=add_section"); SEC2=$(echo "$R" | grep -o '"section_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$SEC2" ] && ok "add_section after → $SEC2" || bad "add_section: $R"
chk "$(Q "SELECT COALESCE(headline,'')='' AND COALESCE(body,'')='' FROM majors_program_sections WHERE id=$SEC2")" 1 "a new section starts blank"
GET "$B/index.php?program=$PBN" >/dev/null; hasnt $S/out.html "data-section=\"$SEC2\"" 'a blank section is not on the public page'
GET -b $M "$B/_admin/program.php?program=$PBN" >/dev/null; has $S/out.html "data-section=\"$SEC2\"" 'a blank section is on the editor page'
chk "$(Q "SELECT (SELECT position FROM majors_program_sections WHERE id=$SEC2) = (SELECT position FROM majors_program_sections WHERE id=$SEC) + 1")" 1 "new section sits right after"
chk "$(Q "SELECT CONCAT(kind,'|',label) FROM majors_program_sections WHERE id=$SEC2")" "feature|Inside the Program" "new feature has the band label"
R=$(MP -d "program_id=$PROG&section_id=$SEC2&dir=up" "$MA?action=move_section"); echo "$R" | grep -q '"success":true' && ok "move_section" || bad "move: $R"
chk "$(Q "SELECT (SELECT position FROM majors_program_sections WHERE id=$SEC2) < (SELECT position FROM majors_program_sections WHERE id=$SEC)")" 1 "section moved up"
OTHER_SEC=$(Q "SELECT id FROM majors_program_sections WHERE program_id<>$PROG LIMIT 1")
chk "$(MP -o /dev/null -w "%{http_code}" --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$OTHER_SEC" --data-urlencode "headline=x" "$MA?action=save_section_fields")" 422 "another program's section is refused"
R=$(MP -d "program_id=$PROG&section_id=$SEC2" "$MA?action=delete_section"); echo "$R" | grep -q '"success":true' && ok "delete_section (feature)" || bad "delete: $R"; echo "$R" | grep -q '"removed":{"kind":"feature"' && ok "delete_section returns what was removed (for Undo)" || bad "removed payload: $R"
FIRST=$(Q "SELECT id FROM majors_program_sections WHERE program_id=$PROG AND kind IN ('teaser','feature') ORDER BY position LIMIT 1")
R=$(MP -d "program_id=$PROG&section_id=$FIRST" "$MA?action=delete_section"); echo "$R" | grep -q '"after":-1' && ok "removing the first section reports after=-1" || bad "first removal: $R"
RK=$(echo "$R" | python3 -c "import sys,json; d=json.load(sys.stdin)['removed']; print(d['kind'], d.get('block_id') or '')"); set -- $RK
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "kind=$1" --data-urlencode "after=-1" --data-urlencode "block_id=$2" --data-urlencode "headline=$(echo "$R" | python3 -c "import sys,json; print(json.load(sys.stdin)['removed']['headline'])")" --data-urlencode "body=$(echo "$R" | python3 -c "import sys,json; print(json.load(sys.stdin)['removed']['body'])")" "$MA?action=add_section"); NEWFIRST=$(echo "$R" | grep -o '"section_id":[0-9]*' | grep -o '[0-9]*$')
chk "$(Q "SELECT id FROM majors_program_sections WHERE program_id=$PROG AND kind IN ('teaser','feature') ORDER BY position LIMIT 1")" "$NEWFIRST" "undo of a first-section removal puts it back first"
R=$(MP -d "program_id=$PROG&section_id=$SEC" "$MA?action=delete_section"); echo "$R" | grep -q '"success":true' && ok "delete_section (card)" || bad "delete: $R"
R=$(curl -s -b $M "$MA?action=program_search&q=engineering"); echo "$R" | grep -q '"results":\[{' && ok "program_search" || bad "search: $R"
OTHER=$(Q "SELECT id FROM majors_academic_programs WHERE status='active' AND id<>$PROG ORDER BY id LIMIT 1")
R=$(MP -d "program_id=$PROG&similar[]=$OTHER" "$MA?action=save_similar"); echo "$R" | grep -q '"success":true' && ok "save_similar" || bad "similar: $R"; echo "$R" | grep -q "\"similar\":\[{\"id\":$OTHER" && ok "save_similar returns the list" || bad "similar list"
SIMARGS=""; for id in ${SIM0//,/ }; do SIMARGS="$SIMARGS -d similar[]=$id"; done; [ -n "$SIM0" ] && MP -d "program_id=$PROG" $SIMARGS "$MA?action=save_similar" >/dev/null || MP -d "program_id=$PROG&similar[]=" "$MA?action=save_similar" >/dev/null
chk "$(Q "SELECT COALESCE(GROUP_CONCAT(similar_academic_program_id ORDER BY id),'') FROM majors_similar_programs WHERE main_academic_program_id=$PROG")" "$SIM0" "similar programs restored"
R=$(curl -s -b $M "$MA?action=list_images"); echo "$R" | grep -q '"images":\[' && ok "list_images" || bad "list_images: $R"
R=$(curl -s -b $M "$MA?action=basename_preview&academic_program=$(printf %s "$NAME" | sed 's/ /%20/g')&program_type=E2E"); echo "$R" | grep -q '"basename":"' && ok "basename_preview" || bad "basename_preview: $R"
R=$(curl -s -b $M "$MA?action=basename_preview&basename=$PBN"); echo "$R" | grep -q "\"basename\":\"${PBN}_2\"" && ok "basename_preview avoids a taken name" || bad "basename uniqueness: $R"
chk "$(GET -b $M "$MA?action=get_settings_form&program_id=$PROG")" 200 "settings form"; has $S/out.html 'data-ma-settings-form' 'settings form markup'
chk "$(GET -b $M "$MA?action=get_listings_form&program_id=$LP")" 200 "listings form"; has $S/out.html 'Counseling - School' 'listings form shows the program lines'
Q "DROP TABLE IF EXISTS e2e_listing_backup; CREATE TABLE e2e_listing_backup AS SELECT * FROM majors_listing_entries WHERE program_id=$LP" >/dev/null
R=$(MP --data-urlencode "program_id=$LP" --data-urlencode "entries[n1][name]=E2E Counseling" --data-urlencode "entries[n1][lists][]=all" --data-urlencode "entries[n1][lists][]=graduate" --data-urlencode "entries[n1][shown_in]=both" --data-urlencode "entries[n2][name]=Counseling, E2E" --data-urlencode "entries[n2][lists][]=all" --data-urlencode "entries[n2][shown_in]=az" "$MA?action=save_listings"); echo "$R" | grep -q '"lines":2' && ok "save_listings" || bad "save_listings: $R"
chk "$(Q "SELECT GROUP_CONCAT(CONCAT(name,':',lists,':',shown_in,':',source) ORDER BY position SEPARATOR ' | ') FROM majors_listing_entries WHERE program_id=$LP")" "E2E Counseling:all,graduate:both:editor | Counseling, E2E:all:az:editor" "listing lines stored in order"
GET "$B/index.php" >/dev/null; has $S/out.html '>Counseling, E2E</a>' 'A-Z entry on All Programs'; GET "$B/index_by_college.php" >/dev/null; hasnt $S/out.html '>Counseling, E2E</a>' 'A-Z-only entry not in the by-college view'
R=$(MP --data-urlencode "program_id=$LP" --data-urlencode "entries[n1][name]=E2E" --data-urlencode "entries[n1][lists][]=certificates" "$MA?action=save_listings"); echo "$R" | grep -q 'Graduate or Undergraduate' && ok "a Certificates line needs its half of the page" || bad "cert validation: $R"
R=$(MP --data-urlencode "program_id=$LP" "$MA?action=save_listings"); echo "$R" | grep -q 'Not listed' && ok "no lines means not listed" || bad "unlist: $R"
GET "$B/index.php" >/dev/null; hasnt $S/out.html "program=counseling_med_41\"" 'an unlisted program is on no listing'; chk "$(GET "$B/index.php?program=counseling_med_41")" 200 "an unlisted program's page stays live"
Q "DELETE FROM majors_listing_entries WHERE program_id=$LP; INSERT INTO majors_listing_entries SELECT * FROM e2e_listing_backup; DROP TABLE e2e_listing_backup" >/dev/null
chk "$(Q "SELECT COUNT(*) FROM majors_listing_entries WHERE program_id=$LP AND source='cms'")" 4 "listing lines restored"
R=$(MP --data-urlencode "academic_program=E2E Test Program" --data-urlencode "credential=Minor" "$MA?action=new_program"); NP=$(echo "$R" | grep -o '"program_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$NP" ] && ok "new_program → $NP" || bad "new_program: $R"
chk "$(Q "SELECT basename FROM majors_academic_programs WHERE id=$NP")" "e2e_test_program_minor" "new program gets <name>_<credential> as its page name"
chk "$(Q "SELECT CONCAT(lists,':',source) FROM majors_listing_entries WHERE program_id=$NP")" "all,undergrad:editor" "a new Minor starts listed on All and Undergrad"
Q "DELETE FROM majors_listing_entries WHERE program_id=$NP" >/dev/null; echo "$R" | grep -q 'program.php?program=e2e_test_program_minor' && ok "redirect goes to the in-place editor by basename" || bad "redirect: $R"
Q "DELETE FROM majors_programs_content WHERE academic_program_id=$NP; DELETE FROM majors_academic_programs WHERE id=$NP" >/dev/null
echo "[full-width sections, photo, departments]"
R=$(MP -d "program_id=$PROG&kind=band&after=0" "$MA?action=add_section"); BAND=$(echo "$R" | grep -o '"section_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$BAND" ] && ok "add a full-width section → $BAND" || bad "add band: $R"
chk "$(Q "SELECT CONCAT(kind,'|',COALESCE(theme,'')) FROM majors_program_sections WHERE id=$BAND")" "band|light" "a new full-width section starts on light gray"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$BAND" --data-urlencode "headline=E2E band" --data-urlencode "body=<p>e2e band body</p>" --data-urlencode "theme=yellow" "$MA?action=save_section_fields"); echo "$R" | grep -q '"success":true' && ok "save a band's text and background" || bad "band fields: $R"
chk "$(Q "SELECT CONCAT(theme,'|',headline) FROM majors_program_sections WHERE id=$BAND")" "yellow|E2E band" "band background stored"
GET "$B/index.php?program=$PBN" >/dev/null; has $S/out.html "section-wrap--wheat majors-section majors-band\" data-section=\"$BAND\"" 'the yellow band is on the public page (current design: wheat)'
GET -b $M "$B/_admin/program.php?program=$PBN" >/dev/null; has $S/out.html "data-section=\"$BAND\" data-ma-kind=\"band\" data-ma-theme=\"yellow\"" 'the editor marks the band with its background'
chk "$(MP -o /dev/null -w "%{http_code}" --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$BAND" --data-urlencode "theme=purple" "$MA?action=save_section_fields")" 422 "an unknown background is refused"
CARD=$(Q "SELECT id FROM majors_program_sections WHERE program_id=$PROG AND kind='teaser' AND block_id IS NULL ORDER BY position LIMIT 1")
[ -n "$CARD" ] && chk "$(MP -o /dev/null -w "%{http_code}" --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$CARD" --data-urlencode "theme=dark" "$MA?action=save_section_fields")" 422 "a card takes no background"
R=$(MP -d "program_id=$PROG&section_id=$BAND" "$MA?action=delete_section"); echo "$R" | grep -q '"theme":"yellow"' && ok "removing a band reports its background (for Undo)" || bad "band removed payload: $R"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "kind=band" --data-urlencode "after=0" --data-urlencode "theme=yellow" --data-urlencode "headline=E2E band" --data-urlencode "body=<p>e2e band body</p>" "$MA?action=add_section"); BAND=$(echo "$R" | grep -o '"section_id":[0-9]*' | grep -o '[0-9]*$')
chk "$(Q "SELECT CONCAT(kind,'|',theme,'|',headline) FROM majors_program_sections WHERE id=$BAND")" "band|yellow|E2E band" "Undo brings the band back with its background"
MP -d "program_id=$PROG&section_id=$BAND" "$MA?action=delete_section" >/dev/null
Q "DROP TABLE IF EXISTS e2e_prog_backup; CREATE TABLE e2e_prog_backup AS SELECT * FROM majors_academic_programs WHERE id=$PROG; DROP TABLE IF EXISTS e2e_flat_backup; CREATE TABLE e2e_flat_backup AS SELECT * FROM majors_programs_content WHERE academic_program_id=$PROG" >/dev/null
DEP0=$(Q "SELECT CONCAT_WS('|',COALESCE(department,'-'),COALESCE(department_url,'-'),COALESCE(more_departments,'-'),COALESCE(similar_bg_url,'-')) FROM majors_academic_programs WHERE id=$PROG")
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "similar_bg_url=/academics/majors/_images/e2e_bg.jpg" "$MA?action=save_program"); echo "$R" | grep -q '"success":true' && ok "save the Similar Programs photo" || bad "similar_bg: $R"
GET "$B/index.php?program=$PBN" >/dev/null; has $S/out.html 'e2e_bg.jpg' 'the chosen photo is behind Similar Programs'
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "similar_bg_url=javascript:alert(1)" "$MA?action=save_program"); echo "$R" | grep -q 'must be a web address' && ok "an unsafe photo address is refused" || bad "similar_bg unsafe: $R"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "departments[text][]=E2E Main Department" --data-urlencode "departments[href][]=/academics/e2e-main/" --data-urlencode "departments[text][]=E2E Second Department" --data-urlencode "departments[href][]=/academics/e2e-second/" "$MA?action=save_program"); echo "$R" | grep -q '"success":true' && ok "save two departments" || bad "departments: $R"
chk "$(Q "SELECT CONCAT(department,'|',department_url,'|',more_departments) FROM majors_academic_programs WHERE id=$PROG")" 'E2E Main Department|/academics/e2e-main/|[{"text":"E2E Second Department","href":"/academics/e2e-second/"}]' "the first is the main department, the rest are kept in order"
GET "$B/index.php?program=$PBN" >/dev/null; has $S/out.html 'href="/academics/e2e-second/"' 'the page links the second department'
GET "$B/index.php?department=E2E%20Second%20Department" >/dev/null; has $S/out.html "program=$PBN\"" 'the program lists under its second department'
chk "$(Q "SELECT program_links LIKE '%E2E Second Department%' FROM majors_programs_content WHERE academic_program_id=$PROG")" 1 "the flat row lists every department"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "departments[text][]=E2E" --data-urlencode "departments[href][]=javascript:alert(1)" "$MA?action=save_program"); echo "$R" | grep -q 'must be a web address' && ok "an unsafe department link is refused" || bad "department link: $R"
Q "UPDATE majors_academic_programs p JOIN e2e_prog_backup b ON b.id=p.id SET p.department=b.department, p.department_url=b.department_url, p.more_departments=b.more_departments, p.similar_bg_url=b.similar_bg_url; DELETE FROM majors_programs_content WHERE academic_program_id=$PROG; INSERT INTO majors_programs_content SELECT * FROM e2e_flat_backup; DROP TABLE e2e_prog_backup; DROP TABLE e2e_flat_backup" >/dev/null
chk "$(Q "SELECT CONCAT_WS('|',COALESCE(department,'-'),COALESCE(department_url,'-'),COALESCE(more_departments,'-'),COALESCE(similar_bg_url,'-')) FROM majors_academic_programs WHERE id=$PROG")" "$DEP0" "departments and photo restored"

echo "[renames and forwarding]"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "basename=${PBN}_renamed" "$MA?action=save_program"); echo "$R" | grep -q 'CMS import is still in use' && ok "an imported page's name stays fixed while the CMS import runs" || bad "imported rename: $R"
chk "$(GET -b $M "$MA?action=get_settings_form&program_id=$PROG")" 200 "settings form (imported)"; has $S/out.html 'it is fixed for now' 'the settings form says why the name is fixed'
R=$(MP --data-urlencode "academic_program=E2E Rename Program" --data-urlencode "credential=Minor" "$MA?action=new_program"); RP=$(echo "$R" | grep -o '"program_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$RP" ] && ok "a program to rename → $RP" || bad "new_program: $R"
RB0=$(Q "SELECT basename FROM majors_academic_programs WHERE id=$RP")
R=$(MP --data-urlencode "program_id=$RP" --data-urlencode "basename=e2e_renamed_minor" "$MA?action=save_program"); echo "$R" | grep -q '"success":true' && ok "rename a program made in the editor" || bad "rename: $R"
chk "$(Q "SELECT basename FROM majors_academic_programs WHERE id=$RP")" "e2e_renamed_minor" "the new page name is stored"
chk "$(Q "SELECT program_id FROM majors_program_aliases WHERE basename='$RB0'")" "$RP" "the old name is kept as an earlier address"
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?program=$RB0")" "301 $B/index.php?program=e2e_renamed_minor" "the old public address forwards to the new one"
curl -s -D $S/hdr.txt -o /dev/null "$B/index.php?program=$RB0"; has $S/hdr.txt '^Cache-Control: no-cache' 'the forward is not remembered by browsers (a name can be changed back)'
chk "$(curl -s -o /dev/null -b $M -w "%{http_code} %{redirect_url}" "$B/_admin/program.php?program=$RB0")" "302 $B/_admin/program.php?program=e2e_renamed_minor" "the old editor address forwards too"
chk "$(GET -b $M "$MA?action=get_settings_form&program_id=$RP")" 200 "settings form (renamed)"; has $S/out.html "?program=$RB0" 'the settings form lists the earlier address'; hasnt $S/out.html 'it is fixed for now' 'a program made in the editor can be renamed'
R=$(MP --data-urlencode "academic_program=E2E Other Program" --data-urlencode "credential=Minor" "$MA?action=new_program"); RP2=$(echo "$R" | grep -o '"program_id":[0-9]*' | grep -o '[0-9]*$'); RB2=$(Q "SELECT basename FROM majors_academic_programs WHERE id=$RP2")
R=$(MP --data-urlencode "program_id=$RP2" --data-urlencode "basename=$RB0" "$MA?action=save_program"); echo "$R" | grep -q 'earlier page name' && ok "another program cannot take a name that still forwards" || bad "alias conflict: $R"
R=$(curl -s -b $M "$MA?action=basename_preview&basename=$RB0"); echo "$R" | grep -q "\"basename\":\"${RB0}_2\"" && ok "a new page name steers clear of earlier names" || bad "alias uniqueness: $R"
R=$(MP --data-urlencode "program_id=$RP" --data-urlencode "basename=$RB0" "$MA?action=save_program"); echo "$R" | grep -q '"success":true' && ok "rename back to the first name" || bad "rename back: $R"
chk "$(Q "SELECT GROUP_CONCAT(basename ORDER BY basename) FROM majors_program_aliases WHERE program_id=$RP")" "e2e_renamed_minor" "renaming back frees the first name and keeps the second as an earlier address"
chk "$(GET "$B/index.php?program=$RB0")" 200 "the first name serves the page again"
OTHER_BN=$(Q "SELECT basename FROM majors_academic_programs WHERE id=$OTHER"); OTHER_NAME=$(Q "SELECT academic_program FROM majors_academic_programs WHERE id=$OTHER")
R=$(MP --data-urlencode "program_id=$RP" --data-urlencode "status=retired" --data-urlencode "forward_to=$OTHER" "$MA?action=save_program"); echo "$R" | grep -q '"success":true' && ok "retire and forward to another program" || bad "forward: $R"
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?program=$RB0")" "301 $B/index.php?program=$OTHER_BN" "a retired program forwards to the page it was combined into"
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?program=e2e_renamed_minor")" "301 $B/index.php?program=$OTHER_BN" "so do its earlier addresses"
MP --data-urlencode "program_id=$RP2" --data-urlencode "status=retired" --data-urlencode "forward_to=$RP" "$MA?action=save_program" >/dev/null
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?program=$RB2")" "301 $B/index.php?program=$OTHER_BN" "forwarding follows a chain of retired programs to the active one"
R=$(MP --data-urlencode "program_id=$RP" --data-urlencode "forward_to=$RP" "$MA?action=save_program"); echo "$R" | grep -q 'cannot forward to itself' && ok "a program cannot forward to itself" || bad "self forward: $R"
R=$(MP --data-urlencode "program_id=$RP" --data-urlencode "forward_to=999999" "$MA?action=save_program"); echo "$R" | grep -q 'no longer exists' && ok "forwarding to a missing program is refused" || bad "missing forward: $R"
GET -b $M "$B/_admin/index.php" >/dev/null; has $S/out.html "Forwards to $OTHER_NAME" 'the control panel shows where a retired program forwards'
GET -b $M "$MA?action=get_settings_form&program_id=$RP" >/dev/null; has $S/out.html 'data-ma-forward-chosen><span data-ma-forward-label>' 'the settings form shows the chosen forward'
MP --data-urlencode "program_id=$RP" --data-urlencode "forward_to=" "$MA?action=save_program" >/dev/null
chk "$(Q "SELECT COALESCE(forward_to,'none') FROM majors_academic_programs WHERE id=$RP")" "none" "clearing the forward stores none"
chk "$(GET "$B/index.php?program=$RB0")" 404 "a retired program with no forward is not found"
chk "$(GET "$B/index.php?program=$RB2")" 404 "a chain that ends at a retired program is not found"
printf '<?php\nreturn ["majors" => ["cms_import" => false]];\n' > app/config/app.www-e2e.php
OUT=$(MAJORS_SITE=www-e2e php app/bin/majors-import.php --dry-run 2>&1); RC=$?; [ $RC -eq 1 ] && echo "$OUT" | grep -q 'switched off' && ok "the page import refuses once the CMS import is switched off" || bad "import refusal ($RC): $OUT"
OUT=$(MAJORS_SITE=www-e2e php app/bin/majors-listing-import.php --dry-run 2>&1); RC=$?; [ $RC -eq 1 ] && echo "$OUT" | grep -q 'switched off' && ok "so does the listing import" || bad "listing import refusal ($RC): $OUT"
RENAME='$app = require "app/bootstrap.php"; (new Majors\Majors\ProgramEditor($app->db(), (bool) $app->config->get("majors.cms_import", true)))->saveProgram((int) $argv[1], ["basename" => $argv[2]]); echo "ok";'
OUT=$(MAJORS_SITE=www-e2e php -r "$RENAME" $PROG ${PBN}_e2e 2>&1); [ "$OUT" = "ok" ] && ok "with the CMS import switched off, an imported page can be renamed" || bad "rename with import off: $OUT"
chk "$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" "$B/index.php?program=$PBN")" "301 $B/index.php?program=${PBN}_e2e" "and its CMS address forwards to the new name"
OUT=$(MAJORS_SITE=www-e2e php -r "$RENAME" $PROG $PBN 2>&1); [ "$OUT" = "ok" ] || bad "rename back: $OUT"
rm -f app/config/app.www-e2e.php
Q "DELETE FROM majors_program_aliases WHERE program_id=$PROG" >/dev/null
chk "$(Q "SELECT basename FROM majors_academic_programs WHERE id=$PROG")" "$PBN" "the imported page name restored"
for P in $RP $RP2; do Q "DELETE FROM majors_listing_entries WHERE program_id=$P; DELETE FROM majors_program_aliases WHERE program_id=$P; DELETE FROM majors_program_sections WHERE program_id=$P; DELETE FROM majors_programs_content WHERE academic_program_id=$P; DELETE FROM majors_academic_programs WHERE id=$P" >/dev/null; done
chk "$(Q "SELECT COUNT(*) FROM majors_program_aliases")" 0 "test programs and their earlier names removed"

echo "[degree maps admin as advisor]"
chk "$(GET -b $J "$B/degree_maps/admin/maps.php?degree_map_id=$ENG2027")" 200 "advisor views own-college current-year map"; has $S/out.html 'id="cloneMap"' 'clone offered'; hasnt $S/out.html 'name="editMap"' 'no edit on current year'
chk "$(GET -b $J "$B/degree_maps/admin/maps.php?degree_map_id=$LAS2027")" 200 "advisor views other-college map"; hasnt $S/out.html 'id="cloneMap"' 'no clone outside own colleges'
chk "$(GET -b $J "$B/degree_maps/admin/maps.php?degree_map_id=$ENG2027&editMap=Edit")" 200 "advisor asks to edit current-year map"; hasnt $S/out.html 'id="degree-map-editor"' 'advisor gets the view, not the editor'
chk "$(GET -b $J "$B/degree_maps/admin/maps.php?selected_year=2027")" 200 "advisor listing"; hasnt $S/out.html 'dm-flag' 'no duplicate tags (none exist after 002)' 

echo "[ajax as advisor]"
A="$B/degree_maps/admin/ajax.php"
P() { curl -s -b $J -H "X-CSRF-Token: $CSRF" -X POST "$@"; }
chk "$(curl -s -o /dev/null -b $J -w "%{http_code}" -X POST -d "degree_map_id=$ENG2027" "$A?action=clone_degree_map")" 419 "POST without csrf → 419"
R=$(P -d "degree_map_id=$LAS2027" "$A?action=clone_degree_map"); echo "$R" | grep -q 'own college' && ok "clone other college → refused" || bad "clone other college: $R"
R=$(P -d "degree_map_id=$ENG2027" "$A?action=clone_degree_map"); NEW=$(echo "$R" | grep -o '"degree_map_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$NEW" ] && ok "clone → new map $NEW" || bad "clone: $R"
chk "$(Q "SELECT academic_year FROM degree_maps WHERE id=$NEW")" 2028 "clone is next catalog year"
chk "$(Q "SELECT (SELECT COUNT(*) FROM degree_maps_courses WHERE degree_map_id=$NEW)=(SELECT COUNT(*) FROM degree_maps_courses WHERE degree_map_id=$ENG2027)")" 1 "clone copied all courses"
chk "$(Q "SELECT (SELECT COUNT(*) FROM degree_maps_footnotes WHERE degree_map_id=$NEW)=(SELECT COUNT(*) FROM degree_maps_footnotes WHERE degree_map_id=$ENG2027)")" 1 "clone copied footnotes"
R=$(P -d "degree_map_id=$ENG2027" "$A?action=clone_degree_map"); echo "$R" | grep -q '"existing":true' && ok "second clone reuses existing" || bad "second clone: $R"
chk "$(GET -b $J "$B/degree_maps/admin/maps.php?degree_map_id=$NEW&editMap=Edit")" 200 "editor page"; has $S/out.html 'id="degree-map-editor"' 'editor body'; has $S/out.html 'class="semester-list"' 'drop lists'
chk "$(GET -b $J "$A?action=get_degree_map&degree_map_id=$NEW")" 200 "get_degree_map html"; has $S/out.html 'edit_map_hours' 'editor reload'
R=$(P -d "degree_map_id=$NEW&year=1&semester=3" "$A?action=edit_course"); echo "$R" | grep -q 'editCourseModal' && ok "new course modal" || bad "edit_course: $R"
R=$(P -d "degree_map_id=$NEW&course_id=0&course_info=TEST 101 Testing&hours=3&year=1&semester=3&order=&sge=020&extra=e2e" "$A?action=save_course"); CID=$(echo "$R" | grep -o '"course_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$CID" ] && ok "save_course → $CID" || bad "save_course: $R"
R=$(P -d "degree_map_id=$NEW&id=$CID" "$A?action=edit_course"); echo "$R" | grep -q 'TEST 101' && ok "edit existing course modal" || bad "edit existing: $R"
R=$(P -d "degree_map_id=$NEW&updatedOrder=[{\"id\":$CID,\"order\":1,\"year\":2,\"semester\":1}]" "$A?action=save_course_order"); echo "$R" | grep -q '"success":true' && ok "save_course_order" || bad "order: $R"
chk "$(Q "SELECT CONCAT(year,'-',semester) FROM degree_maps_courses WHERE id=$CID")" "2-1" "course moved to year 2 fall"
R=$(P -d "degree_map_id=$NEW" "$A?action=edit_map_footnotes"); echo "$R" | grep -q 'editFootnotesModal' && ok "footnotes modal" || bad "footnotes modal: $R"
R=$(P --data-urlencode "degree_map_id=$NEW" --data-urlencode "footnotes[new_1][id]=new_1" --data-urlencode "footnotes[new_1][note]=E2E footnote" "$A?action=save_map_footnotes"); echo "$R" | grep -q '"success":true' && ok "save_map_footnotes" || bad "footnotes save: $R"
FN=$(Q "SELECT id FROM degree_maps_footnotes WHERE degree_map_id=$NEW AND note='E2E footnote'"); [ -n "$FN" ] && ok "footnote inserted $FN" || bad "footnote not inserted"
R=$(curl -s -b $J "$A?action=get_footnotes&degree_map_id=$NEW"); echo "$R" | grep -q 'E2E footnote' && ok "get_footnotes" || bad "get_footnotes: $R"
R=$(P -d "degree_map_id=$NEW&remove_footnote=$FN" "$A?action=delete_footnote"); echo "$R" | grep -q '"success":true' && ok "delete_footnote" || bad "delete_footnote: $R"
R=$(P -d "degree_map_id=$NEW" "$A?action=edit_map_hours"); echo "$R" | grep -q 'editHoursModal' && ok "hours modal" || bad "hours modal: $R"
R=$(P --data-urlencode "degree_map_id=$NEW" --data-urlencode "hours_to_graduate=121" --data-urlencode "degree_map[hours][1][1]=14" --data-urlencode "degree_map[hours][1][total_hours]=29" "$A?action=save_map_hours"); echo "$R" | grep -q '"success":true' && ok "save_map_hours" || bad "hours save: $R"
chk "$(Q "SELECT hours_to_graduate FROM degree_maps WHERE id=$NEW")" 121 "hours_to_graduate saved"
R=$(P -d "degree_map_id=$NEW" "$A?action=edit_map"); echo "$R" | grep -q 'editMapModal' && ok "map details modal" || bad "edit_map: $R"
R=$(P --data-urlencode "degree_map_id=$NEW" --data-urlencode "major=E2E Renamed" --data-urlencode "degree_type=BS" --data-urlencode "college=College of Engineering" --data-urlencode "academic_year_hidden=2028" --data-urlencode "note=e2e note" "$A?action=save_map_details"); echo "$R" | grep -q '"success":true' && ok "save_map_details" || bad "details: $R"
R=$(P --data-urlencode "degree_map_id=$NEW" --data-urlencode "major=X" --data-urlencode "degree_type=BS" --data-urlencode "college=Fairmount College of Liberal Arts and Sciences" --data-urlencode "academic_year_hidden=2028" "$A?action=save_map_details"); echo "$R" | grep -q 'own college' && ok "cannot move map to another college" || bad "college move: $R"
R=$(curl -s -b $J "$A?action=course_autocomplete&term=ENGL%201"); echo "$R" | grep -q 'scbcrse_subj_code' && ok "course_autocomplete" || bad "autocomplete: $R"
R=$(P -d "college=College%20of%20Engineering" "$A?action=get_departments"); echo "$R" | grep -q '<option' && ok "get_departments" || bad "departments: $R"
R=$(P -d "course_id=$CID" "$A?action=delete_course"); echo "$R" | grep -q '"success":true' && ok "delete_course" || bad "delete_course: $R"
R=$(P -d "degree_map_id=$NEW" "$A?action=delete_degree_map"); echo "$R" | grep -q 'permission' && ok "advisor cannot delete map" || bad "advisor delete: $R"
R=$(P -d "degree_map_id=$LAS2027&year=1&semester=1" "$A?action=edit_course"); echo "$R" | grep -qi 'read-only\|own college' && ok "edit on other college/current year refused" || bad "cross-college edit: $R"
R=$(P -d "major=New&degree_type=BA&college=College of Engineering&academic_year=2027" "$A?action=save_map_details"); echo "$R" | grep -q 'future catalog year' && ok "new map must be future year" || bad "future year check: $R"

echo "[super admin + users]"
K=$S/mike.jar; rm -f $K
curl -s -o /dev/null -c $K -b $K "$B/auth/login.php?as=mike.marlett@wichita.edu"
chk "$(GET -b $K "$B/degree_maps/admin/manage_users.php")" 200 "users page"
CS=$(grep -o 'name="csrf-token" content="[a-f0-9]*"' $S/out.html | grep -o '[a-f0-9]\{64\}')
PK() { curl -s -b $K -H "X-CSRF-Token: $CS" -X POST "$@"; }
R=$(curl -s -b $K "$A?action=get_users"); echo "$R" | grep -q 'ada.advisor' && ok "get_users" || bad "get_users: $R"
R=$(curl -s -b $K "$A?action=get_user_form&user_id=2"); echo "$R" | grep -q 'value="Ada"' && ok "get_user_form" || bad "user form: $R"
R=$(PK --data-urlencode "first_name=Tom" --data-urlencode "last_name=Temp" --data-urlencode "email=tom.temp@wichita.edu" --data-urlencode "netid=t123t456" --data-urlencode "role=advisor" --data-urlencode "colleges[]=3" --data-urlencode "colleges[]=4" "$A?action=save_user"); UID_=$(echo "$R" | grep -o '"user_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$UID_" ] && ok "save_user → $UID_" || bad "save_user: $R"
R=$(PK --data-urlencode "first_name=No" --data-urlencode "last_name=Netid" --data-urlencode "email=no.netid@wichita.edu" --data-urlencode "role=advisor" "$A?action=save_user"); echo "$R" | grep -q "myWSU ID is required" && ok "save_user refuses a row without netid" || bad "netid required: $R"
chk "$(Q "SELECT GROUP_CONCAT(college_id ORDER BY college_id) FROM majors_user_colleges WHERE user_id=$UID_")" "3,4" "user colleges saved"
R=$(PK -d "user_id=$UID_" "$A?action=delete_user"); echo "$R" | grep -q '"success":true' && ok "delete_user" || bad "delete_user: $R"
R=$(PK -d "user_id=1&first_name=Mike&last_name=Marlett&email=mike.marlett@wichita.edu&netid=q262t958&role=advisor" "$A?action=save_user"); echo "$R" | grep -q 'own super admin' && ok "cannot demote self" || bad "self demote: $R"
chk "$(GET -b $K "$B/_admin/index.php")" 200 "super admin sees majors admin"; has $S/out.html 'id="programs_table"' 'program inventory'
chk "$(GET -b $K "$B/degree_maps/admin/maps.php?degree_map_id=$ENG2027&editMap=Edit")" 200 "super admin may edit a published map"; has $S/out.html 'id="degree-map-editor"' 'editor shown'; has $S/out.html 'published map for a current or past' 'published-map warning'
R=$(PK --data-urlencode "major=Aerospace Engineering" --data-urlencode "degree_type=BS" --data-urlencode "college=College of Engineering" --data-urlencode "academic_year=2028" "$A?action=save_map_details"); echo "$R" | grep -q '"success":true' && DUP=$(echo "$R" | grep -o '"degree_map_id":[0-9]*' | grep -o '[0-9]*$') && ok "super admin creates 2028 map $DUP" || bad "create: $R"
R=$(PK --data-urlencode "major=Aerospace Engineering" --data-urlencode "degree_type=BS" --data-urlencode "college=College of Engineering" --data-urlencode "academic_year=2028" "$A?action=save_map_details"); echo "$R" | grep -q "\"existing\":true" && ok "second create refused with existing id" || bad "dup create: $R"
R=$(PK -d "degree_map_id=$DUP" "$A?action=delete_degree_map"); echo "$R" | grep -q '"success":true' && ok "cleanup 2028 map" || bad "cleanup: $R"
R=$(PK -d "degree_map_id=$NEW" "$A?action=delete_degree_map"); echo "$R" | grep -q '"success":true' && ok "super admin deletes the e2e map" || bad "delete map: $R"
chk "$(Q "SELECT COUNT(*) FROM degree_maps WHERE id=$NEW")" 0 "map gone"; chk "$(Q "SELECT COUNT(*) FROM degree_maps_courses WHERE degree_map_id=$NEW")" 0 "courses gone"

echo "[advisor admin]"
php app/bin/add-user.php aaron.admin@wichita.edu advisor_admin Aaron Admin a999a999 >/dev/null
N=$S/aaron.jar; rm -f $N; curl -s -o /dev/null -c $N -b $N "$B/auth/login.php?as=aaron.admin@wichita.edu"
chk "$(GET -b $N "$B/degree_maps/admin/maps.php?degree_map_id=$LAS2027")" 200 "advisor admin views another college's map"; has $S/out.html 'id="cloneMap"' 'clone offered across colleges'
chk "$(GET -b $N "$B/degree_maps/admin/maps.php?degree_map_id=$LAS2027&editMap=Edit")" 200 "advisor admin asks to edit published map"; hasnt $S/out.html 'id="degree-map-editor"' 'published year still read-only for advisor admin'
chk "$(GET -b $N "$B/_admin/index.php")" 403 "advisor admin blocked from majors admin"
chk "$(GET -b $N "$B/degree_maps/admin/manage_users.php")" 403 "advisor admin blocked from users"
GET -b $N "$B/degree_maps/admin/maps.php" >/dev/null; CN=$(grep -o 'name="csrf-token" content="[a-f0-9]*"' $S/out.html | grep -o '[a-f0-9]\{64\}')
R=$(curl -s -b $N -H "X-CSRF-Token: $CN" -X POST -d "degree_map_id=$LAS2027" "$A?action=clone_degree_map"); AN=$(echo "$R" | grep -o '"degree_map_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$AN" ] && ok "advisor admin clones another college's map → $AN" || bad "aa clone: $R"
R=$(curl -s -b $N -H "X-CSRF-Token: $CN" -X POST -d "degree_map_id=$AN&year=1&semester=1" "$A?action=edit_course"); echo "$R" | grep -q 'editCourseModal' && ok "advisor admin edits the clone" || bad "aa edit: $R"
R=$(curl -s -b $N -H "X-CSRF-Token: $CN" -X POST -d "degree_map_id=$AN" "$A?action=delete_degree_map"); echo "$R" | grep -q 'permission' && ok "advisor admin cannot delete" || bad "aa delete: $R"
R=$(PK -d "degree_map_id=$AN" "$A?action=delete_degree_map"); echo "$R" | grep -q '"success":true' && ok "cleanup clone" || bad "cleanup: $R"
R=$(PK -d "user_id=$(Q "SELECT id FROM majors_users WHERE email='aaron.admin@wichita.edu'")" "$A?action=delete_user"); echo "$R" | grep -q '"success":true' && ok "cleanup advisor admin user" || bad "cleanup user: $R"

echo "[marketing]"
L=$S/mia.jar; rm -f $L; curl -s -o /dev/null -c $L -b $L "$B/auth/login.php?as=mia.marketing@wichita.edu"
chk "$(GET -b $L "$B/_admin/index.php")" 200 "marketing sees majors admin"
chk "$(GET -b $L "$B/degree_maps/admin/maps.php")" 403 "marketing blocked from degree maps admin"
chk "$(curl -s -o /dev/null -b $L -w "%{http_code} %{redirect_url}" "$B/auth/logout.php")" "302 $B/degree_maps/maps.php" "logout"
chk "$(curl -s -o /dev/null -b $L -w "%{http_code}" "$B/_admin/index.php")" 302 "session cleared"

kill $SRV
echo; echo "PASS=$PASS FAIL=$FAIL"; echo "--- server errors:"; grep -i "majors\]\|PHP " $S/e2e-server.log | head -20
