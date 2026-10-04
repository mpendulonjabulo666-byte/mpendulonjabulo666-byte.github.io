<?php
// Small inline-SVG icon set (stroke-based, currentColor) so the app has
// no external icon font/dependency.

function icon(string $name, int $size = 20): string
{
    $paths = [
        'check' => '<polyline points="20 6 9 17 4 12"></polyline>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"></path>',
        'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"></path>',
        'search' => '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"></polyline>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"></polyline>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
        'camera' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle>',
        'trash' => '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>',
        'shopping-cart' => '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>',
        'wand' => '<path d="m15 4 1.5 1.5M4 20l10-10M18 6l1.2-1.2a1 1 0 0 0 0-1.4L18 2.2a1 1 0 0 0-1.4 0L15.4 3.4a1 1 0 0 0 0 1.4L16.6 6a1 1 0 0 0 1.4 0Z"></path><path d="M9 3v2M3 9h2M4 4l1.5 1.5"></path>',
        'minus' => '<line x1="5" y1="12" x2="19" y2="12"></line>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>',
        'sun' => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"></path>',
        'share' => '<circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>',
        // Added for the sidebar restyle (2026-09): a leaf-motif divider and
        // a sparkle for the Premium CTA, matching the new brand mockup's
        // icon language as closely as a simple stroke icon reasonably can
        // - see CONTINUE.md-adjacent chat history for why the mockup's
        // fully illustrated, multi-tone icons (chef hat, market stall,
        // fork-and-leaf) aren't reproduced here: that's custom illustration
        // work, not something a line-icon set can approximate honestly.
        // A pointed lens (two arcs sharing the same two endpoints) plus a
        // straight center vein. Three earlier attempts at this one small
        // glyph were each wrong in a different way, caught only by
        // rendering it standalone rather than by reading the path data:
        // mismatched control points collapsed it into a plain circle; too
        // small an arc radius made a rounded oval indistinguishable from a
        // paperclip; and - the counterintuitive one - giving the two arcs
        // *opposite* sweep flags (the seemingly obvious way to make them
        // bulge opposite ways) instead made them overlap on the same side,
        // because reversing which endpoint comes first already flips the
        // effective geometry once. Matching sweep flags is what actually
        // produces the two-sided lens here.
        'leaf' => '<path d="M12 3A13 13 0 0 1 12 21A13 13 0 0 1 12 3Z"></path><path d="M12 6v12"></path>',
        'sparkles' => '<path d="M12 3 13.5 9.5 20 11 13.5 12.5 12 19 10.5 12.5 4 11 10.5 9.5Z"></path>',
        // Added for the login/register redesign. mail/lock/eye-off/arrow-
        // right/target are copied verbatim from the mockup's own tested
        // source rather than redrawn from memory, after the leaf icon
        // above cost three attempts to get right by hand.
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><polyline points="3 6 12 13 21 6"></polyline>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V8a4 4 0 0 1 8 0v3"></path>',
        // Not from the mockup (it only supplied the "hidden" state below).
        // A hand-typed curved path here was *also* wrong on the first try
        // (same lesson as 'leaf' above) - an ellipse is a shape a parser
        // genuinely cannot get subtly wrong, so that's what this uses.
        'eye' => '<ellipse cx="12" cy="12" rx="10" ry="6"></ellipse><circle cx="12" cy="12" r="3"></circle>',
        'eye-off' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="13 6 19 12 13 18"></polyline>',
        'target' => '<circle cx="12" cy="12" r="9"></circle><circle cx="12" cy="12" r="4"></circle><path d="M12 3v3M12 18v3M3 12h3M18 12h3"></path>',
        // Added for the admin suite build: real rectangles/lines only, same
        // reasoning as 'eye' above - no freehand curve to get subtly wrong.
        'grid' => '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>',
        'alert-triangle' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'bar-chart' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
    ];

    $body = $paths[$name] ?? '<circle cx="12" cy="12" r="9"></circle>';

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

// Google's and Facebook's brand marks, for the "or continue with" social
// buttons on login.php/register.php - real multi-colour fills, so these
// don't go through icon() above (which forces a single-colour stroke
// outline via fill="none" on every glyph, the right choice for the app's
// own icon set but wrong for a third-party brand mark that has official
// colours). Paths verified against the login mockup's own tested source
// rather than redrawn from memory.
function icon_google(int $size = 20): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">'
        . '<path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.4a5.5 5.5 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.6-5.2 3.6-8.8Z"/>'
        . '<path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3a7.2 7.2 0 0 1-10.7-3.8H1.3v3.1A12 12 0 0 0 12 24Z"/>'
        . '<path fill="#FBBC05" d="M5.3 14.3a7.2 7.2 0 0 1 0-4.6V6.6H1.3a12 12 0 0 0 0 10.8l4-3.1Z"/>'
        . '<path fill="#EA4335" d="M12 4.7c1.8 0 3.4.6 4.6 1.8l3.5-3.5A12 12 0 0 0 1.3 6.6l4 3.1A7.2 7.2 0 0 1 12 4.7Z"/>'
        . '</svg>';
}

