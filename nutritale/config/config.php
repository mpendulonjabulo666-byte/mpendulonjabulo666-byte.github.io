<?php
// Fill these in with your own database's connection details before
// running setup.php. If you're on shared hosting, your host's control
// panel (e.g. cPanel > MySQL Databases) will give you these values.
// On a platform that injects DB credentials as environment variables
// (e.g. Railway's MySQL plugin, which sets MYSQLHOST/MYSQLPORT/etc.),
// those are picked up automatically — no edit needed here.
define('DB_HOST', getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost'));
define('DB_PORT', getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306'));
define('DB_NAME', getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'nutritale'));
define('DB_USER', getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
define('DB_PASS', getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: ''));

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
define('PANTRY_FREE_USES', 3);

// Google Gemini API (https://aistudio.google.com/apikey) — powers the
// "Get AI ideas" button on the pantry page (real AI-generated meal ideas
// + shopping list, on top of the always-free rule-based recipe matcher
// above it). Leave blank to disable the AI button entirely. Get a free
// key at aistudio.google.com and paste it here on your server — never
// commit a real key to git. GEMINI_MAX_OUTPUT_TOKENS caps how long each
// response is allowed to be, and every call also counts against the
// same PANTRY_FREE_USES trial limit as the rest of the pantry page, so
// usage (and cost) stays bounded per free user.
define('GEMINI_API_KEY', '');
define('GEMINI_MODEL', 'gemini-2.5-flash');
define('GEMINI_MAX_OUTPUT_TOKENS', 1500);

// Unsplash API (https://unsplash.com/developers) - powers
// scripts/fetch_recipe_images.php, which sources real, properly-licensed
// food photos for recipes with no image_url yet (the alternative is
// hand-picking/hotlinking from wherever, which this app has already been
// burned by once - see the landing page hero photo's own history). Leave
// blank to skip that script entirely; nothing else in the app reads this.
// Free tier: register an app at unsplash.com/oauth/applications, use its
// "Access Key" (not the secret) here. Same getenv()-first pattern as
// DB_HOST above, for hosts that inject it as an environment variable.
define('UNSPLASH_ACCESS_KEY', getenv('UNSPLASH_ACCESS_KEY') ?: '');

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
// being edited into this file - same getenv()-first pattern DB_HOST etc.
// already use up top, useful for a host (like Railway) that injects
// secrets as env vars rather than letting you edit files directly. Left
// blank (the default either way), the matching sign-in button just shows
// disabled - never an error.
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_OAUTH_CLIENT_SECRET') ?: '');
define('FACEBOOK_APP_ID', getenv('FACEBOOK_OAUTH_CLIENT_ID') ?: '');
define('FACEBOOK_APP_SECRET', getenv('FACEBOOK_OAUTH_CLIENT_SECRET') ?: '');

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
define('APPLE_OAUTH_CLIENT_ID', getenv('APPLE_OAUTH_CLIENT_ID') ?: '');
define('APPLE_OAUTH_TEAM_ID', getenv('APPLE_OAUTH_TEAM_ID') ?: '');
define('APPLE_OAUTH_KEY_ID', getenv('APPLE_OAUTH_KEY_ID') ?: '');
define('APPLE_OAUTH_PRIVATE_KEY', getenv('APPLE_OAUTH_PRIVATE_KEY') ?: '');

// Free users are already bounded by PANTRY_FREE_USES above, but premium
// and admin accounts skip that counter entirely (see pantry.php) and
// were previously unmetered - see CONTINUE.md §2.4. These two bound them:
//
//   AI_PANTRY_CACHE_DAYS — an identical request (same pantry, diet prefs
//   and allergens - see ai_pantry_hash() in includes/ai_pantry.php) within
//   this many days is served from ai_generations instead of calling
//   Gemini again. Costs nobody a trial use or a daily-cap count, since
//   nothing new was generated.
//
//   AI_PANTRY_DAILY_CAP — applied to premium AND admin accounts, not just
//   premium: both were equally unmetered before this, and "admin" isn't
//   the same guarantee as "trusted operator" on every deployment. Counts
//   Gemini calls (attempts), not button presses - a retry that regenerates
//   because of an allergen violation still costs one. See includes/ai_cache.php.
define('AI_PANTRY_CACHE_DAYS', 3);
define('AI_PANTRY_DAILY_CAP', 30);

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

// Set to true only while actively debugging locally — it prints full PHP
// errors (file paths, stack traces, sometimes query fragments) straight
// into the browser, which is a real information leak on a live site.
// Leave false in production; check your host's PHP error log instead
// (errors are always logged regardless of this setting).
define('APP_DEBUG', false);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');
