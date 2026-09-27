<?php
/**
 * A program's listing lines: how it appears on the listing pages (All Programs, Undergrad
 * Majors & Minors, Graduate Degrees, Online, Certificates). Loaded into a popover from the
 * in-place editor and from the control panel; posts to save_listings.
 * Variables: $program, $entries (rows of majors_listing_entries), $auto_detail (the degree written out)
 * @var \Majors\View\Layout $t
 */
use Majors\Majors\Listings;

$p = $program;
$showBadges = stripos((string) ($p['credential'] ?? ''), 'Badge') !== false || !empty($p['badge'])
    || array_filter($entries, static fn ($e) => in_array('badges', Listings::listsOf($e), true)) !== [];
$lists = array_filter(Listings::LISTS, static fn ($k) => $k !== 'badges' || $showBadges, ARRAY_FILTER_USE_KEY);
$row = static function (string $key, array $e) use ($t, $p, $lists, $auto_detail): string {
    $on     = Listings::listsOf($e);
    $isCert = in_array('certificates', $on, true);
    $topics = array_filter(explode('|', (string) ($e['cert_topics'] ?? '')));
    $id     = static fn (string $f): string => 'ml_' . $key . '_' . $f;
    $n      = static fn (string $f): string => 'entries[' . $key . '][' . $f . ']';
    ob_start();
?>
	<fieldset class="ma-listing-row" data-ma-listing-row>
		<legend class="ma-listing-row__legend">Listing line</legend>
		<div class="ma-grid-2">
			<div class="ma-field"><label for="<?= $id('name') ?>">Listed as</label><input type="text" id="<?= $id('name') ?>" name="<?= $n('name') ?>" value="<?= $t->e((string) ($e['name'] ?? '')) ?>" maxlength="255" placeholder="<?= $t->e($p['academic_program']) ?>"><div class="ma-help">Leave blank to use the program's name.</div></div>
			<div class="ma-field"><label for="<?= $id('detail') ?>">After the dash</label><input type="text" id="<?= $id('detail') ?>" name="<?= $n('detail') ?>" value="<?= $t->e((string) ($e['detail'] ?? '')) ?>" maxlength="500" placeholder="<?= $t->e($auto_detail) ?>"><div class="ma-help">Leave blank for "<?= $t->e($auto_detail) ?>". Notes like (Online only) go here.</div></div>
		</div>
		<div class="ma-field"><span class="ma-label">On these lists</span>
			<div class="ma-checks">
<?php foreach ($lists as $k => $def): ?>
				<label class="ma-field ma-field--check"><input type="checkbox" name="<?= $n('lists') ?>[]" value="<?= $k ?>"<?= in_array($k, $on, true) ? ' checked' : '' ?><?= $k === 'certificates' ? ' data-ma-cert-toggle' : '' ?>> <span><?= $t->e($def['label']) ?></span></label>
<?php endforeach; ?>
			</div>
		</div>
		<div class="ma-grid-2">
			<div class="ma-field"><label for="<?= $id('shown_in') ?>">Show in</label>
				<select id="<?= $id('shown_in') ?>" name="<?= $n('shown_in') ?>"><?php foreach (Listings::SHOWN_IN as $k => $label): ?><option value="<?= $k ?>"<?= ($e['shown_in'] ?? 'both') === $k ? ' selected' : '' ?>><?= $t->e($label) ?></option><?php endforeach; ?></select>
				<div class="ma-help">"A–Z only" suits a cross reference like "Public Health Practice, Advanced".</div></div>
			<div class="ma-field" data-ma-cert-only<?= $isCert ? '' : ' hidden' ?>><label for="<?= $id('cert_section') ?>">Certificates page</label>
				<select id="<?= $id('cert_section') ?>" name="<?= $n('cert_section') ?>"><option value="">Choose…</option><?php foreach (Listings::CERT_SECTIONS as $k => $label): ?><option value="<?= $k ?>"<?= ($e['cert_section'] ?? '') === $k ? ' selected' : '' ?>><?= $t->e($label) ?></option><?php endforeach; ?></select></div>
		</div>
		<div class="ma-field" data-ma-cert-only<?= $isCert ? '' : ' hidden' ?>><span class="ma-label">Topic on the Certificates page</span>
			<div class="ma-checks">
<?php foreach (Listings::CERT_TOPICS as $topic): ?>
				<label class="ma-field ma-field--check"><input type="checkbox" name="<?= $n('cert_topics') ?>[]" value="<?= $t->e($topic) ?>"<?= in_array($topic, $topics, true) ? ' checked' : '' ?>> <span><?= $t->e($topic) ?></span></label>
<?php endforeach; ?>
			</div>
		</div>
		<div class="ma-btn-row ma-btn-row--tight"><button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-listing-remove>− Remove this line</button></div>
	</fieldset>
<?php
    return (string) ob_get_clean();
};
$defaults = Listings::defaults($p);
$fresh = ['name' => null, 'detail' => null, 'lists' => implode(',', $defaults['lists']), 'shown_in' => 'both', 'cert_section' => $defaults['cert_section'], 'cert_topics' => null];
?>
<form class="ma-listings" data-ma-listings-form>
	<input type="hidden" name="program_id" value="<?= (int) $p['id'] ?>">
	<p class="ma-help ma-listings__intro">Each line is how this program shows on the listing pages. A program can have several lines, such as one per concentration. With no lines it isn't listed anywhere, but its page stays live and can still be linked.</p>
	<div class="ma-listing-rows" data-ma-listing-rows>
<?php foreach ($entries as $e): ?>
<?= $row((string) (int) $e['id'], $e) ?>
<?php endforeach; ?>
	</div>
	<p class="ma-listing-none" data-ma-listing-none<?= $entries === [] ? '' : ' hidden' ?>><strong>Not listed.</strong> The page is live, but no listing page shows it.</p>
	<div class="ma-btn-row ma-btn-row--tight"><button type="button" class="ma-btn ma-btn--ghost ma-btn--small" data-ma-listing-add>+ Add a line</button></div>
	<template data-ma-listing-template><?= $row('__KEY__', $fresh) ?></template>
	<div class="ma-error" data-ma-listing-error></div>
	<div class="ma-btn-row">
		<button type="submit" class="ma-btn ma-btn--accent">Save listings</button>
		<button type="button" class="ma-btn ma-btn--ghost" data-ma-cancel>Cancel</button>
	</div>
</form>
