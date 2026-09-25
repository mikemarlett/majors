<?php
/**
 * One program's marketing page — current design (program-card / teaser
 * components), rendered from the program's ordered sections.
 *
 * When $editing, $ed (EditMarks) adds the in-place editor's markers and
 * placeholders (data-ma-part on the card, every content root and the similar
 * band; data-ma-text / -html / -form on the things that can change; the tool
 * strip on each section; the "Add a section" bar). Public output is unchanged.
 *
 * Variables: $p (program row), $title, $kind, $crumbs, $description (HTML), $learn_how, $buttons (text/href), $facts, $is_stem, $coordinator,
 *            $image (url/alt/caption/credit|null), $sections, $degree_maps (id/label/url), $similar, $editing, $ed
 * @var \Majors\View\Layout $t
 */
$part = $editing ? ' data-ma-part="content"' : '';
$links = static function (array $ls) use ($t): string {
    if ($ls === []) {
        return '';
    }
    $out = '<div class="teaser__links">';
    foreach ($ls as $l) {
        $out .= '<a class="link--rich" href="' . $t->e($l['href']) . '"><span>' . $t->e($l['text']) . '</span></a>';
    }
    return $out . '</div>';
};
$mapsHtml = '';
if ($degree_maps !== []) {
    $mapsHtml = '<div class="teaser__headline"' . ($editing ? ' data-ma-static title="Degree maps are linked from the degree-maps admin."' : '') . '><h3 class="headline-group"><span class="head">' . (count($degree_maps) > 1 ? 'Degree Maps' : 'Degree Map') . '</span></h3></div>'
        . $links(array_map(static fn (array $m) => ['text' => $m['label'], 'href' => $m['url']], $degree_maps));
}
$teaser = static function (array $s, string $extra = '') use ($t, $links, $ed, $editing): string {
    $linksForm = $ed->form('links', ['links' => $s['links']]);
    $linksHtml = $s['links'] ? '<div' . $linksForm . '>' . $links($s['links']) . '</div>' : ($editing ? $ed->placeholder('+ Add a link', $linksForm) : '');
    return '<div class="teaser collection__item majors-section" data-section="' . (int) $s['id'] . '"' . ($s['shared'] ? ' data-shared="1"' : '') . $ed->scope($s) . '>'
        . $t->partial('majors/admin/inplace/section_tools', ['s' => $s, 'ed' => $ed])
        . '<div class="teaser__body">'
        . '<div class="teaser__headline"><h3 class="headline-group"><span class="head"' . $ed->text('headline', $s['headline']) . '>' . $ed->show($s['headline'], 'Click to write the headline') . '</span></h3></div>'
        . '<div class="teaser__editorial"' . $ed->html('body', $s['body']) . '>' . $ed->showHtml($s['body'], 'Click to write the text.') . '</div>' . $linksHtml . $extra . '</div></div>';
};
$groups = [];
$mapsPlaced = false;
foreach ($sections as $s) {
    if ($s['kind'] === 'teaser') {
        $extra = '';
        if (!$mapsPlaced && $s['headline'] === 'Curriculum' && $mapsHtml !== '') {
            $extra = $mapsHtml;
            $mapsPlaced = true;
        }
        if ($groups === [] || end($groups)['type'] !== 'cards') {
            $groups[] = ['type' => 'cards', 'html' => []];
        }
        $groups[count($groups) - 1]['html'][] = $teaser($s, $extra);
    } elseif ($s['kind'] === 'feature') {
        $groups[] = ['type' => 'feature', 's' => $s];
    }
}
if (!$mapsPlaced && $mapsHtml !== '') {
    $groups[] = ['type' => 'cards', 'html' => ['<div class="teaser collection__item"><div class="teaser__body">' . $mapsHtml . '</div></div>']];
}
$identity = $ed->form('identity', ['credential' => (string) ($p['credential'] ?? ''), 'program_type' => (string) ($p['program_type'] ?? ''), 'graduate' => (int) !empty($p['graduate']), 'is_stem' => (int) $is_stem]);
$crumbsForm = $ed->form('crumbs', ['college' => (string) ($p['college'] ?? ''), 'college_url' => (string) ($p['college_url'] ?? ''), 'department' => (string) ($p['department'] ?? ''), 'department_url' => (string) ($p['department_url'] ?? '')]);
$imageForm = $ed->form('image', ['image_url' => (string) ($p['image_url'] ?? ''), 'image_alt' => (string) ($p['image_alt'] ?? ''), 'image_caption' => (string) ($p['image_caption'] ?? ''), 'image_credit' => (string) ($p['image_credit'] ?? '')]);
$factsForm = $ed->form('facts', ['degree_title' => (string) ($p['degree_title'] ?? ''), 'modality' => (string) ($p['modality'] ?? ''), 'credit_hours' => (string) ($p['credit_hours'] ?? ''), 'entry_terms' => (string) ($p['entry_terms'] ?? '')]);
$coordForm = $ed->form('coordinator', ['coordinator_name' => (string) ($p['coordinator_name'] ?? ''), 'coordinator_email' => (string) ($p['coordinator_email'] ?? ''), 'coordinator_phone' => (string) ($p['coordinator_phone'] ?? '')]);
$buttonsForm = $ed->form('buttons', ['buttons' => $buttons]);
?>

