<?php
require_once __DIR__ . '/../config/db_conn.php';

// Turns free-text ingredient names - both what a recipe lists and what a
// user types into their pantry - into a small set of canonical identities,
// so pantry.php can compare "do these two ingredients mean the same food"
// instead of "does one string appear inside the other" (CONTINUE.md §2.2).
//
// The old matcher did `str_contains($ingNorm, $p) || str_contains($p, $ingNorm)`
// on the whole, untokenized phrase - a substring test with no concept of a
// word boundary or a spelling variant. Pantry "ice" matched recipe "rice"
// (the letters "ice" sit inside "rice"), pantry "oil" matched "boiled
// eggs", and nothing connected "tomatos" or "passata" to "tomato". Two
// independent fixes, both applied per token rather than to the whole
// phrase:
//
//   1. Word-boundary tokenization - split into words and compare whole
//      words, never substrings. This alone fixes the ice/rice and
//      oil/boiled-eggs class of false positive, and a small mechanical
//      depluralizer (singularize_token()) fixes most spelling-only
//      mismatches ("tomatos", "tomatoes" -> "tomato") without needing to
//      seed every regular plural by hand.
//   2. A canonical ingredient + alias table (ingredients /
//      ingredient_aliases, seeded in sql/db_migrations.php) for the mismatches
//      that aren't mechanical - a genuine synonym ("passata" is tomato,
//      "capsicum" is a bell pepper) or an irregular form the depluralizer
//      would get wrong. Only ingredients that actually need one of those
//      get a row; "chicken" or "rice" need no seed data at all and resolve
//      through the tokenizer alone.
//
// A recipe ingredient counts as "in the pantry" if at least one of its
// tokens is covered by the pantry's tokens - deliberately not "every
// token must match". This app is a casual "what can I roughly make"
// helper, not a strict inventory check: generic pantry "chicken" should
// still suggest a recipe that calls for "chicken breast", and "cherry
// tomatoes" shouldn't need its own alias just because "cherry" doesn't
// match anything - the shared "tomato" token is enough.

// Words that describe quantity, preparation, or grammar rather than an
// ingredient's identity. Stripped before matching so they never count as -
// or block - a shared token. Extend this the way allergens.php's exception
// lists grew: one real false match at a time, not speculatively up front.
const INGREDIENT_STOPWORDS = [
    // grammar / connectors
    'and', 'or', 'the', 'a', 'an', 'of', 'with', 'without', 'for', 'to', 'in', 'plus',
    // units and measures a free-typed pantry entry tends to include
    'cup', 'cups', 'tbsp', 'tablespoon', 'tablespoons', 'tsp', 'teaspoon', 'teaspoons',
    'g', 'gram', 'grams', 'kg', 'ml', 'l', 'litre', 'litres', 'liter', 'liters',
    'oz', 'ounce', 'ounces', 'lb', 'lbs', 'pound', 'pounds',
    'can', 'cans', 'packet', 'packets', 'pack', 'packs', 'bag', 'bags',
    'piece', 'pieces', 'slice', 'slices', 'clove', 'cloves', 'pinch', 'dash',
    'bunch', 'handful', 'jar', 'jars', 'tin', 'tins', 'bottle', 'bottles',
    // preparation / cooking method, not identity
    'fresh', 'dried', 'frozen', 'canned', 'tinned', 'cooked', 'raw', 'ripe', 'rolled',
    'large', 'small', 'medium', 'chopped', 'diced', 'sliced', 'minced', 'ground', 'crushed',
    'grated', 'whole', 'boneless', 'skinless', 'lean', 'extra', 'plain', 'low-fat',
    'fat-free', 'unsalted', 'salted', 'organic',
    'boiled', 'baked', 'roasted', 'grilled', 'steamed', 'fried', 'poached', 'toasted',
];

// Normalises one ingredient phrase into the words worth matching on:
// lowercased, numbers and punctuation stripped, split on whitespace,
// stopwords removed. "2 cups fresh chopped tomatoes" -> ['tomatoes'].
// Empty or stopword-only input returns [].
function ingredient_tokens(string $text): array
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[\d.\/]+/u', ' ', $text);    // quantities: "2", "1/2", "3.5"
    $text = preg_replace('/[^\p{L}\s-]/u', ' ', $text); // punctuation, but keep hyphens inside words
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_diff($words, INGREDIENT_STOPWORDS));
}

