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
check('uber link has dish', strpos(uber_eats_search_link('Beef-Stuffed Pasta'), 'Beef-Stuffed%20Pasta') !== false);
check('html link is new-tab + noopener', strpos(pnp_link_html('milk'), 'rel="noopener noreferrer"') !== false);
check('html escapes injection', strpos(pnp_link_html('"><script>'), '<script>') === false);
check('block has both buttons', strpos(store_links_block('Pap, Wors and Chakalaka Plate'), 'Uber Eats') !== false && strpos(store_links_block('Pap, Wors and Chakalaka Plate'), 'Pick n Pay') !== false);
check('block says prices are theirs', strpos(store_links_block('Pap'), 'prices and stock are theirs') !== false);
check('list version (no dish) still has links', strpos(store_links_block(), 'ubereats.com/za/near-me') !== false);
check('bunny chow is a takeaway dish', dish_on_uber_eats('Bunny Chow with Lamb Curry'));
check('tomato bredie is not', !dish_on_uber_eats('Tomato Bredie'));
check('overnight oats is not', !dish_on_uber_eats('Rooibos Overnight Oats'));
check('whole words only (no match inside a word)', !dish_on_uber_eats('Chipotle beans') && dish_on_uber_eats('Fish and Chips'));
check('home dish: no Uber button', strpos(store_links_block('Tomato Bredie'), 'Uber Eats') === false);
check('takeaway dish: Uber button', strpos(store_links_block('Bunny Chow'), 'Find this dish on Uber Eats') !== false);
check('tag only for takeaway dishes', uber_eats_tag('Bunny Chow') !== '' && uber_eats_tag('Tomato Bredie') === '');
check('locked: no store links shown', strpos(store_links_block('Bunny Chow', false), 'pnp.co.za') === false && strpos(store_links_block('Bunny Chow', false), 'ubereats.com') === false);
check('locked: points to premium', strpos(store_links_block('Bunny Chow', false), 'premium.php') !== false);
check('locked home dish does not promise Uber', strpos(store_links_block('Tomato Bredie', false), 'Uber') === false);
echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
