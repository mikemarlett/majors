<?php

/** Html::clean / safeUrl: what the editors may store. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Majors\Support\Html;

echo "[clean]\n";
check(Html::clean('<p>Hello <strong>world</strong> &amp; friends — “quotes”</p>') === '<p>Hello <strong>world</strong> &amp; friends — “quotes”</p>', 'plain paragraph survives untouched (entities and UTF-8 kept)');
check(Html::clean('<p>x<script>alert(1)</script>y</p>') === '<p>xy</p>', 'script removed with its content');
check(Html::clean('<p onclick="evil()" style="color:red" class="c">t</p>') === '<p>t</p>', 'event handlers, style and class dropped');
check(Html::clean('<p><a href="javascript:alert(1)">click</a> <a href="/academics/x/">ok</a></p>') === '<p>click <a href="/academics/x/">ok</a></p>', 'javascript: link unwrapped, relative link kept');
check(Html::clean('<p><a href="https://example.org/a?b=1&c=2" target="_blank" title="T">t</a></p>') === '<p><a href="https://example.org/a?b=1&amp;c=2" target="_blank" title="T" rel="noopener">t</a></p>', 'external link keeps href/title/target and gets rel=noopener');
check(Html::clean('<p><a href="/x" target="_self">t</a></p>') === '<p><a href="/x">t</a></p>', 'target other than _blank dropped');
check(Html::clean('<div class="wrap"><span style="font-size:12pt" data-ccp-props="{}">Word</span> paste<br>next</div>') === 'Word paste<br>next', 'div/span unwrapped, Word debris gone, br kept');
check(Html::clean('<ul><li>one</li><li>two <em>x</em></li></ul><ol><li>1</li></ol>') === '<ul><li>one</li><li>two <em>x</em></li></ol>' || Html::clean('<ul><li>one</li><li>two <em>x</em></li></ul><ol><li>1</li></ol>') === '<ul><li>one</li><li>two <em>x</em></li></ul><ol><li>1</li></ol>', 'lists kept');
check(Html::clean('<h3>Sub</h3><h1>big</h1><p>p</p>') === '<h3>Sub</h3>big<p>p</p>', 'h3 kept, h1 unwrapped');
check(Html::clean('<p><iframe src="https://evil"></iframe>after</p><object data="x"></object>') === '<p>after</p>', 'iframe/object removed');
check(Html::clean('   ') === '' && Html::clean('') === '', 'blank in, blank out');
check(Html::clean('<p>a</p><!-- note --><p>b</p>') === '<p>a</p><p>b</p>', 'comments dropped');
check(Html::clean('<p><a href="data:text/html;base64,AAAA">d</a></p>') === '<p>d</p>', 'data: URL unwrapped');
check(Html::clean("<p><a href=\"java\tscript:alert(1)\">d</a></p>") === '<p>d</p>', 'scheme hidden by a control character');
check(str_replace("\u{a0}", '&nbsp;', Html::clean('<p>Non-breaking&nbsp;space and <sup>2</sup></p>')) === '<p>Non-breaking&nbsp;space and <sup>2</sup></p>', 'nbsp (as the character) and sup kept');

echo "[safeUrl]\n";
check(Html::safeUrl('/academics/x/') === '/academics/x/' && Html::safeUrl('#top') === '#top' && Html::safeUrl('?a=1') === '?a=1', 'relative forms');
check(Html::safeUrl('https://www.wichita.edu/a b') === 'https://www.wichita.edu/a%20b', 'https kept, spaces encoded');
check(Html::safeUrl('mailto:x@wichita.edu') === 'mailto:x@wichita.edu' && Html::safeUrl('tel:316-978-3456') === 'tel:316-978-3456', 'mailto and tel');
check(Html::safeUrl('javascript:alert(1)') === '' && Html::safeUrl('JAVASCRIPT:x') === '' && Html::safeUrl('vbscript:x') === '' && Html::safeUrl('data:text/html,x') === '', 'dangerous schemes refused');
check(Html::safeUrl("  \x01java\nscript:alert(1)") === '', 'control characters do not hide a scheme');
check(Html::safeUrl('www.wichita.edu/x') === 'www.wichita.edu/x', 'scheme-less host kept as typed (browser treats it as relative)');

finish();