function icon_facebook(int $size = 20): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">'
        . '<path fill="#1877F2" d="M13.5 21.9v-8h2.7l.4-3.2h-3.1V8.6c0-.9.3-1.5 1.6-1.5h1.6V4.2c-.3 0-1.3-.1-2.4-.1-2.4 0-4 1.5-4 4.1v2.5H7.6v3.2h2.3v8h3.6Z"/>'
        . '</svg>';
}

// Apple's mark is officially monochrome (unlike Google's/Facebook's
// multi-colour logos above), so this one uses currentColor to follow the
// button's own text colour in light and dark mode rather than a fixed
// brand colour - path verified by rendering it standalone before wiring
// into login.php/register.php, per this app's own "never trust a
// hand-typed SVG path" rule.
function icon_apple(int $size = 20): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . '<path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zm3.61-3.325c.836-1.012 1.4-2.427 1.245-3.831-1.207.052-2.662.805-3.532 1.818-.771.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701z"/>'
        . '</svg>';
}

// WhatsApp's mark, for the "Share this recipe" fallback menu on
// recipe.php (see assets/js/recipe-share.js) - monochrome like Apple's
// above, so currentColor here too. Path also verified by standalone
// render before being wired in.
function icon_whatsapp(int $size = 20): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413"/>'
        . '</svg>';
}

function render_stars(float $average, ?int $count = null, int $size = 14): string
{
    $rounded = (int)round($average);
    $html = '<span class="star-rating">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="' . ($i <= $rounded ? 'star-filled' : 'star-empty') . '">' . icon('star', $size) . '</span>';
    }
    if ($count !== null) {
        $html .= '<span class="star-count">' . ($count > 0 ? number_format($average, 1) . ' (' . $count . ')' : 'No reviews yet') . '</span>';
    }
    $html .= '</span>';
    return $html;
}

function render_theme_toggle(): string
{
    return '<button type="button" id="theme-toggle" class="theme-toggle-btn" aria-label="Toggle dark mode">'
        . '<span class="theme-icon-sun">' . icon('sun', 18) . '</span>'
        . '<span class="theme-icon-moon">' . icon('moon', 18) . '</span>'
        . '</button>';
}

// $size sets the rendered height; width follows the source image's own
// aspect ratio (the isolated book mark isn't square). 1024x525 is the new
// (2026-09) illustration's real pixel size - update this ratio again if
// the source image is ever replaced with a differently-proportioned one.
// The brand mark: an open book with a leaf rising from the spine. Inline
// SVG, not an image file, for three reasons the old raster logo failed on -
// it has no background of its own so the page shows through instead of a
// white box, it stays sharp at every size on every density, and its colours
// are CSS variables so the dark theme lightens them like everything else.
//
// The name is deliberately not in here. It is real HTML text beside the
// mark (brand_wordmark_html()), the way app logos normally work, so it stays
// selectable, translatable and legible on both themes.
//
// Geometry is shared with scripts/make_logo.php, which draws the same shapes
// for the PNG app icons - change one and re-run that script.
function nutritale_logo_svg(int $size = 48): string
{
    return '<svg class="brand-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64"'
        . ' role="img" aria-label="' . h(APP_NAME) . '" xmlns="http://www.w3.org/2000/svg">'
        . '<path d="M31 34C23 29 13 30 4 35C4 43 4 48 4 52C14 47 23 46 31 51Z" fill="var(--logo-ink)"/>'
        . '<path d="M33 34C41 29 51 30 60 35C60 43 60 48 60 52C50 47 41 46 33 51Z" fill="var(--logo-ink)"/>'
        . '<path d="M32 8C41 16 42 27 32 38C22 27 23 16 32 8Z" fill="var(--logo-leaf)"/>'
        . '<rect x="30.7" y="15" width="2.6" height="35" rx="1.3" fill="var(--logo-ink)"/>'
        . '</svg>';
}

// The "NutriTale" wordmark styled to match the brand mark: "Nutri" in a
// deep forest green, "Tale" in gold, both in a heavier weight of the
// display serif than headings use elsewhere. Kept as real, selectable,
// theme-aware HTML text rather than baking it into the logo image itself
// - a raster wordmark in a fixed dark green would go illegible on the
// dark theme's near-black background, and would need re-exporting for
// every place it appears instead of just following the CSS variables
// (see --brand-nutri / --brand-tale, which do get lighter in dark mode
// for exactly that legibility reason).
function brand_wordmark_html(): string
{
    return '<span class="brand-wordmark"><span class="brand-nutri">Nutri</span><span class="brand-tale">Tale</span></span>';
}
