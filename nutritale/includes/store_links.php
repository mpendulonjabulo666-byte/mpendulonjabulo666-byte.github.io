<?php
// Link-outs to Pick n Pay and Uber Eats. LINKS ONLY: no scraping, no prices,
// no stock claims. Neither company offers a public consumer API, so the app
// just opens their own search page for the ingredient/dish and they take it
// from there. All URL patterns live in this file so a change is one edit.
//
// The Uber Eats
// search pattern is NOT confirmed - test it on a phone. If it lands on a
// generic page, change UBER_EATS_SEARCH_URL to 'https://www.ubereats.com/za/near-me'
// (verified) and drop the {q} placeholder.

// Confirmed 2026-11-09 on a real phone: /search/feta shows 'Results for "feta"'.
// (The old /pnpstorefront/.../search/?text= pattern only lands on the home page.)
const PNP_SEARCH_URL = 'https://www.pnp.co.za/search/{q}';
const UBER_EATS_SEARCH_URL = 'https://www.ubereats.com/za/search?q={q}';

function store_search_term(string $text): string
{
    // Drop leading quantities/units noise is not attempted: the caller
    // passes the ingredient or dish name. Keep it short and plain.
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    return mb_substr($text, 0, 80);
}

function pnp_search_link(string $text): string
{
    return str_replace('{q}', rawurlencode(store_search_term($text)), PNP_SEARCH_URL);
}

function uber_eats_search_link(string $text): string
{
    return str_replace('{q}', rawurlencode(store_search_term($text)), UBER_EATS_SEARCH_URL);
}

/** Small "Buy at Pick n Pay" link for one ingredient. */
function pnp_link_html(string $ingredientName): string
{
    return '<a class="store-link" href="' . h(pnp_search_link($ingredientName))
        . '" target="_blank" rel="noopener noreferrer" title="Finds this item on Pick n Pay in a new tab">Pick n Pay</a>';
}

/**
 * Dishes commonly sold ready-made by restaurants, takeaways and bakeries on
 * Uber Eats in South Africa. This is our own judgement from the dish name,
 * not data from Uber: it decides whether the "Find this dish on Uber Eats"
 * button and the "Often on Uber Eats" tag make sense. Home-cooking dishes
 * (bredies, potjiekos, overnight oats...) don't get the button. Add or
 * remove words here; matching is whole-word and case-insensitive.
 */
const UBER_EATS_DISH_WORDS = [
    'bunny chow', 'kota', 'gatsby', 'burger', 'pizza', 'wings', 'fried chicken',
    'grilled chicken', 'chicken', 'curry', 'wrap', 'shawarma', 'falafel', 'pita',
    'sushi', 'samoosa', 'samosa', 'fish and chips', 'hake', 'chips', 'toast',
    'pancakes', 'shakshuka', 'salad', 'bowl', 'smoothie', 'parfait', 'vetkoek',
    'amagwinya', 'fat cakes', 'boerewors roll', 'boerewors rolls', 'pap, wors',
    'koeksisters', 'melktert', 'milk tart', 'malva pudding', 'pasta', 'stir-fry',
    'sandwich', 'chili', 'boerewors', 'sosaties', 'soup', 'peppermint crisp tart',
];

function dish_on_uber_eats(string $title): bool
{
    foreach (UBER_EATS_DISH_WORDS as $word) {
        if (preg_match('/(?<![a-z])' . preg_quote($word, '/') . '(?![a-z])/i', $title)) {
            return true;
        }
    }
    return false;
}

/** Small tag for recipe cards and the recipe page. Empty when not a takeaway dish. */
function uber_eats_tag(string $title): string
{
    return dish_on_uber_eats($title)
        ? '<span class="tag uber-tag" title="Restaurants on Uber Eats often sell this dish">Often on Uber Eats</span>'
        : '';
}

/**
 * Visible notice card with real buttons. $what = recipe title (recipe page)
 * or '' (shopping list: Pick n Pay only needs the ingredients, Uber Eats
 * gets a generic "restaurants near me" link because there is no dish).
 */
function store_links_block(string $dishName = '', bool $unlocked = true): string
{
    // Premium perk: free members see what they're missing and where to
    // unlock it, never the links themselves (premium.php sells it).
    if (!$unlocked) {
        return '<div class="store-notice store-notice-locked" role="note">'
            . '<strong>Get it delivered (Premium)</strong>'
            . '<span>Premium members get one-tap links to buy these ingredients at Pick n Pay'
            . ($dishName === '' ? ' or order from restaurants on Uber Eats'
                : (dish_on_uber_eats($dishName) ? ' or find the dish on Uber Eats' : ''))
            . '.</span>'
            . '<span class="store-notice-buttons"><a class="btn btn-small" href="premium.php">Unlock with Premium</a></span>'
            . '</div>';
    }
    // On a recipe, the Uber button only appears for dishes restaurants
    // actually sell (dish_on_uber_eats). The shopping list always offers it.
    $showUber = $dishName === '' || dish_on_uber_eats($dishName);
    $uber = $dishName !== ''
        ? uber_eats_search_link($dishName)
        : 'https://www.ubereats.com/za/near-me';
    $uberLabel = $dishName !== '' ? 'Find this dish on Uber Eats' : 'Browse Uber Eats';
    $pnp = $dishName !== ''
        ? pnp_search_link($dishName)
        : 'https://www.pnp.co.za/';
    $pnpLabel = $dishName !== '' ? 'Shop at Pick n Pay' : 'Open Pick n Pay';
    return '<div class="store-notice" role="note">'
        . '<strong>Get it delivered</strong>'
        . '<span>' . ($showUber
            ? 'Order the ingredients, or the ready-made dish, from these sites.'
            : 'Order the ingredients from Pick n Pay.')
            . ' Links open in a new tab; prices and stock are theirs.</span>'
        . '<span class="store-notice-buttons">'
        . '<a class="btn btn-small" href="' . h($pnp) . '" target="_blank" rel="noopener noreferrer">' . h($pnpLabel) . '</a>'
        . ($showUber ? '<a class="btn btn-small" href="' . h($uber) . '" target="_blank" rel="noopener noreferrer">' . h($uberLabel) . '</a>' : '')
        . '</span></div>';
}
