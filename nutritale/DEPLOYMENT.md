# Deploying NutriTale

This is source code only. GitHub Pages (which serves the rest of this
repository) cannot run PHP or MySQL, so `nutritale/` needs a real PHP
host to actually work. This guide covers the two realistic paths —
shared hosting and a VPS — plus the checklist for going live with real
payments.

## Requirements

- PHP 8.0+ with the `pdo_mysql`, `curl`, and `mbstring` extensions
  (standard on virtually every host; `curl` is required for the
  PayFast ITN confirmation call in `includes/payfast.php`)
- MySQL 5.7+ or MariaDB 10.3+
- HTTPS on your domain — required for PayFast (it will not send ITN
  callbacks to a plain `http://` URL) and for cookies to behave safely

## Path A — Shared hosting (cPanel, etc.)

Cheapest and simplest option; fine for real traffic at NutriTale's
scale.

1. **Create the database.** In cPanel → MySQL Databases, create a
   database and a user with full privileges on it. Note the host
   (usually `localhost`), database name, username, and password.
2. **Upload the code.** Upload the contents of `nutritale/` to a
   subdomain or subfolder (e.g. `public_html/nutritale/`) via the File
   Manager or FTP/SFTP.
3. **Configure.** Edit `config/config.php` on the server and set
   `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` to the values from step 1.
4. **Install.** Visit `https://yourdomain.com/nutritale/install.php` in
   a browser. It creates the tables and seeds starter recipes. Safe to
   re-run any time — it only creates what's missing.
5. **Register the first account.** The first account created via
   `register.php` is automatically an admin (see the `$isFirstUser`
   check in `register.php`).
6. **Delete or lock down `install.php`** once setup is done — it's
   harmless to leave (it no-ops when tables already exist) but there's
   no reason to leave a setup script world-reachable indefinitely.

The included `.htaccess` already blocks direct browser access to
`config/`, `sql/`, `scripts/`, `data/`, and dotfiles (`.git`, etc.) on
Apache — nothing extra to configure for this on shared hosting.

## Path B — VPS (DigitalOcean, Linode, Hetzner, etc.)

More control, marginally more setup. Use this if you outgrow shared
hosting or want your own box.

1. **Provision a small VPS** (1 vCPU / 1GB RAM is plenty to start) and
   point a domain's A record at its IP.
2. **Install the stack:**
   ```
   sudo apt update
   sudo apt install -y php php-mysql php-curl php-mbintl nginx mysql-server certbot python3-certbot-nginx
   ```
   (package names vary slightly by distro/PHP version)
3. **Set up MySQL:** `sudo mysql_secure_installation`, then create a
   database and user as in Path A step 1.
4. **Upload the code** (`git clone` this repo, or `scp`/`rsync`
   `nutritale/`) to e.g. `/var/www/nutritale`.
5. **Configure nginx** to serve `/var/www/nutritale` with PHP-FPM —
   any standard "nginx + PHP-FPM" vhost config works; NutriTale has no
   special rewrite rules (every page is a plain `.php` file hit
   directly, e.g. `recipe.php?id=...`).
6. **Get a certificate:** `sudo certbot --nginx -d yourdomain.com`
   (required — see HTTPS note above).
7. **Configure and install** as in Path A steps 3–5.

## Path C — Railway (for testing)

Not recommended for production (see the note at the end of this
section), but a good way to try the app on a real HTTPS URL — for
free/cheap, no domain purchase needed — before committing to shared
hosting or a VPS.

1. **Push this repo to GitHub** (already done, if you're reading this
   from the repo).
