<?php /** @var \Majors\View\Layout $t */ ?>
<?= $t->partial('partials/page_header', ['title' => ($code ?? 500) === 404 ? 'Not found' : 'Error', 'noprint' => false]) ?>
<section class="<?= $t->cls('section') ?>">
	<p class="<?= $t->cls('heading4') ?>"><?= $t->e($message ?? 'Something went wrong.') ?></p>
	<p><a href="<?= $t->e($t->url('degree_maps/maps.php')) ?>">All degree maps</a> · <a href="<?= $t->e($t->url('index.php')) ?>">All degree programs</a></p>
</section>
