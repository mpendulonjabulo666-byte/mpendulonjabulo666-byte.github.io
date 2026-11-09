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
 * Visible notice card with real buttons. $what = recipe title (recipe page)
 * or '' (shopping list: Pick n Pay only needs the ingredients, Uber Eats
 * gets a generic "restaurants near me" link because there is no dish).
 */
function store_links_block(string $dishName = ''): string
{
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
        . '<span>Order the ingredients or the dish from our partners\' sites. Links open in a new tab; prices and stock are theirs.</span>'
        . '<span class="store-notice-buttons">'
        . '<a class="btn btn-small" href="' . h($pnp) . '" target="_blank" rel="noopener noreferrer">' . h($pnpLabel) . '</a>'
        . '<a class="btn btn-small" href="' . h($uber) . '" target="_blank" rel="noopener noreferrer">' . h($uberLabel) . '</a>'
        . '</span></div>';
}
