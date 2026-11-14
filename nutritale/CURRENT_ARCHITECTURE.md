# NutriTale — Current Architecture (audit of 2026-11-10)

Audit only. Nothing was migrated or rebuilt. Every statement below was read
from the code on branch `claude/app-continuation-s4hyzj`; items marked
**UNVERIFIED** could not be checked from the repository and need a test on
the live host.

## 1. Stack

| Layer | What it is | Where |
|---|---|---|
| Language | PHP 8.x, no framework, no Composer (PHPMailer is vendored in `includes/PHPMailer/`) | whole app |
| Rendering | Server-rendered pages: one `.php` file per screen (~75 files in the web root) | `index.php`, `pantry.php`, `recipe.php`, ... |
| Front end | Plain HTML, one stylesheet (`assets/css/style.css`), small vanilla JS files, PWA (`manifest.json`, `sw.js`) | `assets/` |
| Database | MySQL / MariaDB through PDO, prepared statements, utf8mb4 | `config/db_conn.php` (`db()`) |
| Schema | `sql/schema.sql` (base tables) + `sql/db_migrations.php` (named migrations, tracked in `schema_migrations`), applied automatically on the first request after a deploy | `includes/schema_upgrade.php` |
| Config | Constants in `config/config.php`; secrets come from `nt_env()`, which reads the server-only `config/config.local.php` (git-ignored and excluded from deploy) | `config/` |
| Tests | 11 plain-PHP test scripts, no test framework | `tests/` |
| Deploy | GitHub Action uploads `nutritale/` by FTP to InfinityFree `htdocs/` on every push to the deploy branch | `.github/workflows/deploy-nutritale.yml` |

## 2. Authentication

- **Email + password**: `password_hash()` / `password_verify()` (`register.php`, `login.php`). Lockout via `users.failed_attempts` / `locked_until` and a `login_attempts` table. Cloudflare Turnstile on login and register (`includes/turnstile.php`).
- **Social sign-in**: Google, Facebook, Apple (`oauth_*.php`, `includes/oauth.php`). Each is off until its keys are set.
- **Sessions**: PHP sessions stored in MySQL (`nutri_sessions`, `includes/db_session_handler.php`), 30-day cookie, HttpOnly, Secure on HTTPS, SameSite=Lax.
- **CSRF**: per-session token checked on every POST form (`csrf_token()` / `csrf_check()` in `includes/functions_core.php`).
- **Roles**: `users.is_admin`, `is_vendor`, `is_premium_member`. Premium/tier checks go through helpers such as `user_has_full_library()`.
- **No token auth exists.** There are no API keys, bearer tokens or JWTs. Everything assumes a browser cookie. This is the main gap for mobile apps.

## 3. How data is stored

| Data | Storage |
|---|---|
| Recipes | `recipes` (string id, title, description, `image_url`, meal type, cuisine, difficulty, time, servings, premium flag, price, `created_by`) |
| Ingredients / steps | `recipe_ingredients` (name, quantity, unit, display quantity, category, order), `recipe_instructions` (step number, text) |
| Diet / allergens | `recipe_diet_tags`, `recipe_allergens`; per user: `user_diet_preferences`, `user_allergens` |
| Nutrition | Columns on `recipes`: `calories`, `protein_g`, `carbs_g`, `fat_g`, `fiber_g` (per serving, whole numbers). User targets in `user_goals`. There is **no per-day intake or wellness log**. |
| Pantry | `user_pantry_items` (user id + free-text `ingredient_name`, plus `expires_on` from a later migration). Matching uses `ingredients` / `ingredient_aliases` and `includes/ingredient_matching.php`. |
| Favourites, ratings, views | `favorites`, `recipe_ratings`, `recipe_views` |
| Meal planner / shopping | `meal_plan_items`, `meal_plan_templates(+_items)`, `shopping_list_checks` (the list itself is computed from the plan) |
| Payments | `premium_subscriptions`, `recipe_purchases`, `ingredient_listings`, `ingredient_orders`, `vendor_payouts` (PayFast, still sandbox credentials) |
| AI | `ai_generations` (per-user log + cache of generated ideas), `external_api_cache` (TheMealDB responses) |
| Recipe images | **Not stored on the server.** `recipes.image_url` holds an external URL (Unsplash / Wikimedia). Credits live in `data/image_attribution.json`. AI-idea photos are looked up on Unsplash and cached in `data/idea_photos.json`. |
| Uploaded files | Only profile avatars: `assets/img/avatars/` on the server disk (`includes/avatars.php`). Excluded from deploy so uploads are not overwritten. |

