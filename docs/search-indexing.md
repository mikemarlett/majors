# Indexing the Majors pages and Degree Maps from the database

Hand-off for the site search (`/srv/work/Search-Engine`, `connector_dbpages.php`)
and for the site's `ai-meta.php` include (`/srv/work/Modern Campus/ai-search`).
Written 2026-10-08, the day the Majors pages went database-driven on www.

Everything here is pinned by `app/share/urls.php`, which both pieces of code
can `require` from `/data/www/config/majors/share/urls.php` on www (and on the
test box). Use it instead of copying the rules: it is tested against the app's
own URL code (`app/tests/share_urls.php`), so when the app changes, that file
changes with it.

## What changed on 2026-10-08

- Program pages are `https://www.wichita.edu/academics/majors/index.php?program=<basename>`.
  The CMS pages at `/academics/majors/<basename>.php` are recycled; their
  addresses answer 301 to the new ones (Apache rewrite, live from the evening
  of 2026-10-08). A crawler will follow the redirect; a database connector
  should use the new address directly.
- The listing pages keep their addresses (`index.php`, `majors.php`,
  `graduate.php`, `online.php`, `certificates.php`, the `*_by_college.php`
  pages), now served by the app from the database.
- Degree maps are public only once approved (`degree_maps.approved = 1`);
  everything else is hidden from the public viewer, the search endpoint and
  the program pages. The connector's old rule `approved = 1 OR approved IS NULL`
  must become `approved = 1`.
- The connector's degree-map address `maps.php?id=` is not one the viewer
  understands (it reads `degree_map_id`, and the legacy `map_id`); use
  `$urls['degree_map']($id)`.

## Programs (provider `db-majors`)

Database `formshandlerdb` on www, the site account can read it.

```sql
SELECT p.* FROM majors_academic_programs p WHERE p.status = 'active'
```

- **URL**: `$urls['program']($row)` — `index.php?program=<basename>`; a row
  with an empty basename (none today) gets `?id=`.
- **Visibility**: `$urls['program_visible']($row)`, i.e. `status = 'active'`.
  Retired programs stay in the table; one with `forward_to` set redirects its
  old address to another program, so neither kind is indexed.
- **Title**: `academic_program` (what the page's title shows), with
  `credential` or `program_type` as the degree. `sort_title` is an alternate
  name worth adding to the searchable text.
- **Description**: `meta_description`, else the plain text of `description`
  (HTML).
- **Body**, in the order the page shows it:
  - `description` (HTML), `degree_title`, `credit_hours`, `modality`,
    `entry_terms`, `coordinator_name`
  - `college`, `department`, plus `more_departments` (JSON: a list of
    `{"text": name, "href": url}` objects for further departments)
  - the page's sections: `majors_program_sections` where `program_id = p.id`,
    ordered by `position`; each has `headline` and `body` (HTML) unless it uses
    a shared block (`block_id` set), in which case the text is
    `majors_content_blocks.headline` / `.body` for that id. `kind` is
    `teaser`, `feature` or `band`; all three are visible text.
  - the names the program is listed under: `majors_listing_entries.name` and
    `.detail` where `program_id = p.id` (NULL name = the program's own name).
- **Linked degree maps** (optional, as extra terms): `degree_maps` where
  `program_id = p.id AND approved = 1`, `major` and `degree_type`.
- **Freshness**: `p.timestamp` changes on every save in the editor;
  `majors_program_sections.updated_at` and `majors_content_blocks.updated_at`
  for their parts. A nightly full rebuild of ~500 rows is simpler than tracking
  them.
- **Photo**, if the index shows one: `image_url` (site-relative,
  `/academics/majors/_images/...`), `image_alt`.

The legacy flat table `majors_programs_content` is still kept in step by the
editor for the site's older scripts, but it only holds the old fixed fields;
index from the tables above.

## Degree maps (provider `db-degreemaps`)

```sql
SELECT * FROM degree_maps WHERE approved = 1
```

- **URL**: `$urls['degree_map']($id)`. For a result that should always land on
  the newest approved year of a degree, `$urls['degree_map_latest']($id)`.
- **Visibility**: `$urls['degree_map_visible']($row)`.
- **Which years**: every approved year is public, but indexing all of them
  gives four near-identical results per degree. Index the public default year
  only: `$urls['degree_map_default_year']($yearsWithApprovedMaps)` (the current
  catalog year when it has approved maps, which rolls over on August 1, else
  the newest earlier year), or index all years and boost that one.
- **Text**: `major`, `degree_type`, `college`, `department`, `note`,
  `hours_to_graduate`, and the courses in `degree_maps_courses`
  (`scbcrse_subj_code`, `scbcrse_crse_numb`, `course_info`), as the connector
  already does. Approval is recorded in `approved_by` / `approved_at`.

## ai-meta.php (the program schema.org block)

The include only emits `EducationalOccupationalProgram` data when the request
path matches `/academics/majors/<slug>.php`, so since the switch it emits
nothing for program pages. It should also match `index.php` with a `program`
query parameter (`$_GET['program']`), look the row up by
`majors_academic_programs.basename` (status active), and use
`$urls['program']($row)` as the page URL. The query it runs today keys on
`majors_programs_content.basename`; the programs table is the source now.

## Checking

- The app's own search endpoint shows what is public:
  `https://www.wichita.edu/academics/majors/search.php?search=<term>` (JSON,
  active programs only) and
  `https://www.wichita.edu/academics/majors/degree_maps/search.php`
  (POST `searchList`, approved maps only). A connector row that these do not
  return should not be in the index either.
- `php app/tests/run.php` in this repo runs `tests/share_urls.php`.
