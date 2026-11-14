<?php
// Link-outs to Pick n Pay. LINKS ONLY: no scraping, no prices, no stock
// claims. Pick n Pay offers no public consumer API, so the app just opens
// their own search page for the ingredient and they take it from there.
//
// Uber Eats links were removed on purpose (2026-11-14): NutriTale is about
// cooking healthy food yourself, so the app does not send people to
// takeaways. Do not add them back.

// Confirmed 2026-11-09 on a real phone: /search/feta shows 'Results for "feta"'.
// (The old /pnpstorefront/.../search/?text= pattern only lands on the home page.)
const PNP_SEARCH_URL = 'https://www.pnp.co.za/search/{q}';

function store_search_term(string $text): string
{
    // The caller passes the ingredient or dish name. Keep it short and plain.
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    return mb_substr($text, 0, 80);
}

function pnp_search_link(string $text): string
{
    return str_replace('{q}', rawurlencode(store_search_term($text)), PNP_SEARCH_URL);
}

/** Small "Buy at Pick n Pay" link for one ingredient. */
function pnp_link_html(string $ingredientName): string
{
    return '<a class="store-link" href="' . h(pnp_search_link($ingredientName))
        . '" target="_blank" rel="noopener noreferrer" title="Finds this item on Pick n Pay in a new tab">Pick n Pay</a>';
}

/**
 * Visible notice card with a real button. $dishName = recipe title (recipe
 * page) or '' (shopping list, hormone guide).
 */
function store_links_block(string $dishName = '', bool $unlocked = true): string
{
    // Premium perk: free members see what they're missing and where to
    // unlock it, never the link itself (premium.php sells it).
    if (!$unlocked) {
        return '<div class="store-notice store-notice-locked" role="note">'
            . '<strong>Get the ingredients (Premium)</strong>'
            . '<span>Premium members get one-tap links to buy these ingredients at Pick n Pay.</span>'
            . '<span class="store-notice-buttons"><a class="btn btn-small" href="premium.php">Unlock with Premium</a></span>'
            . '</div>';
    }
    $pnp = $dishName !== '' ? pnp_search_link($dishName) : 'https://www.pnp.co.za/';
    $pnpLabel = $dishName !== '' ? 'Shop at Pick n Pay' : 'Open Pick n Pay';
    return '<div class="store-notice" role="note">'
        . '<strong>Get the ingredients</strong>'
        . '<span>Order the ingredients from Pick n Pay. The link opens in a new tab; prices and stock are theirs.</span>'
        . '<span class="store-notice-buttons">'
        . '<a class="btn btn-small" href="' . h($pnp) . '" target="_blank" rel="noopener noreferrer">' . h($pnpLabel) . '</a>'
        . '</span></div>';
}
