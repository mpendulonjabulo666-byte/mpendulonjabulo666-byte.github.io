<?php
/**
 * Hormone-supportive eating guide: food groups and recipe matching.
 *
 * Deliberately wording-safe: these are everyday nutrition groups that
 * dietitians commonly link with normal hormone function. Nothing here is a
 * treatment, a diagnosis or a promise. The page that uses this shows a
 * doctor note. Do not add claims like "boosts testosterone" or "fixes PCOS".
 */

/** Food groups (nutrient -> why it is mentioned -> ingredient keywords). */
function hormone_food_groups(): array
{
    return [
        'zinc' => [
            'label' => 'Zinc-rich foods',
            'why' => 'Zinc is one of the minerals your body uses for normal hormone production.',
            'keywords' => ['beef', 'lamb', 'pumpkin seed', 'chickpea', 'lentil', 'cashew', 'oat', 'egg', 'chicken', 'turkey'],
        ],
        'vitd' => [
            'label' => 'Vitamin D foods',
            'why' => 'Vitamin D takes part in normal hormone function. Sunlight helps too.',
            'keywords' => ['egg', 'salmon', 'sardine', 'mackerel', 'tuna', 'mushroom', 'fortified'],
        ],
        'magnesium' => [
            'label' => 'Magnesium foods',
            'why' => 'Magnesium supports sleep and stress recovery, which hormones respond to.',
            'keywords' => ['spinach', 'almond', 'pumpkin seed', 'avocado', 'banana', 'black bean', 'quinoa', 'dark chocolate', 'cashew'],
        ],
        'fats' => [
            'label' => 'Healthy fats',
            'why' => 'Your body builds hormones from fats, so very low-fat eating is not ideal.',
            'keywords' => ['olive oil', 'avocado', 'walnut', 'almond', 'salmon', 'sardine', 'flax', 'chia', 'tahini', 'peanut'],
        ],
        'omega3' => [
            'label' => 'Omega-3 foods',
            'why' => 'Omega-3 fats are linked with a calmer inflammation response.',
            'keywords' => ['salmon', 'sardine', 'mackerel', 'flax', 'chia', 'walnut'],
        ],
        'fibre' => [
            'label' => 'High-fibre foods',
            'why' => 'Fibre helps keep blood sugar steady and supports gut clearance of used hormones.',
            'keywords' => ['oat', 'lentil', 'chickpea', 'bean', 'flax', 'chia', 'broccoli', 'barley', 'brown rice', 'quinoa', 'sweet potato', 'pea'],
        ],
        'iron' => [
            'label' => 'Iron-rich foods',
            'why' => 'Iron matters for energy, especially with monthly blood loss. Pair with vitamin C.',
            'keywords' => ['beef', 'lentil', 'spinach', 'chickpea', 'liver', 'tofu', 'kidney bean', 'sardine'],
        ],
        'cruciferous' => [
            'label' => 'Cruciferous veg',
            'why' => 'Broccoli-family veg help the liver process hormones normally.',
            'keywords' => ['broccoli', 'cauliflower', 'cabbage', 'kale', 'brussels', 'bok choy', 'rocket'],
        ],
        'b6' => [
            'label' => 'Vitamin B6 foods',
            'why' => 'B6 is involved in normal hormone activity and mood.',
            'keywords' => ['banana', 'chickpea', 'salmon', 'potato', 'chicken', 'turkey', 'tuna', 'sunflower seed'],
        ],
    ];
}

/** Focus options: which groups each one looks at, plus plain wording. */
function hormone_focuses(): array
{
    return [
        'men' => [
            'label' => 'Men: testosterone support',
            'intro' => 'Food will not replace sleep, strength training or a healthy weight, but these groups are the ones usually linked with normal testosterone levels.',
            'groups' => ['zinc', 'vitd', 'magnesium', 'fats'],
        ],
        'women' => [
            'label' => 'Women: cycle and hormone balance',
            'intro' => 'These groups are the ones usually linked with a steadier cycle, steadier energy and normal oestrogen and progesterone processing.',
            'groups' => ['iron', 'fibre', 'omega3', 'cruciferous', 'b6', 'magnesium'],
        ],
        'both' => [
            'label' => 'Everyone: general hormone health',
            'intro' => 'A simple base that suits most people: protein, fibre, healthy fats and colour at every meal.',
            'groups' => ['fibre', 'fats', 'magnesium', 'omega3'],
        ],
    ];
}

function hormone_focus_key(?string $raw): string
{
    $raw = strtolower(trim((string)$raw));
    return array_key_exists($raw, hormone_focuses()) ? $raw : 'both';
}

/** Whole-word-ish match of a keyword inside one ingredient name. */
function hormone_keyword_in(string $ingredient, string $keyword): bool
{
    $ingredient = strtolower($ingredient);
    return (bool)preg_match('/\b' . preg_quote($keyword, '/') . '(s|es)?\b/u', $ingredient);
}

/**
 * Which groups (of the chosen focus) do these ingredient names cover?
 * @param string[] $ingredientNames
 * @return string[] group keys
 */
function hormone_groups_matched(array $ingredientNames, string $focus): array
{
    $groups = hormone_food_groups();
    $focusGroups = hormone_focuses()[hormone_focus_key($focus)]['groups'];
    $hit = [];
    foreach ($focusGroups as $g) {
        foreach ($groups[$g]['keywords'] as $kw) {
            foreach ($ingredientNames as $name) {
                if (hormone_keyword_in($name, $kw)) {
                    $hit[] = $g;
                    continue 3;
                }
            }
        }
    }
    return $hit;
}

/** The doctor note shown on the page. */
function hormone_doctor_note(): string
{
    return 'This guide is general nutrition information. It supports healthy eating habits; it does not diagnose, treat or fix any condition. '
        . 'If you have PCOS, thyroid problems, very irregular or painful periods, low energy that will not lift, are pregnant or breastfeeding, '
        . 'or take hormone medication, speak to your doctor or clinic before changing your diet.';
}
