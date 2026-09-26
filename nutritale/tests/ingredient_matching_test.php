<?php
// Tests includes/ingredient_matching.php: the pure tokenizer/depluralizer/
// resolver functions, with a small fixture alias map standing in for the
// database (load_ingredient_alias_map() itself isn't exercised here - it's
// one query, verified against the live database instead, same as
// includes/ai_cache.php in tests/ai_pantry_test.php's neighbourhood).
//
//   php tests/ingredient_matching_test.php
//
// The rule these all defend: compare whole words, never substrings, and
// only ever equate two different words when something (a seeded alias or
// the mechanical depluralizer) actually says they're the same ingredient.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/ingredient_matching.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL  $label\n";
}

// A small fixture standing in for load_ingredient_alias_map()'s real
// output - self-mappings plus the seeded aliases relevant to these tests.
function fixture_alias_map(): array
{
    return [
        'tomato' => 'tomato', 'passata' => 'tomato',
        'pepper' => 'pepper', 'capsicum' => 'pepper', 'capsicums' => 'pepper',
        'chickpea' => 'chickpea', 'garbanzo' => 'chickpea',
        'turkey' => 'turkey', 'mince' => 'turkey',
        'zucchini' => 'zucchini', 'baby marrow' => 'zucchini', 'baby marrows' => 'zucchini',
    ];
}

// --- The two bugs CONTINUE.md §2.2 names by example ----------------------
check(
    'pantry "ice" no longer matches recipe "rice" (was a substring hit)',
    !pantry_has_ingredient('Rice', canonical_ingredient_set('ice', []), [])
);
check(
    'pantry "oil" no longer matches "boiled eggs" (was a substring hit)',
    !pantry_has_ingredient('Boiled eggs', canonical_ingredient_set('oil', []), [])
);
// Meanwhile the intended, non-buggy matches keep working.
check(
    'pantry "rice" still matches recipe "Rice"',
    pantry_has_ingredient('Rice', canonical_ingredient_set('rice', []), [])
);
check(
    'pantry "oil" still matches a recipe that actually calls for oil',
    pantry_has_ingredient('Olive oil', canonical_ingredient_set('oil', []), [])
);

// --- Spelling variants the mechanical depluralizer should catch, with no
// seed data at all --------------------------------------------------------
check('misspelt plural "tomatos" resolves like "tomato"', canonicalize_token('tomatos', []) === 'tomato');
check('correct plural "tomatoes" resolves like "tomato"', canonicalize_token('tomatoes', []) === 'tomato');
check('regular plural "onions" resolves like "onion"', canonicalize_token('onions', []) === 'onion');
check('"-ies" plural "berries" resolves like "berry"', canonicalize_token('berries', []) === 'berry');
check('a 3-letter word ending in s is left alone ("gas" is not "ga")', singularize_token('gas') === 'gas');
check('a double-s word is left alone ("bass" is not "ba")', singularize_token('bass') === 'bass');

// --- Genuine synonyms need the seeded alias table, not mechanics ---------
$map = fixture_alias_map();
check('"passata" resolves to "tomato" only via a seeded alias', canonicalize_token('passata', $map) === 'tomato');
check('"passata" does NOT resolve to "tomato" without that alias', canonicalize_token('passata', []) !== 'tomato');
check('"capsicum" resolves to "pepper"', canonicalize_token('capsicum', $map) === 'pepper');
check('plural "capsicums" also resolves to "pepper"', canonicalize_token('capsicums', $map) === 'pepper');
check('"garbanzo" resolves to "chickpea"', canonicalize_token('garbanzo', $map) === 'chickpea');

// --- Two-word ("bigram") aliases, for a regional term whose individual
// words are ambiguous on their own -----------------------------------------
check(
    'South African "baby marrow" resolves to zucchini as a whole phrase',
    canonical_ingredient_set('baby marrow', $map) === ['zucchini']
);
check(
    'plural "baby marrows" also resolves to zucchini',
    canonical_ingredient_set('baby marrows', $map) === ['zucchini']
);
check(
    '"baby" alone is not silently aliased to zucchini (would break "baby spinach")',
    canonicalize_token('baby', $map) !== 'zucchini'
);

