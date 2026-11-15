<?php
// php tests/back_target_test.php
require_once __DIR__ . '/../includes/functions_core.php';
$pass = 0; $fail = 0;
function check(string $l, bool $ok): void { global $pass, $fail; if ($ok) { $pass++; return; } $fail++; echo "FAIL  $l\n"; }
function t(string $ref, string $host = 'nutritale.freedev.app', string $script = '/recipe.php'): array {
    $_SERVER['HTTP_REFERER'] = $ref; $_SERVER['HTTP_HOST'] = $host; $_SERVER['SCRIPT_NAME'] = $script;
    return back_target('index.php', 'Back to recipes');
}
check('from admin list', t('https://nutritale.freedev.app/admin_recipes.php?q=pap')[0] === 'admin_recipes.php?q=pap');
check('admin label', t('https://nutritale.freedev.app/admin_recipes.php')[1] === 'Back to admin recipes');
check('from favourites', t('https://nutritale.freedev.app/favorites.php')[0] === 'favorites.php');
check('no referer -> default', t('')[0] === 'index.php');
check('other site -> default', t('https://evil.example/admin_recipes.php')[0] === 'index.php');
check('same page -> default (no loop)', t('https://nutritale.freedev.app/recipe.php?id=x')[0] === 'index.php');
check('form handler -> default', t('https://nutritale.freedev.app/favorite_toggle.php')[0] === 'index.php');
check('host lookalike -> default', t('https://nutritale.freedev.app.evil.com/favorites.php')[0] === 'index.php');
echo "$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
