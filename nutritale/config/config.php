<?php
// Fill these in with your own database's connection details before
// running install.php. If you're on shared hosting, your host's control
// panel (e.g. cPanel > MySQL Databases) will give you these values.
define('DB_HOST', 'localhost');
define('DB_NAME', 'nutritale');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'NutriTale');

// PayFast (https://www.payfast.co.za) payment settings for premium recipe
// purchases. The values below are PayFast's published SANDBOX test
// credentials — they only work against sandbox.payfast.co.za and never
// move real money. Before going live: create a real PayFast merchant
// account, replace PAYFAST_MERCHANT_ID/KEY with your own, set a
// passphrase to match what you configure in your PayFast account
// settings, and set PAYFAST_SANDBOX to false.
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
define('GEMINI_MAX_OUTPUT_TOKENS', 800);

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
