# Majors in-place editor — design (branch `inplace-editor`)

Status: design for review, 2026-09-25. Replaces the form-based editor on
`_admin/program.php` with a page that *is* the finished program page, plus a
control-panel index. Both site designs (old = www/www-test, new = www-dev).
Desktop first; mobile only has to not break.

## What marketing gets

1. **Edit the page by clicking on it.** `_admin/program.php?program=<basename>`
   renders the public program page (same templates, same chrome) with an
   editing layer. Click a headline, a paragraph, a button, the photo, the
   facts box: it becomes editable right there. Empty optional things show a
   dashed placeholder ("Add a photo", "Add program details…") that only
   editors see. Sections carry a small tool strip (move, remove, add after,
   customize). Similar programs are removed with an × on the card and added
   from an "+ Add similar program" tile.
2. **Control panel index.** `_admin/index.php` is a sortable, filterable
   table: search, Level (undergraduate/graduate), Credential, College,
   Status, "Needs attention". A few things are edited from the table without
   opening the page: status (active/retired) and similar programs (popover).
3. **No ids in public URLs.** `index.php?program=<basename>`; `?id=N` keeps
   working as a 301 to the basename form. New programs get a computed, unique
   basename (`<name>_<type>`, e.g. `data_science_ms`), shown and editable
   before creation.

## URLs and identity

| URL | Behaviour |
|---|---|
| `index.php?program=<basename>` | public page (canonical) |
| `index.php?id=N` | 301 → `?program=<basename>` when the row has one, else renders as before |
| `_admin/program.php?program=<basename>` (also `?id=N`) | in-place editor |
| `_admin/program.php?new=1` | small create form (name, credential, type, graduate); shows the basename it will use, editable |
| `_admin/index.php` | control panel |
| `_admin/blocks.php` | shared blocks (unchanged form editor) |

`basename` is already `UNIQUE` on `majors_academic_programs`; 6 retired rows
have none and stay reachable by id. `ProgramRepository::findByBasename()`;
every place that links a program page (listing, similar cards, admin table,
degree maps admin "program page") uses `basename` and falls back to `?id=`
only when it is empty. `ProgramEditor::basenameFor(name, type, credential)` +
`uniqueBasename()` (public) compute `<slug(name)>_<slug(type ?: credential)>`,
`_2`, `_3`… on collision. No schema change.

## Rendering with editing markers

`ProgramRenderer::parts($program, $maps, editing: bool)` passes `editing`
to the templates. Nothing changes in the public output. When editing, the
same templates add attributes and placeholders:

| marker | on | meaning |
|---|---|---|
| `data-ma-part="card"`, `"content"`, `"similar"` | the root element(s) of each part | what the client swaps after a save. Content is several sibling roots (teaser grid / feature bands), each marked. When there are no sections, the "add a section" bar carries the marker so the part still exists. |
| `data-ma-scope="program"` / `data-section="ID"` (exists) / `data-ma-scope="block" data-block="ID"` | card root / section root | which save action a field inside belongs to |
| `data-ma-text="<field>"` | element whose text *is* the value: program name, learn_how line, section headline, feature band label | single-line plain text, edited with `contenteditable` |
| `data-ma-html="<field>"` | the prose div: description, section body | rich text, edited with CKEditor 5 **balloon** build in place |
| `data-ma-form="<form>" data-ma-json="{…}"` | the thing the form belongs to | opens an anchored popover form: `identity` (credential, type, graduate, STEM), `crumbs` (college + link, department + link), `buttons` (list), `image` (url, alt, caption, credit), `facts` (degree, modality, credit hours, entry term), `coordinator` (name, email, phone), `links` (section link list), `section-image` (url, alt) |
| `data-ma-ph` | placeholder elements rendered only when editing and the value is empty | same `data-ma-form`/`data-ma-text`/`data-ma-html` as the real thing would carry |
| `data-ma-tools` | the section tool strip (partial `majors/admin/inplace/section_tools.php`) | kind label, "shared by N pages" badge, Move up/down, Add after (card / feature / shared text), Customize, Swap for shared text, Remove |
| `data-ma-similar-remove="ID"` / `data-ma-similar-add` | on similar cards / the add tile | |

Feature sections (Inside the Program) show the photo placeholder when
there is none; cards never do (theme cards have no photo; decided 2026-09-25).

Shared sections render the block's text with the badge. Clicking a text in a
shared section asks: *"This text is shared by N pages. Edit for all N pages /
Customize for this page only / Cancel."* Edit-for-all edits in place against
the block (`save_block_fields`); Customize detaches first, then edits.

### Placement of tools

Section tools are absolutely positioned in the top-right corner of the
section root (`position: relative` added to the root via `.ma-editing`).
The "add after" buttons live in the tool strip, so the teaser grid needs no
extra `<li>`s. One "Add a section" bar renders after the last content root
(and alone when the page has none).

## Saving model

Every editable thing saves itself; there is no page-level Save button.

- **Text**: click → `contenteditable="plaintext-only"` (fallback: `true` +
  paste-as-text). Enter or blur saves when changed; Esc restores. The old
  value is kept client-side; the "Saved" toast has **Undo** for 15 s, which
  re-posts the old value.
