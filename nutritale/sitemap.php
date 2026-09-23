<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Served at /sitemap.xml via the .htaccess rewrite below, not as a static
// file - app_base_url() already derives the real scheme+host from the
// request (same function PayFast return URLs use, see CONTINUE.md §2.11),
// so this never needs a manual domain edit after deployment the way a
// hand-written sitemap.xml would.
//
// Public pages only, deliberately: everything else in this app sits
// behind require_login() (browse/pantry/planner/etc.) or is a POST-only
// action handler, neither of which belongs in a sitemap meant for search
// engines to crawl and index.
header('Content-Type: application/xml; charset=UTF-8');

$base = app_base_url();
$today = date('Y-m-d');

$pages = [
    ['loc' => 'landing.php', 'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => 'login.php', 'changefreq' => 'monthly', 'priority' => '0.3'],
    ['loc' => 'register.php', 'changefreq' => 'monthly', 'priority' => '0.5'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $page) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($base . $page['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>$today</lastmod>\n";
    echo "    <changefreq>{$page['changefreq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
