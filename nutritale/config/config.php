<?php
// Optional untracked file for local-only secrets (never committed - see
// .gitignore). It just calls putenv() for whichever keys you want to set,
// so every getenv()-first setting below picks them up automatically.
//
// InfinityFree (and some other free hosts) disable putenv() or don't let
// getenv() see what it sets, so nt_env() below falls back to reading the
// same putenv('KEY=value') lines straight out of config.local.php. Either
// way works; no change to that file is needed. The include is silenced
// with @ so a disabled putenv() can't print a warning into the page.
if (!function_exists('putenv')) {
    // putenv() disabled by the host: stub it so config.local.php still runs
    // (its values are picked up by nt_env() instead).
    function putenv($assignment) { return false; }
}
if (file_exists(__DIR__ . '/config.local.php')) {
    @require __DIR__ . '/config.local.php';
}

function nt_local_cfg(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $cfg = [];
    $file = __DIR__ . '/config.local.php';
    if (is_readable($file)) {
        $src = (string)file_get_contents($file);
        if (preg_match_all("/^\\s*putenv\\(\\s*'([A-Z0-9_]+)=(.*)'\\s*\\)\\s*;/m", $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $cfg[$row[1]] = stripslashes($row[2]);
            }
        }
    }
    return $cfg;
}

// getenv() first (real environment variables, e.g. Railway), then whatever
// config.local.php defined. Returns false when unset, exactly like getenv().
function nt_env(string $key)
{
    $v = getenv($key);
    if ($v !== false) {
        return $v;
    }
    $local = nt_local_cfg();
    return $local[$key] ?? false;
}

// Fill these in with your own database's connection details before
// running setup.php. If you're on shared hosting, your host's control
// panel (e.g. cPanel > MySQL Databases) will give you these values.
// On a platform that injects DB credentials as environment variables
// (e.g. Railway's MySQL plugin, which sets MYSQLHOST/MYSQLPORT/etc.),
// those are picked up automatically — no edit needed here.
define('DB_HOST', nt_env('DB_HOST') ?: (nt_env('MYSQLHOST') ?: 'localhost'));
define('DB_PORT', nt_env('DB_PORT') ?: (nt_env('MYSQLPORT') ?: '3306'));
define('DB_NAME', nt_env('DB_NAME') ?: (nt_env('MYSQLDATABASE') ?: 'nutritale'));
define('DB_USER', nt_env('DB_USER') ?: (nt_env('MYSQLUSER') ?: 'root'));
define('DB_PASS', nt_env('DB_PASS') ?: (nt_env('MYSQLPASSWORD') ?: ''));

define('APP_NAME', 'NutriTale');

// PayFast (https://www.payfast.co.za) payment settings for premium recipe
// purchases. The values below are PayFast's published SANDBOX test
// credentials — they only work against sandbox.payfast.co.za and never
// move real money. Before going live: create a real PayFast merchant
// account, replace PAYFAST_MERCHANT_ID/KEY with your own, set a
// passphrase to match what you configure in your PayFast account
// settings, and set PAYFAST_SANDBOX to false. See DEPLOYMENT.md's "Going
// live with PayFast" section for the full checklist - no other code
// change is needed, only these values.
define('PAYFAST_SANDBOX', true);
define('PAYFAST_MERCHANT_ID', '10000100');
define('PAYFAST_MERCHANT_KEY', '46f0cd694581a');
define('PAYFAST_PASSPHRASE', '');

// Platform economics. PLATFORM_COMMISSION_PCT applies to both premium
// recipe sales and ingredient marketplace sales.
define('PLATFORM_COMMISSION_PCT', 10);
define('PREMIUM_MONTHLY_PRICE', 99.00);

