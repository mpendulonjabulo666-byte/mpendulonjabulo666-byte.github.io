<?php
// Link-outs to Pick n Pay and Uber Eats. LINKS ONLY: no scraping, no prices,
// no stock claims. Neither company offers a public consumer API, so the app
// just opens their own search page for the ingredient/dish and they take it
// from there. All URL patterns live in this file so a change is one edit.
//
// Pick n Pay pattern confirmed from live search results. The Uber Eats
// search pattern is NOT confirmed - test it on a phone. If it lands on a
// generic page, change UBER_EATS_SEARCH_URL to 'https://www.ubereats.com/za/near-me'
// (verified) and drop the {q} placeholder.

const PNP_SEARCH_URL = 'https://www.pnp.co.za/pnpstorefront/pnp/en/search/?text={q}';
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
        . '" target="_blank" rel="noopener noreferrer" title="Opens Pick n Pay\'s own search in a new tab">Pick n Pay</a>';
}

/** Recipe-level block: find the dish on Uber Eats + a note that we only link. */
function store_links_block(string $dishName): string
{
    return '<p class="store-links muted">Prefer to order it ready-made? '
        . '<a href="' . h(uber_eats_search_link($dishName)) . '" target="_blank" rel="noopener noreferrer">Search “' . h(store_search_term($dishName)) . '” on Uber Eats</a>. '
        . 'Links open Pick n Pay and Uber Eats in a new tab. NutriTale does not sell, price or stock these items.</p>';
}
