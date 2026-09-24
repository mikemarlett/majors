<?php
/**
 * Advisors' guide (behind sign-in). Variables: $user, $contact, $maps_url, $public_url
 * @var \Majors\View\Layout $t
 */
$mail = $contact !== '' ? '<a href="mailto:' . $t->e($contact) . '">' . $t->e($contact) . '</a>' : 'the web team';
?>
<section class="<?= $t->cls('section') ?>">
<div class="<?= $t->cls('prose') ?> majors-help">

	<p class="majors-help__lead">Degree maps are the semester-by-semester plans students print from the public site. This page walks through adding a course, then covers everything else you can do in the editor and, at the end, what the editor does <em>not</em> do.</p>

	<nav class="majors-help__toc" aria-label="On this page">
		<strong>On this page:</strong>
		<a href="#h-find">Find the map</a> ·
		<a href="#h-next-year">Start next year's map</a> ·
		<a href="#h-course">Add a course</a> ·
		<a href="#h-arrange">Move, edit and remove courses</a> ·
		<a href="#h-hours">Hours</a> ·
		<a href="#h-footnotes">Footnotes and notes</a> ·
		<a href="#h-sge">The general-education check</a> ·
		<a href="#h-details">Map details</a> ·
		<a href="#h-publish">When students see it</a> ·
		<a href="#h-limits">What the editor can't do</a>
	</nav>

	<h2 id="h-who">Who can edit what</h2>
	<ul>
		<li><strong>Advisors</strong> edit the maps of their own college or colleges. You can look at every map, but Edit and Clone only appear on yours.</li>
		<li><strong>Only next year's maps are editable.</strong> Once a catalog year has started, its maps are frozen: students have printed them and advising has been given on them. The current year, and everything older, is view-only for advisors. A change that genuinely has to be made to a published map goes through <?= $mail ?>, and is visible to students the moment it is saved.</li>
		<li>The catalog year rolls over on <strong>August 1</strong>. From then on the year that just started is frozen and the following year opens for editing.</li>
	</ul>

	<h2 id="h-find">1. Find the map</h2>
	<p>Start at <a href="<?= $t->e($maps_url) ?>">the admin listing</a>. Type part of a degree name in the search box, or pick a <strong>catalog year</strong> and a <strong>college</strong> to narrow the list. Each map has an <strong>Actions</strong> row:</p>
	<ul>
		<li><strong>Edit Map</strong> opens the editor (only on maps you may change).</li>
		<li><strong>View Map</strong> shows it read-only, exactly as it prints.</li>
		<li><strong>Clone to Next Year</strong> copies it forward (see below).</li>
		<li><strong>Public page</strong> opens the student-facing version.</li>
		<li><strong>New Map</strong> creates an empty map for a brand-new degree. Most of the time you want Clone instead.</li>
	</ul>

	<h2 id="h-next-year">2. Start next year's map</h2>
	<p>Find the current year's map and choose <strong>Clone to Next Year</strong>. The copy carries every course, footnote and hours entry, and opens in the editor so you can make the changes for the new catalog. Clone once per degree: if a map already exists for that degree and year the editor tells you and offers to open it instead.</p>
	<p>The public link to a degree map never needs to change. <code>?latest=</code> followed by any map's id always redirects to the newest catalog year for that degree, so a link you put in an email or a web page in 2024 still lands on the current map. Older years stay reachable from the "Catalog year" line above each map.</p>

	<h2 id="h-course">3. Add a course</h2>
	<ol>
		<li>In the editor, find the semester table the course belongs in and click <strong>Add course</strong> beneath it.</li>
		<li>In <strong>Course Info</strong>, start typing a course number, like <code>ENGL 101</code>, or a title. Suggestions come from the course catalog; pick one and the title and <strong>Credit Hours</strong> fill in. You can then edit either. For a placeholder such as an elective, just type the text you want printed, for example <code>Humanities elective</code>.</li>
		<li>Check <strong>Credit Hours</strong>. A single number is usual; a range such as <code>3-4</code> is fine and will show as a range in the totals.</li>
		<li>Choose an <strong>SGE Code</strong> if the course counts toward a Systemwide General Education bucket. Leave it blank for major and elective courses. The colored badge students see, and the general-education check described below, both come from this field.</li>
		<li>Attach <strong>Footnotes</strong> if the course needs one. The list shows the footnotes that already exist on this map; to add a new one, save the course, use <strong>Edit Footnotes</strong>, then come back and attach it.</li>
		<li><strong>Placement</strong> is pre-set to the semester you clicked in. Change year, semester or order here if you clicked the wrong table; otherwise leave it.</li>
		<li>Use <strong>Extra Note</strong> only for a remark that applies to this one course, such as <code>Must earn a C or better</code>. It prints in small type under the course. Anything shared by several courses belongs in a footnote.</li>
		<li>Click <strong>Save Changes</strong>. The course appears in the table and the semester and year totals update.</li>
	</ol>

	<h2 id="h-arrange">4. Move, edit and remove courses</h2>
	<ul>
		<li><strong>Reorder</strong> by dragging the handle at the left of a course, up or down within a semester or across into another semester. The new order is saved as soon as you drop it.</li>
		<li><strong>Edit</strong> a course with the pencil at its right; the same form opens.</li>
		<li><strong>Remove</strong> a course from the Edit form with <strong>Delete</strong>. You will be asked to confirm. This cannot be undone from the editor.</li>
	</ul>

	<h2 id="h-hours">5. Hours</h2>
	<p>The totals printed on the map are not calculated live: they are whatever is saved for each semester and year, so a map still reads sensibly when a semester lists <em>"Elective"</em> without hours. Open <strong>Edit Map Hours</strong> to see them.</p>
	<ul>
		<li>Every field starts at the <strong>sum of the courses listed</strong> in that semester or year. If that is right, you have nothing to do.</li>
		<li>The gray number beside a field is that course sum. It turns <strong>orange</strong> when the field disagrees with it, which is your cue to check whether a course's hours are wrong or the total was meant to differ.</li>
		<li>A semester or year with no saved value prints the course sum automatically.</li>
		<li><strong>Hours needed to complete the degree</strong> is the figure printed at the bottom of the map.</li>
	</ul>

	<h2 id="h-footnotes">6. Footnotes and notes</h2>
	<p>There are three places for explanatory text, from widest to narrowest:</p>
	<ul>
		<li>The map <strong>Note</strong> (in Edit Map Details) prints in a box under the title and applies to the whole degree.</li>
		<li><strong>Footnotes</strong> (Edit Footnotes) print as a numbered list at the bottom and are attached to courses in each course's editor; the course shows the superscript number. Drag footnotes to renumber them. A footnote given order <strong>0</strong> prints as an un-numbered "Note" ahead of the list. Removing a footnote detaches it from every course that used it.</li>
		<li>A course's <strong>Extra Note</strong> prints under that course alone.</li>
	</ul>

	<h2 id="h-sge">7. The general-education check</h2>
	<p>When the courses tagged with SGE codes add up to more or fewer hours than a bucket requires, the editor shows a red <strong>Check the general-education hours</strong> box listing each bucket that is off. Being over is usually fine and the warning is there to catch a course tagged with the wrong code. Being under is worth a second look. Students never see this box.</p>

	<h2 id="h-details">8. Map details</h2>
	<p><strong>Edit Map Details</strong> holds the degree name, degree type (BA, BS, BFA…), the map-wide note, the college, the department and the link to the degree's marketing page. The <strong>catalog year is fixed</strong> once a map exists; to change year, clone. The college name printed on an old map is the name the college had when that map was published, so a renamed college keeps its history.</p>

	<h2 id="h-publish">9. When students see it</h2>
	<p>Being frank, because this trips people up:</p>
	<ul>
		<li>Everything you save here is live <strong>on this site</strong> immediately. There is no draft state and no approval step.</li>
		<li>The main site (www.wichita.edu) is updated by copying the degree-map data across, which <?= $mail ?> does on request. When next year's maps are ready, say so, and they go live together.</li>
		<li>Because next year's maps are the only editable ones, and next year's maps are not yet what students print, "live immediately" is rarely a problem. It matters only for the changes super admins make to published years.</li>
	</ul>

	<h2 id="h-limits">10. What the editor can't do</h2>
	<ul>
		<li><strong>No undo.</strong> Deleting a course or footnote is final in the editor. A mistake can usually be put right by <?= $mail ?> from a database backup, but it is not a click, so read the confirmation before you agree to it.</li>
		<li><strong>No editing of the current or past years</strong> for advisors, as above.</li>
		<li><strong>No rich text.</strong> Course info, notes and footnotes are plain text.</li>
		<li><strong>Printing is the browser's.</strong> The public page is laid out for one US-letter portrait sheet per map; use the browser's Print with no extra headers.</li>
		<li><strong>Sessions end after eight idle hours</strong>, and the editor will tell you to sign in again. Unsaved text in an open form is lost, so save as you go.</li>
	</ul>

	<p>Questions, a locked map that must change, or a college that is missing from your account: <?= $mail ?>.</p>
</div>
</section>