// A conservative, deliberately small depluralizer used only as a fallback
// when a token isn't in the alias table - see canonicalize_token(). Not
// meant to be linguistically complete: ingredients this app actually knows
// about are covered by explicit aliases in sql/db_migrations.php instead,
// including the irregular ones a blind "-s" strip would get wrong.
// Three-letter-or-shorter words are returned unchanged - short enough that
// a trailing "s" is as likely to be part of the word ("gas") as a plural.
function singularize_token(string $word): string
{
    if (mb_strlen($word) <= 3) {
        return $word;
    }
    if (preg_match('/^(.+)ies$/u', $word, $m)) {
        return $m[1] . 'y';   // "berries" -> "berry"
    }
    if (preg_match('/^(.+)oes$/u', $word, $m)) {
        return $m[1] . 'o';   // "tomatoes" -> "tomato", "potatoes" -> "potato"
    }
    if (preg_match('/^(.+[^s])s$/u', $word, $m)) {
        return $m[1];         // "onions" -> "onion", "tomatos" (misspelt) -> "tomato"
    }
    return $word;
}

// Resolves one token to a canonical ingredient name using $aliasMap (built
// by load_ingredient_alias_map() from the ingredients/ingredient_aliases
// tables - kept as a parameter so this stays unit-testable without a
// database, the same pattern gemini_pantry_ideas() uses for $generator).
// A token with no seeded entry resolves to its own singular form: it still
// matches itself consistently, just doesn't gain anyone else's synonyms.
function canonicalize_token(string $token, array $aliasMap): string
{
    if (isset($aliasMap[$token])) {
        return $aliasMap[$token];
    }
    $singular = singularize_token($token);
    return $aliasMap[$singular] ?? $singular;
}

// The set of canonical ingredient identities a piece of free text touches.
// Tries a two-word phrase before falling back to single words, so a
// regional term whose individual words are ambiguous on their own -
// "baby marrow" (South African for zucchini; "baby" alone means something
// different in "baby spinach") - can be seeded as one alias without
// mis-resolving "baby" everywhere else. Plural because free-typed pantry
// text isn't always one ingredient: a real row in this app's own data is
// literally "rice and chicken" (CONTINUE.md §2.2) - stopword removal
// already drops "and", so this handles it without special-casing
// conjunctions.
function canonical_ingredient_set(string $text, array $aliasMap): array
{
    $tokens = ingredient_tokens($text);
    $canon = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if ($i + 1 < $count) {
            $bigram = $tokens[$i] . ' ' . $tokens[$i + 1];
            if (isset($aliasMap[$bigram])) {
                $canon[] = $aliasMap[$bigram];
                $i++; // consume both tokens
                continue;
            }
        }
        $canon[] = canonicalize_token($tokens[$i], $aliasMap);
    }
    return array_values(array_unique($canon));
}

// Does a pantry (already resolved to a canonical token set, once, by the
// caller) cover this recipe ingredient? True if they share at least one
// canonical token - see the file header for why "at least one" rather than
// "all". An ingredient name that's entirely stopwords/numbers can't be
// "had" by anything.
function pantry_has_ingredient(string $recipeIngredientText, array $pantryCanonicalSet, array $aliasMap): bool
{
    $needed = canonical_ingredient_set($recipeIngredientText, $aliasMap);
    return $needed !== [] && (bool)array_intersect($needed, $pantryCanonicalSet);
}

// Builds the {alias-or-name -> canonical name} map from the database:
// every ingredient resolves to itself, plus every seeded alias (which may
// be a single word or a two-word phrase - see canonical_ingredient_set()).
// Call once per request (a few dozen rows at most) and pass the result
// into the pure functions above.
function load_ingredient_alias_map(): array
{
    $map = [];
    foreach (db()->query('SELECT canonical_name FROM ingredients')->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $map[$name] = $name;
    }
    $stmt = db()->query(
        'SELECT a.alias, i.canonical_name FROM ingredient_aliases a
         JOIN ingredients i ON i.id = a.ingredient_id'
    );
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['alias']] = $row['canonical_name'];
    }
    return $map;
}

// A different granularity from everything above: splits one pantry "Add"
// submission into the separate ingredient *entries* it names, for storing
// as separate rows - never down to individual words the way
// ingredient_tokens() does for matching. "Chicken breast" must stay one
// entry, not become "chicken" + "breast".
//
// The "Add" field is a single text input, but its own placeholder text
// ("e.g. chicken, spinach, rice...") reads like it accepts more than one
// at a time, and this app's own live data shows people take it at its
// word - a real pantry row is literally saved as "rice and chicken"
// (CONTINUE.md §2.9). Splitting at Add time instead means that becomes two
// rows, which can be removed individually and don't rely on the matcher's
// tolerance for a multi-ingredient row to work correctly.
//
// Splits only on a conjunction - a comma, "and", "&", or a line break -
// never on whitespace within a phrase. "and" is matched on word
// boundaries so "island" or "brand" isn't split mid-word.
function split_pantry_entry(string $raw): array
{
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $raw = preg_replace('/\s*(?:,|\n|&|\band\b)\s*/iu', ',', $raw);
    $entries = array_map('trim', explode(',', $raw));
    $entries = array_filter($entries, fn($e) => $e !== '');
    return array_values(array_unique($entries));
}