// Google Gemini API (https://aistudio.google.com/apikey) — powers the
// "Get AI ideas" button on the pantry page (real AI-generated meal ideas
// + shopping list, on top of the always-free rule-based recipe matcher
// above it). Leave blank to disable the AI button entirely. Get a free
// key at aistudio.google.com and paste it here on your server — never
// commit a real key to git. GEMINI_MAX_OUTPUT_TOKENS caps how long each
// response is allowed to be; every call also counts against the daily
// caps below, so usage (and cost) stays bounded per user.
define('GEMINI_API_KEY', nt_env('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('GEMINI_MAX_OUTPUT_TOKENS', 1500);

// Unsplash API (https://unsplash.com/developers) - powers
// scripts/fetch_recipe_images.php, which sources real, properly-licensed
// food photos for recipes with no image_url yet (the alternative is
// hand-picking/hotlinking from wherever, which this app has already been
// burned by once - see the landing page hero photo's own history). Leave
// blank to skip that script entirely; nothing else in the app reads this.
// Free tier: register an app at unsplash.com/oauth/applications, use its
// "Access Key" (not the secret) here. Same nt_env()-first pattern as
// DB_HOST above, for hosts that inject it as an environment variable.
define('UNSPLASH_ACCESS_KEY', nt_env('UNSPLASH_ACCESS_KEY') ?: '');

// Optional "Sign in with Google" / "Sign in with Facebook" on the login
// and sign-up pages (includes/oauth.php + oauth_*.php). Same pattern as
// GEMINI_API_KEY above: leave either pair blank to disable that one
// button - it shows greyed out rather than erroring when clicked - so
// there's nothing to configure to keep using plain email/password.
//   Google:   console.cloud.google.com/apis/credentials -> Create
//             credentials -> OAuth client ID -> Web application. Add
//             {your domain}/oauth_google_callback.php as an authorized
//             redirect URI.
//   Facebook: developers.facebook.com/apps -> create an app -> add the
//             "Facebook Login" product. Add
//             {your domain}/oauth_facebook_callback.php as a valid OAuth
//             redirect URI, and set the app to Live (not just Development
//             mode) before real users can use it.
// Signing in this way finds or creates an account by the email address
// the provider hands back - only ever one it has itself confirmed, never
// an unverified one (see the callbacks) - the same account a plain
// email/password sign-up would use, matched first by (oauth_provider,
// oauth_id) and falling back to email so a second provider can link onto
// an account the first already created.
//
// Every value below can come from a real environment variable instead of
// being edited into this file - same nt_env()-first pattern DB_HOST etc.
// already use up top, useful for a host (like Railway) that injects
// secrets as env vars rather than letting you edit files directly. Left
// blank (the default either way), the matching sign-in button just shows
// disabled - never an error.
define('GOOGLE_CLIENT_ID', nt_env('GOOGLE_OAUTH_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', nt_env('GOOGLE_OAUTH_CLIENT_SECRET') ?: '');
define('FACEBOOK_APP_ID', nt_env('FACEBOOK_OAUTH_CLIENT_ID') ?: '');
define('FACEBOOK_APP_SECRET', nt_env('FACEBOOK_OAUTH_CLIENT_SECRET') ?: '');

// Apple "Sign in with Apple" - developer.apple.com/sign-in-with-apple.
// Unlike Google/Facebook, Apple has no static client secret: APPLE_OAUTH_*
// below are used to *generate* one (a short-lived signed JWT) on every
// request - see apple_oauth_client_secret() in includes/oauth.php.
//   APPLE_OAUTH_CLIENT_ID   - the Services ID you create for this website
//                             (e.g. "com.nutritale.web"), NOT your app's
//                             bundle ID.
//   APPLE_OAUTH_TEAM_ID     - your 10-character Apple Developer Team ID.
//   APPLE_OAUTH_KEY_ID      - the 10-character ID of the Sign in with
//                             Apple *key* you create under Certificates,
//                             Identifiers & Profiles -> Keys.
//   APPLE_OAUTH_PRIVATE_KEY - the full contents of the .p8 file Apple
//                             gives you when you create that key
//                             (downloadable exactly once) - the
//                             "-----BEGIN PRIVATE KEY-----" PEM block,
//                             newlines intact. Almost always set via an
//                             environment variable rather than pasted
//                             here, since it's a multi-line secret.
define('APPLE_OAUTH_CLIENT_ID', nt_env('APPLE_OAUTH_CLIENT_ID') ?: '');
define('APPLE_OAUTH_TEAM_ID', nt_env('APPLE_OAUTH_TEAM_ID') ?: '');
define('APPLE_OAUTH_KEY_ID', nt_env('APPLE_OAUTH_KEY_ID') ?: '');
define('APPLE_OAUTH_PRIVATE_KEY', nt_env('APPLE_OAUTH_PRIVATE_KEY') ?: '');

// AI pantry-suggestion usage caps, all enforced in pantry.php via
// includes/ai_cache.php's ai_generations-backed counters - see
// CONTINUE.md §2.4/§2.20 for the full history (this replaced an earlier
// lifetime "3 free trials ever" counter, users.pantry_free_uses_used,
// which had no daily or time-window concept at all).
//
//   AI_PANTRY_CACHE_DAYS — an identical request (same pantry, diet prefs
//   and allergens - see ai_pantry_hash() in includes/ai_pantry.php) within
//   this many days is served from ai_generations instead of calling
//   Gemini again. Costs nobody a daily-cap count, since nothing new was
//   generated.
//
//   AI_PANTRY_FREE_DAILY_CAP — free (non-premium, non-admin) accounts,
//   from the day they sign up, no separate elevated trial period.
//
//   AI_PANTRY_DAILY_CAP — premium AND admin accounts, not just premium:
//   both were equally unmetered before this work started, and "admin"
//   isn't the same guarantee as "trusted operator" on every deployment.
//
//   AI_PANTRY_COOLDOWN_SECONDS — minimum gap between two real Gemini
//   calls from the same user, regardless of tier. Only gates an actual
//   new generation, never a cache hit (see pantry.php's ai_suggest
//   handler - the cache lookup runs first).
//
// All three counters count Gemini calls (attempts), not button presses -
// a retry that regenerates because of an allergen violation still costs
// one. See includes/ai_cache.php.
define('AI_PANTRY_CACHE_DAYS', 3);
define('AI_PANTRY_FREE_DAILY_CAP', 2);
define('AI_PANTRY_FREE_WINDOW_DAYS', 3); // free accounts: AI_PANTRY_FREE_DAILY_CAP generations per rolling N days
define('AI_PANTRY_DAILY_CAP', 20);
define('AI_PANTRY_COOLDOWN_SECONDS', 30);

// Free vs Premium recipe access. Free accounts browse a fixed set of
// FREE_RECIPE_LIMIT free-tier recipes (free_recipe_ids() in
// includes/functions_core.php - enforced server-side in browse, the recipe
// page, the planner and the pantry matcher) and see at most
// FREE_PANTRY_MATCH_LIMIT matches on "What Can I Make?". Premium sees every
// recipe, every match, plus up to EXTERNAL_RECIPE_LIMIT extra matches from
// TheMealDB (includes/external_recipes.php).
define('FREE_RECIPE_LIMIT', 20);
define('FREE_PANTRY_MATCH_LIMIT', 3);
define('EXTERNAL_RECIPE_LIMIT', 10);

// TheMealDB (themealdb.com) - the external recipe source above. '1' is
// their public test key: fine for development, but their terms ask apps
// released publicly to become a supporter and use the upgraded key they
// email you - set MEALDB_API_KEY (config.local.php / env) to that before
// launch. Set it to '' to switch the internet recipes off entirely.
define('MEALDB_API_KEY', nt_env('MEALDB_API_KEY') !== false ? nt_env('MEALDB_API_KEY') : '1');

// Outgoing email (password resets, "someone rated your recipe" notices).
// Leave SMTP_HOST blank to fall back to PHP's mail(), which many hosts
// block or silently drop (see DEPLOYMENT.md) - fine for local development,
// not reliable for anything a real user needs to actually receive. Any
// real SMTP provider works here: your host's own mail server (check its
// control panel for the hostname/port), a transactional email service
// (SendGrid, Mailgun, Postmark, Brevo - most have a free tier), or a
// personal account's SMTP with an app password (e.g. Gmail:
// smtp.gmail.com, port 587, an "app password" - not your normal login
// password - from your Google account's security settings). Never commit
// real values here.
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls'); // 'tls', 'ssl', or '' for an unencrypted connection
define('SMTP_FROM_EMAIL', 'no-reply@example.com');
define('SMTP_FROM_NAME', APP_NAME);

// Google Analytics 4 (analytics.google.com) - the gtag.js snippet is
// wired into every page's <head> already (see ga4_script() in
// includes/functions_core.php), keyed off this one constant. 'G-XXXXXXXXXX' is
// a placeholder, not a real property - GA silently ignores an invalid
// measurement ID (no page-breaking error, just no data received), so
// leaving it as-is is safe until a real GA4 property exists. Replace with
// the real Measurement ID (Admin > Data Streams > your stream, in your
// GA4 property) - no other code change needed. Leave entirely blank
// ('') to remove the script from every page instead, same convention as
// GEMINI_API_KEY/UNSPLASH_ACCESS_KEY above.
define('GA_MEASUREMENT_ID', 'G-6GHNGG305J');

// Set to true only while actively debugging locally — it prints full PHP
// errors (file paths, stack traces, sometimes query fragments) straight
// into the browser, which is a real information leak on a live site.
// Leave false in production; check your host's PHP error log instead
// (errors are always logged regardless of this setting).
define('APP_DEBUG', false);

// True only for a genuinely HTTPS request - same check app_base_url()
// (includes/functions_core.php) already used for building PayFast return URLs,
// defined here instead so the session cookie setup below (which runs
// before functions_core.php is ever loaded) can use it too; app_base_url()
// now calls this instead of duplicating the check. Doesn't account for a
// TLS-terminating reverse proxy (no X-Forwarded-Proto handling) - neither
// did the pre-existing check, and DEPLOYMENT.md's two recommended
// production paths (shared hosting, a plain VPS) don't sit behind one;
// Railway, which does, is already marked not-for-production there for
// unrelated reasons (see CONTINUE.md §2.5).
function is_https_request(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

// How long a login should persist with no activity at all - used for both
// the base session cookie below and the DB session handler's own cleanup
// (session.gc_maxlifetime, set further down). Matches login.php's
// REMEMBER_ME_DAYS: previously that 30-day figure only applied if someone
// actually ticked "Remember me", and everyone else got a cookie that died
// the moment their browser fully closed (lifetime => 0) - which on a phone,
// especially a PWA added to the home screen, is effectively "every time you
// reopen the app". Nobody expects a recipe app to need a checkbox ticked to
// stay logged in, so that's now the default for everyone; the checkbox in
// login.php still works, it just no longer does anything meaningfully
// different from not checking it.
define('SESSION_LIFETIME_DAYS', 30);

// Store sessions in MySQL rather than the host's local disk - see
// includes/db_session_handler.php's header comment for the full reasoning
// (short version: InfinityFree and other free shared hosts sweep session
// temp files on their own schedule regardless of what this app asks for,
// which was the actual cause of users getting silently logged out - not a
// multi-server/scaling problem, so no Redis or similar needed). Must happen
// before session_start() below, and can't use db()/config/db_conn.php's
// connection - that file requires this one, not the other way around, so
// db() doesn't exist yet at this point in the request.
require_once __DIR__ . '/../includes/db_session_handler.php';
session_set_save_handler(
    new DbSessionHandler(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS),
    true
);
// PHP's own garbage collector (not just our handler's gc() method, though
// that reads this too) should only consider a session old enough to delete
// on the same schedule the cookie itself is good for - otherwise a session
// could be cleaned out of the database while the browser still presents a
// cookie claiming it's valid for weeks longer.
ini_set('session.gc_maxlifetime', (string)(SESSION_LIFETIME_DAYS * 86400));

// Session cookie hardening: HttpOnly (JavaScript can never read it via
// document.cookie - the whole point), Secure (never sent over plain HTTP,
// skipped automatically on this local dev server since is_https_request()
// is false there), and SameSite. Explicit here rather than left to
// whatever php.ini a given host happens to ship with - the previous
// behavior for all three flags. SameSite=Lax, not Strict: verified
// directly against this app's own code, not assumed - Strict withholds
// the cookie on the *cross-site top-level redirect back into the app*
// that both PayFast (checkout_return.php/premium_return.php, which call
// require_login()) and every OAuth provider (oauth_verify_state() reads
// $_SESSION['oauth_state_...'], set right before redirecting out) depend
// on - Strict would silently break real payment confirmations and every
// Google/Facebook/Apple sign-in, every time. Lax still blocks the classic
// cross-site POST CSRF vector (the thing SameSite actually exists to stop)
// while allowing a top-level GET redirect to carry the cookie, which is
// why it's the OWASP/browser-vendor-recommended default for a session
// cookie specifically, not a weaker fallback.
//
// lifetime is now SESSION_LIFETIME_DAYS, not 0 (a true "dies when the
// browser fully closes" session cookie) - see that constant's comment
// above for why 0 was actually the bulk of this bug, independent of the
// disk-storage issue the DB handler above fixes.
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME_DAYS * 86400,
    'path' => '/',
    'domain' => '',
    'secure' => is_https_request(),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');
