<?php
require_once __DIR__ . '/../config/database.php';

// Shared allergen vocabulary and free-text detection.
//
// Two separate jobs live here:
//
//   1. ALLERGEN_OPTIONS — the eight allergens a user can tick in
//      onboarding and a vendor can tag a recipe with. Structured data,
//      matched exactly.
//   2. text_allergen_hits() — scanning *unstructured* text (an AI-written
//      meal idea) for foods that imply an allergen. This is the safety
//      net for anything we didn't tag ourselves.
//
// Job 2 is deliberately cautious: it would rather throw away a safe
// recipe than show an unsafe one. A false positive costs a regeneration;
// a false negative costs someone a hospital visit.

const ALLERGEN_OPTIONS = ['dairy', 'eggs', 'gluten', 'nuts', 'soy', 'fish', 'sesame', 'shellfish'];

// Foods that imply each allergen. Matched on word boundaries, so "nuts"
// won't fire on "nutside" — but plenty of compound words still need the
// exceptions below.
function allergen_keywords(): array
{
    return [
        'dairy' => [
            // "creamy" is deliberately absent: it describes texture, not
            // dairy, and coconut curries are described as creamy constantly.
            // Real dairy shows up as an actual ingredient in the shopping
            // list, which is scanned too.
            'milk', 'cheese', 'butter', 'cream', 'yoghurt', 'yogurt', 'ghee',
            'custard', 'mozzarella', 'cheddar', 'parmesan', 'parmigiana', 'parmigiano',
            'ricotta', 'feta', 'halloumi',
            'mascarpone', 'buttermilk', 'whey', 'casein', 'paneer', 'creme fraiche',
        ],
        'eggs' => [
            'egg', 'eggs', 'mayonnaise', 'mayo', 'meringue', 'aioli', 'frittata',
            'omelette', 'omelet', 'custard', 'quiche',
        ],
        'gluten' => [
            'wheat', 'flour', 'bread', 'breadcrumb', 'breadcrumbs', 'pasta', 'noodle',
            'noodles', 'spaghetti', 'macaroni', 'couscous', 'barley', 'rye', 'semolina',
            'cracker', 'crackers', 'tortilla', 'pastry', 'biscuit', 'cake', 'roux',
            'seitan', 'bulgur', 'farro', 'spelt', 'soy sauce', 'pita', 'baguette', 'bun',
            'buns', 'wrap', 'wraps', 'pie crust', 'puff pastry', 'panko',
        ],
        'nuts' => [
            'nut', 'nuts', 'peanut', 'peanuts', 'almond', 'almonds', 'cashew', 'cashews',
            'walnut', 'walnuts', 'pecan', 'pecans', 'pistachio', 'pistachios', 'hazelnut',
            'hazelnuts', 'macadamia', 'praline', 'marzipan', 'satay', 'nutella',
            'pine nut', 'pine nuts', 'brazil nut', 'nut butter', 'almond milk',
        ],
        'soy' => [
            'soy', 'soya', 'soybean', 'soybeans', 'tofu', 'edamame', 'miso', 'tempeh',
            'tamari', 'soy sauce',
        ],
        'fish' => [
            'fish', 'salmon', 'tuna', 'cod', 'anchovy', 'anchovies', 'sardine', 'sardines',
            'mackerel', 'trout', 'haddock', 'hake', 'tilapia', 'snoek', 'kingklip',
            'worcestershire', 'fish sauce', 'caviar', 'roe',
        ],
        'sesame' => [
            'sesame', 'tahini', 'hummus', 'houmous', 'halva', "za'atar", 'zaatar', 'benne',
        ],
        'shellfish' => [
            'shellfish', 'shrimp', 'shrimps', 'prawn', 'prawns', 'crab', 'lobster',
            'mussel', 'mussels', 'clam', 'clams', 'oyster', 'oysters', 'scallop',
            'scallops', 'calamari', 'squid', 'crayfish', 'langoustine',
        ],
    ];
}

