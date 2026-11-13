<?php
// php tests/hormone_foods_test.php
require_once __DIR__ . '/../includes/hormone_foods.php';
$pass = 0; $fail = 0;
function check(string $l, bool $ok): void { global $pass, $fail; if ($ok) { $pass++; return; } $fail++; echo "FAIL  $l\n"; }

check('focus defaults to both', hormone_focus_key(null) === 'both' && hormone_focus_key('x') === 'both');
check('focus accepts men/women', hormone_focus_key('MEN') === 'men' && hormone_focus_key('women') === 'women');
check('plural keyword matches', hormone_keyword_in('2 eggs', 'egg'));
check('whole word only', !hormone_keyword_in('eggplant', 'egg') && !hormone_keyword_in('pea nut butter', 'peanut'));
check('multi-word keyword', hormone_keyword_in('Extra virgin olive oil', 'olive oil'));
$men = hormone_groups_matched(['beef mince', 'spinach', 'olive oil'], 'men');
check('men: zinc+magnesium+fats found', in_array('zinc', $men) && in_array('magnesium', $men) && in_array('fats', $men));
check('men: no iron group', !in_array('iron', $men));
$women = hormone_groups_matched(['lentils', 'broccoli', 'salmon fillet'], 'women');
check('women: iron, fibre, cruciferous, omega3', count(array_intersect(['iron', 'fibre', 'cruciferous', 'omega3'], $women)) === 4);
check('nothing matches plain sugar', hormone_groups_matched(['white sugar', 'flour'], 'both') === []);
// Wording guard: no treatment/cure claims in the food copy (the doctor note itself negates them).
$copy = json_encode([hormone_food_groups(), hormone_focuses()]);
foreach (['cure', 'fix ', 'treat ', 'boost testosterone', 'balance your hormones', 'guarantee'] as $bad) {
    check("copy avoids '$bad'", stripos($copy, $bad) === false);
}
check('doctor note mentions doctor and pregnancy', stripos(hormone_doctor_note(), 'doctor') !== false && stripos(hormone_doctor_note(), 'pregnant') !== false);
echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
