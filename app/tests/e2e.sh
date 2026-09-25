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
chk "$(GET "$B/index.php?order=college&filter=online")" 200 "filtered"; has $S/out.html 'Online Degrees' 'filter headline'
chk "$(GET "$B/index.php?program=$PBN")" 200 "program page"; grep -qE 'program-card|majors-program-intro' $S/out.html && ok "program page body (either design)" || bad "program page body"
chk "$(GET "$B/search.php?filter=graduate")" 200 "majors search json"; has $S/out.html '"title":"Graduate Degrees"' 'json title'

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
GET -b $M "$B/_admin/program.php?id=$PROG" >/dev/null; MC=$(grep -o 'name="csrf-token" content="[^"]*"' $S/out.html | head -1 | sed 's/.*content="//; s/"$//')
MP() { curl -s -b $M -H "X-CSRF-Token: $MC" "$@"; }
MA=$B/_admin/ajax.php
NAME=$(Q "SELECT academic_program FROM majors_academic_programs WHERE id=$PROG")
DESC0=$(Q "SELECT description FROM majors_academic_programs WHERE id=$PROG")
FLAGS0=$(Q "SELECT CONCAT_WS(',',COALESCE(graduate,'n'),COALESCE(online_learning,'n'),COALESCE(certificate,'n'),is_stem) FROM majors_academic_programs WHERE id=$PROG")
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "credit_hours=99" --data-urlencode "modality=Online" --data-urlencode "description=<p>E2E description</p>" "$MA?action=save_program"); echo "$R" | grep -q '"success":true' && ok "save_program" || bad "save_program: $R"; echo "$R" | grep -q '"parts":{' && ok "save_program returns the re-rendered parts" || bad "parts missing"
chk "$(Q "SELECT credit_hours FROM majors_academic_programs WHERE id=$PROG")" 99 "program facts saved"
chk "$(Q "SELECT CONCAT_WS(',',COALESCE(graduate,'n'),COALESCE(online_learning,'n'),COALESCE(certificate,'n'),is_stem) FROM majors_academic_programs WHERE id=$PROG")" "$FLAGS0" "partial save leaves the flags alone"
chk "$(Q "SELECT description FROM majors_programs_content WHERE academic_program_id=$PROG")" "<p>E2E description</p>" "flat row kept in step (ai-meta.php)"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "modality=Sideways" "$MA?action=save_program"); echo "$R" | grep -q 'Modality must be' && ok "save_program validates modality" || bad "modality validation: $R"
R=$(MP --data-urlencode "program_id=$PROG" --data-urlencode "academic_program=" "$MA?action=save_program"); echo "$R" | grep -q 'name is required' && ok "save_program refuses an empty name" || bad "empty name: $R"
MP -d "program_id=$PROG&credit_hours=&modality=" --data-urlencode "description=$DESC0" "$MA?action=save_program" >/dev/null
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
R=$(MP -d "program_id=$PROG&kind=feature&after=$SEC" "$MA?action=add_section"); SEC2=$(echo "$R" | grep -o '"section_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$SEC2" ] && ok "add_section after → $SEC2" || bad "add_section: $R"
chk "$(Q "SELECT (SELECT position FROM majors_program_sections WHERE id=$SEC2) = (SELECT position FROM majors_program_sections WHERE id=$SEC) + 1")" 1 "new section sits right after"
chk "$(Q "SELECT CONCAT(kind,'|',label) FROM majors_program_sections WHERE id=$SEC2")" "feature|Inside the Program" "new feature has the band label"
R=$(MP -d "program_id=$PROG&section_id=$SEC2&dir=up" "$MA?action=move_section"); echo "$R" | grep -q '"success":true' && ok "move_section" || bad "move: $R"
chk "$(Q "SELECT (SELECT position FROM majors_program_sections WHERE id=$SEC2) < (SELECT position FROM majors_program_sections WHERE id=$SEC)")" 1 "section moved up"
OTHER_SEC=$(Q "SELECT id FROM majors_program_sections WHERE program_id<>$PROG LIMIT 1")
chk "$(MP -o /dev/null -w "%{http_code}" --data-urlencode "program_id=$PROG" --data-urlencode "section_id=$OTHER_SEC" --data-urlencode "headline=x" "$MA?action=save_section_fields")" 422 "another program's section is refused"
R=$(MP -d "program_id=$PROG&section_id=$SEC2" "$MA?action=delete_section"); echo "$R" | grep -q '"success":true' && ok "delete_section (feature)" || bad "delete: $R"
R=$(MP -d "program_id=$PROG&section_id=$SEC" "$MA?action=delete_section"); echo "$R" | grep -q '"success":true' && ok "delete_section (card)" || bad "delete: $R"
R=$(curl -s -b $M "$MA?action=program_search&q=engineering"); echo "$R" | grep -q '"results":\[{' && ok "program_search" || bad "search: $R"
OTHER=$(Q "SELECT id FROM majors_academic_programs WHERE status='active' AND id<>$PROG ORDER BY id LIMIT 1")
SIM0=$(Q "SELECT GROUP_CONCAT(similar_academic_program_id ORDER BY id) FROM majors_similar_programs WHERE main_academic_program_id=$PROG")
R=$(MP -d "program_id=$PROG&similar[]=$OTHER" "$MA?action=save_similar"); echo "$R" | grep -q '"success":true' && ok "save_similar" || bad "similar: $R"; echo "$R" | grep -q "\"similar\":\[{\"id\":$OTHER" && ok "save_similar returns the list" || bad "similar list"
SIMARGS=""; for id in ${SIM0//,/ }; do SIMARGS="$SIMARGS -d similar[]=$id"; done; [ -n "$SIM0" ] && MP -d "program_id=$PROG" $SIMARGS "$MA?action=save_similar" >/dev/null || MP -d "program_id=$PROG&similar[]=" "$MA?action=save_similar" >/dev/null
chk "$(Q "SELECT COALESCE(GROUP_CONCAT(similar_academic_program_id ORDER BY id),'') FROM majors_similar_programs WHERE main_academic_program_id=$PROG")" "$SIM0" "similar programs restored"
R=$(curl -s -b $M "$MA?action=list_images"); echo "$R" | grep -q '"images":\[' && ok "list_images" || bad "list_images: $R"
R=$(curl -s -b $M "$MA?action=basename_preview&academic_program=$(printf %s "$NAME" | sed 's/ /%20/g')&program_type=E2E"); echo "$R" | grep -q '"basename":"' && ok "basename_preview" || bad "basename_preview: $R"
R=$(curl -s -b $M "$MA?action=basename_preview&basename=$PBN"); echo "$R" | grep -q "\"basename\":\"${PBN}_2\"" && ok "basename_preview avoids a taken name" || bad "basename uniqueness: $R"
chk "$(GET -b $M "$MA?action=get_settings_form&program_id=$PROG")" 200 "settings form"; has $S/out.html 'data-ma-settings-form' 'settings form markup'
R=$(MP --data-urlencode "academic_program=E2E Test Program" --data-urlencode "credential=Minor" "$MA?action=new_program"); NP=$(echo "$R" | grep -o '"program_id":[0-9]*' | grep -o '[0-9]*$'); [ -n "$NP" ] && ok "new_program → $NP" || bad "new_program: $R"
chk "$(Q "SELECT basename FROM majors_academic_programs WHERE id=$NP")" "e2e_test_program_minor" "new program gets <name>_<credential> as its page name"; echo "$R" | grep -q 'program.php?program=e2e_test_program_minor' && ok "redirect goes to the in-place editor by basename" || bad "redirect: $R"
Q "DELETE FROM majors_programs_content WHERE academic_program_id=$NP; DELETE FROM majors_academic_programs WHERE id=$NP" >/dev/null
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