<section class="section-wrap section-wrap--shade-light"<?= $editing ? ' data-ma-part="card" data-ma-scope="program"' : '' ?>>
	<div class="program-card">
		<div class="program-card__body">
			<h2 class="headline-group">
				<span class="superhead"<?= $identity ?>><?= $ed->show($kind, 'Set the program type') ?><?php if ($is_stem): ?> · STEM Program<?php endif; ?></span>
				<span class="head"<?= $ed->text('academic_program') ?>><?= $t->e($p['academic_program']) ?></span>
			</h2>
<?php if ($crumbs): ?>
			<div class="link-collection"<?= $crumbsForm ?>><ul>
<?php foreach ($crumbs as $l): ?>
				<li><a href="<?= $t->e($l['href']) ?>"><?= $t->e($l['text']) ?></a></li>
<?php endforeach; ?>
			</ul></div>
<?php else: ?>
<?= $ed->placeholder('Add the college and department links', $crumbsForm) ?>
<?php endif; ?>
			<div class="program-card__detail"<?= $ed->html('description', $description) ?>><?= $ed->showHtml($description, 'Click to write the description.') ?></div>
<?php if ($facts): ?>
			<dl class="majors-facts"<?= $factsForm ?>>
<?php foreach ($facts as $f): ?>
				<div><dt><?= $t->e($f['label']) ?></dt><dd><?= $t->e($f['value']) ?></dd></div>
<?php endforeach; ?>
			</dl>
<?php else: ?>
<?= $ed->placeholder('Add program details (degree, modality, credit hours, entry term)', $factsForm) ?>
<?php endif; ?>
<?php if ($coordinator): ?>
			<p class="majors-coordinator"<?= $coordForm ?>>Questions? Contact <?= $coordinator['name'] !== '' ? 'Program Coordinator ' . $t->e($coordinator['name']) : 'the program' ?><?php if ($coordinator['email'] !== ''): ?> at <a href="mailto:<?= $t->e($coordinator['email']) ?>"><?= $t->e($coordinator['email']) ?></a><?php endif; ?><?php if ($coordinator['phone'] !== ''): ?> or call <?= $t->e($coordinator['phone']) ?><?php endif; ?>.</p>
<?php else: ?>
<?= $ed->placeholder('Add a program coordinator (name, email, phone)', $coordForm) ?>
<?php endif; ?>
<?php if ($learn_how !== '' || $editing): ?>
			<div class="program-card__cta">
				<h3 class="heading4"<?= $ed->text('learn_how', $learn_how) ?>><?= $ed->show($learn_how, 'Add the "Learn how…" line') ?></h3>
<?php if ($buttons): ?>
				<div class="button-collection landing-panel__buttons button-collection--accent-first"<?= $buttonsForm ?>>
<?php foreach ($buttons as $b): ?>
					<a href="<?= $t->e($b['href'] ?? '#') ?>" role="button" class="button"><?= $t->e($b['text'] ?? '') ?> <?= $t->icon('design--arrow-right', 'icon button__trailing-icon') ?></a>
<?php endforeach; ?>
				</div>
<?php else: ?>
<?= $ed->placeholder('+ Add a button (Request info, Apply, Visit…)', $buttonsForm) ?>
<?php endif; ?>
			</div>
<?php endif; ?>
		</div>
<?php if ($image): ?>
		<div class="program-card__image"<?= $imageForm ?>>
			<div class="captioned-media captioned-media--right"><figure>
				<div class="figure-wrapper">
					<img src="<?= $t->e($t->img($image['url'])) ?>" alt="<?= $t->e($image['alt']) ?>">
<?php if ($image['credit'] !== ''): ?>
					<cite class="cite--photo-credit"><?= $t->icon('design--camera') ?> <?= $t->e($image['credit']) ?></cite>
<?php endif; ?>
				</div>
<?php if ($image['caption'] !== ''): ?>
				<figcaption><p><?= $t->e($image['caption']) ?></p></figcaption>
<?php endif; ?>
			</figure></div>
		</div>
