<?php
// php tests/store_links_test.php
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
require_once __DIR__ . '/../includes/store_links.php';
$pass = 0; $fail = 0;
function check(string $l, bool $ok): void { global $pass, $fail; if ($ok) { $pass++; return; } $fail++; echo "FAIL  $l\n"; }

check('pnp link encodes spaces', pnp_search_link('olive oil') === 'https://www.pnp.co.za/search/olive%20oil');
check('pnp link encodes & and quotes', strpos(pnp_search_link('mac & "cheese"'), '&') === false || strpos(pnp_search_link('mac & "cheese"'), '%26') !== false);
check('tags stripped', strpos(pnp_search_link('<script>x</script>flour'), '%3C') === false);
check('length capped', strlen(rawurldecode(substr(pnp_search_link(str_repeat('a', 500)), strpos(pnp_search_link('a'), '=') + 0))) < 200);
check('block has the Pick n Pay button', strpos(store_links_block('Lentil Soup'), 'Shop at Pick n Pay') !== false && strpos(store_links_block('Lentil Soup'), 'pnp.co.za/search/Lentil%20Soup') !== false);
check('list version (no dish) opens Pick n Pay', strpos(store_links_block(), 'https://www.pnp.co.za/') !== false);
check('locked: no store link shown, upsell instead', strpos(store_links_block('Lentil Soup', false), 'pnp.co.za') === false && strpos(store_links_block('Lentil Soup', false), 'premium.php') !== false);
// Uber Eats was removed for good on 2026-11-14 (cook-it-yourself app). Guard against it creeping back.
foreach (['Bunny Chow', 'Chicken Burger', ''] as $dish) {
    foreach ([true, false] as $unlocked) {
        check("no Uber Eats in block ('$dish', " . ($unlocked ? 'premium' : 'free') . ')', stripos(store_links_block($dish, $unlocked), 'uber') === false);
    }
}
check('uber helpers are gone', !function_exists('uber_eats_tag') && !function_exists('dish_on_uber_eats') && !function_exists('uber_eats_search_link'));
$root = dirname(__DIR__);
foreach (['landing.php', 'premium.php', 'recipe.php', 'hormone_guide.php', 'shopping_list.php', 'includes/recipe_card.php', 'terms.php', 'privacy.php', 'refund.php'] as $f) {
    check("$f does not mention Uber", stripos(file_get_contents("$root/$f"), 'uber') === false);
}
echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
