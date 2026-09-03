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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('UTC');