// Phrases that contain an allergen keyword but don't carry the allergen.
// Blanked out before that allergen's keywords are scanned, so "nutmeg"
// and "coconut milk" don't trigger a nut alarm on every curry.
//
// Coconut is a judgement call: it's a tree nut botanically and to the
// FDA, but most tree-nut allergies tolerate it and treating it as a nut
// would reject a large share of otherwise fine recipes. Excluded here —
// the allergen disclaimer (Step 2) covers the residual risk.
function allergen_exceptions(): array
{
    return [
        'nuts' => ['nutmeg', 'coconut', 'butternut', 'water chestnut', 'nutritional yeast', 'nutrition'],
        'dairy' => ['coconut cream', 'coconut milk', 'oat milk', 'almond milk', 'soy milk', 'peanut butter', 'cocoa butter', 'nut butter'],
        'eggs' => ['eggplant', 'egg-free', 'eggless'],
        'gluten' => [
            'rice noodle', 'rice noodles', 'glass noodle', 'glass noodles',
            'buckwheat', 'rice flour', 'almond flour', 'chickpea flour', 'corn tortilla',
        ],
        'fish' => ['fish-free', 'shellfish'],
        'soy' => ['soy-free'],
    ];
}

// "X-free" claims, which cover the noun that follows them. Applied before
// the literal exceptions.
//
// This is the one place the model's own assertion is allowed to buy it
// trust, and it's scoped tightly on purpose: at most two following words,
// so "gluten-free banana bread" passes but a gluten-free claim in a title
// can't launder wheat flour further down the shopping list. Without this,
// every legitimate substitution offered to a coeliac user gets rejected
// and they end up with no suggestions at all.
function allergen_exception_patterns(): array
{
    return [
        'gluten' => ['/\bgluten[- ]free\b(\s+\S+){0,2}/iu'],
        'dairy' => ['/\b(dairy|lactose)[- ]free\b(\s+\S+){0,2}/iu', '/\bnon[- ]dairy\b(\s+\S+){0,2}/iu'],
        'eggs' => ['/\begg[- ]free\b(\s+\S+){0,2}/iu'],
        'nuts' => ['/\bnut[- ]free\b(\s+\S+){0,2}/iu'],
        'soy' => ['/\bsoy[- ]free\b(\s+\S+){0,2}/iu'],
    ];
}

// Returns the subset of $userAllergens that $text appears to contain.
// Empty array means nothing detected — which is not the same as "safe",
// only "nothing we recognise".
function text_allergen_hits(string $text, array $userAllergens): array
{
    if ($text === '' || !$userAllergens) {
        return [];
    }

    $keywords = allergen_keywords();
    $exceptions = allergen_exceptions();
    $patterns = allergen_exception_patterns();
    $haystack = mb_strtolower($text);
    $hits = [];

    foreach ($userAllergens as $allergen) {
        if (!isset($keywords[$allergen])) {
            continue;
        }

        // Blank the known-safe phrases first so their substrings can't
        // match below. Replaced with a space, not '', so we don't fuse
        // neighbouring words into a new accidental match.
        $scannable = $haystack;
        foreach ($patterns[$allergen] ?? [] as $pattern) {
            $scannable = preg_replace($pattern, ' ', $scannable);
        }
        foreach ($exceptions[$allergen] ?? [] as $phrase) {
            $scannable = str_replace($phrase, ' ', $scannable);
        }

        foreach ($keywords[$allergen] as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $scannable)) {
                $hits[] = $allergen;
                break;
            }
        }
    }

    return array_values(array_unique($hits));
}

// Every piece of text in one AI meal idea, flattened for scanning. Keep
// this in step with the response schema in ai_pantry.php — a field that
// isn't collected here is a field that never gets checked.
function meal_text_for_scanning(array $meal): string
{
    $parts = [
        $meal['title'] ?? '',
        $meal['description'] ?? '',
    ];
    foreach ($meal['uses_from_pantry'] ?? [] as $item) {
        $parts[] = is_string($item) ? $item : '';
    }
    foreach ($meal['shopping_list'] ?? [] as $item) {
        $parts[] = ($item['item'] ?? '') . ' ' . ($item['quantity'] ?? '');
    }
    return implode(' . ', array_filter($parts));
}

// Convenience: the allergens a user has asked us to avoid.
function user_allergens(int $userId): array
{
    $stmt = db()->prepare('SELECT allergen FROM user_allergens WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