// --- Stopwords: quantities and prep words don't count as, or block, a
// match ---------------------------------------------------------------------
check('quantities and units are stripped', ingredient_tokens('2 cups rice') === ['rice']);
check('prep/cooking-method words are stripped', ingredient_tokens('fresh chopped tomatoes') === ['tomatoes']);
check('a stopword-only phrase yields no tokens', ingredient_tokens('a cup of') === []);
check(
    'an ingredient name that is entirely stopwords cannot be "had" by anything',
    !pantry_has_ingredient('2 cups', ['rice'], [])
);

// --- The real multi-ingredient pantry row already sitting in this app's
// live database - a single row saved as literally "rice and chicken"
// (see CONTINUE.md §2.2) - resolves to both ingredients, not neither ------
check(
    '"rice and chicken" resolves to both rice and chicken',
    canonical_ingredient_set('rice and chicken', []) === ['rice', 'chicken']
);
check(
    '"meat mince" resolves to turkey via the mince alias, plus generic meat',
    canonical_ingredient_set('meat mince', $map) === ['meat', 'turkey']
);

// --- "At least one shared token", not "every token" - the app is meant to
// be forgiving: generic pantry "chicken" should still suggest a recipe
// that calls for the more specific "chicken breast" ------------------------
check(
    'generic pantry "chicken" satisfies recipe ingredient "Chicken breast"',
    pantry_has_ingredient('Chicken breast', canonical_ingredient_set('chicken', []), [])
);
check(
    'a varietal modifier ("cherry") does not need its own alias to match',
    pantry_has_ingredient('Cherry tomatoes', canonical_ingredient_set('tomato', []), [])
);
// But two genuinely unrelated ingredients still don't match.
check(
    'pantry "rice" does not satisfy "Chicken breast"',
    !pantry_has_ingredient('Chicken breast', canonical_ingredient_set('rice', []), [])
);

// --- load_ingredient_alias_map()'s expected shape - not run against the
// database, just documenting the contract the DB-touching function has to
// satisfy for pantry.php's call site to work -------------------------------
check(
    'canonical_ingredient_set works with an empty map (unseeded install)',
    canonical_ingredient_set('rice', []) === ['rice']
);

// --- split_pantry_entry() (CONTINUE.md §2.9 / Step 10): the pantry "Add"
// field's own placeholder text ("e.g. chicken, spinach, rice...") implies
// comma-separated multi-add - this makes that real, without exploding a
// single ingredient's own words -------------------------------------------
check(
    'the exact phrase the field\'s placeholder suggests splits into three',
    split_pantry_entry('chicken, spinach, rice') === ['chicken', 'spinach', 'rice']
);
check(
    'the real "rice and chicken" row already in this app\'s data splits into two',
    split_pantry_entry('rice and chicken') === ['rice', 'chicken']
);
check('"&" is also a separator', split_pantry_entry('chicken & rice') === ['chicken', 'rice']);
check(
    'a single multi-word ingredient is NOT split on its own whitespace',
    split_pantry_entry('chicken breast') === ['chicken breast']
);
check(
    '"and" only splits as a whole word, not inside one ("island" stays intact)',
    split_pantry_entry('rice, island spice') === ['rice', 'island spice']
);
check(
    'mixed separators and messy whitespace all resolve the same way',
    split_pantry_entry('  chicken ,  rice   and   spinach ') === ['chicken', 'rice', 'spinach']
);
check('duplicate entries are not stored twice', split_pantry_entry('rice, rice, chicken') === ['rice', 'chicken']);
check('a lone empty submission yields nothing', split_pantry_entry('  ,  , ') === []);
check('a plain single ingredient still round-trips as one entry', split_pantry_entry('rice') === ['rice']);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
