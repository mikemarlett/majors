<?php
/**
 * Compact "listed on" pills for the control panel (also returned after a listings save).
 * Variables: $lists (list keys), $lines (number of listing lines)
 * @var \Majors\View\Layout $t
 */
use Majors\Majors\Listings;

if ($lists === []): ?><span class="ma-flag ma-flag--unlisted">Not listed</span><?php else: ?><span class="ma-flag-list"><?php foreach ($lists as $k): ?><span class="ma-flag ma-flag--list-<?= $t->e($k) ?>" title="<?= $t->e(Listings::LISTS[$k]['label'] ?? $k) ?>"><?= $t->e(Listings::SHORT[$k] ?? $k) ?></span><?php endforeach; ?><?php if ($lines > 1): ?><span class="ma-flag ma-flag--lines" title="<?= (int) $lines ?> listing lines"><?= (int) $lines ?> lines</span><?php endif; ?></span><?php endif;