## 4. Hosting-specific paths, URLs and configuration

- Live URL: `https://nutritale.freedev.app` (InfinityFree subdomain). Web root on the host: `/htdocs/`.
- `app_base_url()` derives scheme + host + folder from the request, so most links survive a domain change with no edit.
- Hard-coded host names that must change on a domain move: `recipe.php` print footer (`nutritale.freedev.app`), `includes/demo_account.php` (host check), a comment in `.htaccess`. OAuth redirect URLs and the PayFast notify URL are registered **at the providers** and must be updated there. Turnstile keys are bound to the hostname in the Cloudflare dashboard.
- `.htaccess` (Apache only): blocks `config/`, `sql/`, `scripts/`, `data/`, `.md`/`.sql`/`.log`; sets security headers; rewrites `sitemap.xml`. A VPS on nginx needs the same rules rewritten as nginx config.
- Server-only files never in git: `config/config.local.php`, `assets/img/avatars/*`, `data/idea_photos.json`.
- Deploy secrets (`FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`) live in GitHub Actions secrets.

## 5. InfinityFree restrictions that affect the plan

Confirmed in code comments, `CONTINUE.md`, or InfinityFree's own documentation:

1. **Browser security check blocks non-browser clients.** Every request without the `__test` cookie gets an HTML/JavaScript challenge page instead of the real response. InfinityFree states this is required on free accounts and that it blocks mobile apps and API clients. **A native Android/iOS app cannot call an API hosted on InfinityFree.** This also applies to any server-to-server callback.
2. **PayFast payment notifications are a server-to-server callback** (`payfast_notify.php`). **UNVERIFIED** whether they get through the security check on this host; with sandbox keys this has not been proven live. Must be tested before real payments.
3. `curl_multi_*` is disabled (`includes/external_recipes.php` falls back to sequential calls).
4. `putenv()` may be disabled (reason `nt_env()` parses `config.local.php` itself).
5. Session temp files are swept by the host (reason sessions are in MySQL).
6. Some outbound server requests are blocked: the barcode lookup has a client-side fallback because the server could not reach Open Food Facts (`assets/js/pantry-scan.js`). **UNVERIFIED** whether outbound calls to Gemini and Unsplash succeed on the live host today.
7. No SSH, no shell, FTP only; no real cron (premium expiry is enforced lazily on page load); the database is reachable only from the host itself (no remote MySQL), so backups must be taken through the control panel or a PHP export.
8. `sw.js` is served with a 30-day cache header by the host (worked around in `sw.js` v7 and `.htaccess`).

## 6. What future mobile apps can reuse

**Reusable as-is (already separate from page rendering):**

- `includes/ingredient_matching.php` — pantry-to-recipe matching, aliases, typo correction
- `includes/ai_pantry.php`, `includes/ai_cache.php` — AI prompt, allergen filtering, caps, caching
- `includes/allergens.php`, `includes/pantry_expiry.php`, `includes/external_recipes.php`
- `includes/store_links.php` — Pick n Pay link builders (pure functions). Uber Eats was removed for good on 2026-11-14
- `includes/payfast_gateway.php`, `includes/vendor_payouts.php`, `includes/account_deletion.php`
- The whole database schema and migration system
- Existing tests for all of the above

**Not reusable without work (logic lives inside page files):**

- Recipe listing/search/filter queries (`index.php`), recipe detail assembly and paywall rules (`recipe.php`)
- Pantry add/remove/update handlers (`pantry.php`), favourites (`favorite_toggle.php`), planner and shopping list (`planner.php`, `shopping_list.php`)
- Login, registration, profile and preference updates
- These handlers read `$_POST`, write to `$_SESSION`, then redirect. They return HTML, not data.

**Existing JSON endpoints:** only `barcode_lookup.php` and `tour_done.php`. Both use the session cookie.

## 7. Sources

- [InfinityFree: Why won't my mobile app connect to my website?](https://forum.infinityfree.com/t/why-wont-my-mobile-app-connect-to-my-website/115197)
- [InfinityFree: Browser Security System - Features and Limitations](https://forum.infinityfree.com/t/browser-security-system-features-and-limitations/49353)