2. **Create a Railway project** at [railway.app](https://railway.app)
   and choose "Deploy from GitHub repo," pointing it at this repo with
   `nutritale/` as the root directory. Railway will find the
   `Dockerfile` in `nutritale/` and build from it automatically — it
   runs PHP's built-in server (`php -S`), the same way this app has
   been developed and tested throughout.
3. **Add a MySQL database:** in the same Railway project, "+ New" →
   "Database" → "Add MySQL." Railway provisions it and automatically
   sets `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, and
   `MYSQLPASSWORD` as environment variables — `config/config.php`
   already reads these automatically, so **no code edit is needed** for
   the database connection (this is different from Paths A/B, where you
   edit `config/config.php` directly).
4. **Generate a public domain:** on the web service (not the database),
   Settings → Networking → "Generate Domain." This gives you a real
   `https://something.up.railway.app` URL — HTTPS included, which
   PayFast's ITN and secure cookies both require.
5. **Install:** visit `https://your-app.up.railway.app/install.php` to
   create the tables and seed starter recipes, same as any other path.
6. **PayFast sandbox works as-is** here with zero changes — it's
   already the default in `config/config.php`.

**Why not for production:** Railway's free tier sleeps/limits usage and
its pricing scales with usage in a way that's harder to predict than a
flat-rate VPS or shared-hosting plan at NutriTale's scale — genuinely
fine for kicking the tyres, but Path A or B is the better home once
you're ready to actually launch.

## Going live with PayFast (real payments)

`config/config.php` ships pointed at PayFast's **sandbox** — safe by
default, no real money can move. Before accepting real payments:

1. Create a PayFast merchant account at
   [payfast.co.za](https://www.payfast.co.za) (South African bank
   account required — PayFast settles in ZAR).
2. In PayFast's dashboard, note your **Merchant ID** and **Merchant
   Key**, and set a **passphrase** under Settings → Integration.
3. In `config/config.php`, set:
   ```php
   define('PAYFAST_SANDBOX', false);
   define('PAYFAST_MERCHANT_ID', 'your-real-merchant-id');
   define('PAYFAST_MERCHANT_KEY', 'your-real-merchant-key');
   define('PAYFAST_PASSPHRASE', 'your-passphrase');
   ```
4. Confirm `app_base_url()` (in `includes/functions.php`) resolves to
   your real HTTPS domain — PayFast calls the `notify_url` it's given
   server-to-server, so this must be publicly reachable, not
   `localhost`.
5. Make one real low-value test purchase end-to-end (recipe purchase,
   Premium subscription, and an ingredient order) and confirm each
   lands as `paid` in `recipe_purchases` / `premium_subscriptions` /
   `ingredient_orders` and the ITN hit `payfast_notify.php` correctly.
6. `payfast_notify.php` currently trusts any POST that carries a valid
   signature and confirms with PayFast's validate endpoint — the
   comment at the top of that file flags that verifying the caller is
   in PayFast's published IP range is the one hardening step this
   build intentionally leaves for you before handling real money.

## Environment-specific values to double check

- `config/config.php`: `DB_*`, `PAYFAST_*`, `PLATFORM_COMMISSION_PCT`,
  `PREMIUM_MONTHLY_PRICE`, `PANTRY_FREE_USES`
- `config/config.php`: `APP_DEBUG` ships `false` (errors are logged, not
  shown to visitors) — that's the correct production setting. Only flip
  it to `true` temporarily while actively debugging on your own machine,
  never on a live site.
- `config/config.php`: `GEMINI_API_KEY` — optional. Powers the "Get AI
  ideas" button on the pantry page (real AI-generated meal ideas +
  shopping list, via Google's free-tier Gemini API). Leave blank to
  disable it — the rule-based recipe matcher on the same page has no
  dependency on this and always works. Get a free key at
  [aistudio.google.com/apikey](https://aistudio.google.com/apikey).
- Email sending: `send_notification_email()` in `includes/functions.php`
  currently uses PHP's `mail()`, which many hosts either block or
  silently drop — if recipe-review notification emails aren't
  arriving, that function is the first place to check (most hosts do
  better with SMTP via a real mail provider instead of `mail()`).

## After deploying

- Set `DB_PASS` back to a real secret (never commit real credentials —
  `config/config.php` is meant to be edited on the server, not in git)
- Consider a daily `mysqldump` cron job — there's no backup automation
  built in
- If any images under `assets/` come back 403 after upload, your SFTP/FTP
  client likely set restrictive permissions — run `find assets -type f
  -exec chmod 644 {} \;` and `find assets -type d -exec chmod 755 {} \;`
  on the server (git doesn't track full permission bits, only the
  executable flag, so this can't be baked into the repo)
- Everything else (recipes, planner, admin panel, vendor dashboard) is
  ready to use as soon as `install.php` has run
