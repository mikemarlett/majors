<?php
/**
 * A–Z or by-college list of maps.
 * Variables: $groups (Listing::byAlpha/byCollege), $link_base (url prefix that takes the map id),
 *            optional $flags (map id => label; admin: duplicates, not approved)
 * @var \Majors\View\Layout $t
 */
/* $flags: map id => label ("duplicate", "not approved", "not approved, duplicate"); $flag_ids + $flag_text is the older one-label form. */
$flags = $flags ?? [];
foreach (($flag_ids ?? []) as $fid) {
    $flags[(int) $fid] = (string) ($flag_text ?? '');
}
$flagTitles = [
    'duplicate'    => 'Another map with the same name, degree type and college exists for this catalog year',
    'not approved' => 'Not on the public site until an advisor admin or a super admin approves it',
];
$flagTitle = static fn (string $label): string => implode('. ', array_filter(array_map(static fn (string $part): string => $flagTitles[trim($part)] ?? '', explode(',', $label))));
?>
<div class="<?= $t->cls('alpha_list') ?> dm-listing">
<?php if ($groups === []): ?>
	<p class="<?= $t->cls('heading3') ?>">No degree maps found.</p>
<?php endif; ?>
<?php foreach ($groups as $group): ?>
	<hr>
	<div class="<?= $t->cls('alpha_list.items') ?>">
		<header><h2 class="<?= $t->cls('heading4') ?>" id="<?= $t->e($group['key']) ?>"><?= $t->e($group['label']) ?></h2></header>
	</div>
	<ul>
<?php foreach ($group['items'] as $m): ?>
		<li><a href="<?= $t->e($link_base . (int) $m['id']) ?>"><?= $t->e($m['major']) ?></a> — <?= $t->e($m['degree_type']) ?><?php if (isset($flags[(int) $m['id']])): ?> <span class="dm-flag" title="<?= $t->e($flagTitle($flags[(int) $m['id']])) ?> (id <?= (int) $m['id'] ?>)"><?= $t->e($flags[(int) $m['id']]) ?></span><?php endif; ?></li>
<?php endforeach; ?>
	</ul>
<?php endforeach; ?>
</div>
