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
require_once __DIR__ . '/../includes/ingredient_matching.php';
check('group key validated', hormone_group_key('zinc', 'men') === 'zinc' && hormone_group_key('iron', 'men') === '' && hormone_group_key('<x>', 'women') === '');
check('have list splits and caps', hormone_have_list('chicken, spinach and rice') === ['chicken', 'spinach', 'rice'] && count(hormone_have_list(str_repeat('a,', 40))) <= 12);
check('have list strips tags', strpos(implode(',', hormone_have_list('<b>beef</b>')), '<') === false);
$pk = hormone_pickup_foods('men', '', ['beef', 'eggs']);
check('pickup leaves out what you have', !in_array('beef', $pk) && !in_array('egg', $pk) && count($pk) > 0 && count($pk) <= 10);
check('pickup respects group', hormone_pickup_foods('women', 'cruciferous', []) === array_slice(hormone_food_groups()['cruciferous']['keywords'], 0, 10));
echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