<?php elseif ($editing): ?>
		<div class="program-card__image">
<?= $ed->placeholder('Add a photo', $imageForm, 'ma-ph--photo') ?>
		</div>
<?php endif; ?>
	</div>
</section>

<?php foreach ($groups as $i => $g): ?>
<?php if ($g['type'] === 'cards'): ?>
<section class="teaser-collection section-wrap collection--two-columns<?= $i === count($groups) - 1 ? ' section-wrap--nipple-down' : '' ?>"<?= $part ?>><div class="collection__items">
	<?= implode("\n\t", $g['html']) ?>
</div></section>
<?php else: ?>
<?php $s = $g['s']; $imgForm = $ed->form('section-image', ['image_url' => (string) ($s['image']['url'] ?? ''), 'image_alt' => (string) ($s['image']['alt'] ?? '')]); $linksForm = $ed->form('links', ['links' => $s['links']]); ?>
<section class="section-wrap section-wrap--wheat majors-section" data-section="<?= (int) $s['id'] ?>"<?= $ed->scope($s) . $part ?>>
<?= $t->partial('majors/admin/inplace/section_tools', ['s' => $s, 'ed' => $ed]) ?>
	<header class="section-header section-header--no-border"><h2<?= $ed->text('label', $s['label']) ?>><?= $t->e($s['label'] !== '' ? $s['label'] : 'Inside the Program') ?></h2></header>
	<div class="teaser teaser--columned-intro">
<?php if ($s['image']): ?>
		<div class="teaser__image"<?= $imgForm ?>><img src="<?= $t->e($t->img($s['image']['url'])) ?>" alt="<?= $t->e($s['image']['alt']) ?>"></div>
<?php elseif ($editing): ?>
		<div class="teaser__image"><?= $ed->placeholder('Add a photo', $imgForm, 'ma-ph--photo') ?></div>
<?php endif; ?>
		<div class="teaser__body">
			<div class="teaser__headline"><h3 class="headline-group"><span class="head"<?= $ed->text('headline', $s['headline']) ?>><?= $ed->show($s['headline'], 'Click to write the headline') ?></span></h3></div>
			<div class="teaser__editorial"<?= $ed->html('body', $s['body']) ?>><?= $ed->showHtml($s['body'], 'Click to write the text.') ?></div>
<?php if ($s['links']): ?>
			<div<?= $linksForm ?>><?= $links($s['links']) ?></div>
<?php else: ?>
<?= $ed->placeholder('+ Add a link', $linksForm) ?>
<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>
<?php endforeach; ?>
<?= $t->partial('majors/admin/inplace/add_bar', ['ed' => $ed, 'empty' => $sections === []]) ?>

<?php if ($similar || $editing): ?>
<section class="teaser-collection section-wrap section-wrap--shade-dark section-wrap--image-background-texturize collection--two-columns collection--two-columns-early-break"<?= $editing ? ' data-ma-part="similar" data-ma-scope="similar"' : '' ?>>
	<header class="section-header section-header--centered section-header--no-border collection__header"><h2>Similar Programs</h2></header>
	<div class="collection__items">
<?php foreach ($similar as $s): ?>
<?php if ($editing): ?>
		<div class="ma-similar collection__item" data-ma-similar="<?= (int) $s['id'] ?>">
<?php endif; ?>
		<a href="<?= $t->e($t->programUrl($s)) ?>" class="teaser <?= $editing ? '' : 'collection__item ' ?>teaser--card-wide teaser--card">
<?php if (!empty($s['main_image_url'])): ?>
			<div class="teaser__image"><img src="<?= $t->e($t->img($s['main_image_url'])) ?>" alt="" width="1000" height="1000"></div>
<?php endif; ?>
			<div class="teaser__body"><div class="teaser__headline"><div class="headline-group"><span class="head"><?= $t->e($s['academic_program']) ?> (<?= $t->e($s['credential'] ?? $s['program_simple_type'] ?? $s['program_type']) ?>)</span></div></div></div>
		</a>
<?php if ($editing): ?>
		<button type="button" class="ma-similar__remove" data-ma-similar-remove="<?= (int) $s['id'] ?>" title="Remove from similar programs" aria-label="Remove <?= $t->e($s['academic_program']) ?> from similar programs">×</button>
		</div>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($editing): ?>
		<div class="ma-similar ma-similar--add collection__item"><button type="button" class="ma-similar__add" data-ma-similar-add>+ Add a similar program</button></div>
<?php endif; ?>
	</div>
	<div class="section-wrap__image"><img src="<?= $t->e($t->img($image['url'] ?? '/_resources/images/wichita.jpg')) ?>" alt="" width="1000" height="1000"></div>
</section>
<?php endif; ?>
