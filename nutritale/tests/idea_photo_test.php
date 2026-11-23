<?php
require_once __DIR__ . '/../includes/functions_core.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/idea_photo.php';
$f = 0; $t = function ($ok, $n) use (&$f) { echo ($ok ? 'ok   ' : 'FAIL ') . $n . "\n"; if (!$ok) $f++; };
$t(idea_photo_key("  Spicy Feta & Tomato Pasta! ") === 'spicy feta  tomato pasta', 'key normalised');
$t(idea_photo('') === null, 'empty title -> null');
if (UNSPLASH_ACCESS_KEY === '') $t(idea_photo('Tomato soup') === null, 'no key -> null');
$t(str_contains(idea_photo_html('Nothing'), 'idea-photo-plain') || str_contains(idea_photo_html('Nothing'), 'unsplash'), 'html renders either way');
exit($f ? 1 : 0);