- **Rich text**: click → `BalloonEditor.create(el)` with bold, italic, link,
  bulleted/numbered list, h3 heading, undo/redo. Leaving the editor (its
  `ui.focusTracker` goes false, so the balloon toolbar and link form count as
  inside) saves when `getData()` changed; Esc restores. Undo as above.
- **Popover forms**: Save/Cancel buttons; Save posts only that form's fields.
- **Structural** actions (add/move/remove section, detach, swap block,
  similar add/remove) post immediately; Remove asks for confirmation.

Every mutating response returns `parts` (`card`, `content`, `similar`
HTML with markers) and `title`; the client swaps the parts and updates the
page title / h1. Rendering happens on the server, so the page is always the
real page (image_base, theme classes, grouping of cards into grids). The
theme's scroll-fade script only touches nodes present at load, so swapped
nodes are simply visible.

## Ajax actions (`_admin/ajax.php?action=…`, role marketing; POST + CSRF unless noted)

| action | in | out |
|---|---|---|
| `render_program` (GET) | `program_id` | `{parts, title}` |
| `save_program` (exists; partial by design) | `program_id` + any subset of program fields; `buttons[text][]/[href][]` | `{parts, title}` |
| `save_section_fields` *(new)* | `program_id`, `section_id`, subset of `headline`, `body`, `label`, `links[…]`, `image_url`, `image_alt` | `{parts}`; 409 when the section is shared |
| `save_block_fields` *(new)* | `block_id`, subset of `headline`, `body`, `links[…]` | `{parts (for program_id), uses}` |
| `add_section` *(new)* | `program_id`, `kind`, `after` (section id, 0 = end), `block_id?` | creates with a visible default ("New card" / "Inside the Program"), inserts at the position, `{parts, section_id}` |
| `move_section` *(new)* | `program_id`, `section_id`, `dir` up\|down | `{parts}` |
| `delete_section`, `detach_section`, `save_section_order` (exist) | | now also return `{parts}` |
| `swap_section_block` *(new)* | `program_id`, `section_id`, `block_id` | section now points at the block; `{parts}` |
| `save_similar` (exists) | `program_id`, `similar[]` | `{parts}` |
| `program_search` (exists, GET) | `q` | for the similar picker |
| `list_images` (GET) *(new)* | `q?` | files in `<webRoot>/_images` as `{name, url}`; the client prefixes `image_base` for thumbnails |
| `basename_preview` (GET) *(new)* | `academic_program`, `program_type`, `credential` | `{basename}` (unique) |
| `get_settings_form` (GET) *(new)* | `program_id` | HTML dialog: sort_title, basename, note, status, flags (online, online only, minor, certificate, badge), meta description/keywords, catalog link; saves through `save_program` |
| `new_program` (exists) | + optional `basename` | |

`ProgramEditor` gains `updateSection()`, `updateBlock()`,
`insertSectionAfter()`, `moveSection()`, `swapSectionBlock()`,
`basenameFor()`; `create()` accepts a basename. `syncFlat()` runs as today.

## Client

- `assets/admin/ma-ui.js` — shared: `ajax()` (fetch + CSRF header, JSON
  errors → toast), anchored `popover()` (absolute in `body`, flips when it
  would overflow, closes on Esc/outside click, returns focus), `toast()`
  (with optional Undo), `confirm()`.
- `assets/admin/inplace.js` — the editor: event delegation on `document`
  so swapped HTML needs no re-binding; text/html/form/tools/similar behaviours
  above; a sticky **edit bar** under the admin bar: program title, *View
  public page*, *Page settings*, *Shared blocks*, *All programs*, and the
  save status pill.
- `assets/admin/inplace.css` — dashed outlines on hover/focus, pencil
  affordance, placeholders, tool strip, popover, toast, edit bar. Design
  neutral; a few `.majors-new` / `.majors-old` overrides where the chrome
  needs it.
- `assets/admin/majors-admin.js` — the control panel: sort (string / number
  / date, `aria-sort`), filters, count, status select (`save_program`),
  similar popover (`program_search` + `save_similar`).
- Libraries: CKEditor 5 balloon build pinned to the same version as the
  classic build already used (`@ckeditor/ckeditor5-build-balloon@41.4.2`,
  jsdelivr); no jQuery/jQuery UI needed by the new pages.

## Tests

- `tests/render_program.php`: editing mode emits the markers and
  placeholders on both designs; public mode emits none of them.
- `tests/basename.php`: `basenameFor()` cases (accents, punctuation, empty
  type falls back to credential, length clip).
- `tests/e2e.sh`: `?program=` 200; `?id=` 301 → basename; listing and
  similar links carry `?program=`; editor page has `data-ma-part` ×3 and the
  edit bar; `render_program`; `save_section_fields` (own text) and 409 on a
  shared section; `add_section` after / `move_section` / `swap_section_block`;
  `list_images`; `basename_preview` uniqueness; control panel toolbar.

## Out of scope for this pass

Photo upload (where images live is still a marketing decision; the picker
browses `_images/` and accepts any URL). A public "Edit this page" chip.
Photos on cards. Mobile editing.
