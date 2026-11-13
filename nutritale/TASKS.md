# NutriTale — Tasks (updated 2026-11-13)

Phased plan for a shared backend serving the website and future Android/iOS
apps. Rule for every phase: the live website and its data keep working, and
each step can be undone. See `CURRENT_ARCHITECTURE.md` and `AI_ARCHITECTURE.md`.

## Decisions needed from the owner (blocking)

- [ ] Domain name and VPS budget/provider (costs money; nothing in Phase 3+ can go live without it)
- [ ] How Premium is sold in the store apps: read-only status at first, or Apple/Google in-app billing
- [ ] Who owns the VPS, domain and store accounts (client or developer) — put it in the contract

## Phase 0 — Safety net (no code moves; do first)

- [ ] Take a full database export from the InfinityFree control panel; store it off the host; repeat weekly and before every phase
- [ ] Download a copy of `config/config.local.php`, `assets/img/avatars/` and `data/` from the host (these are not in git)
- [ ] Add an admin-only diagnostics check: one small outbound request each to Gemini, Unsplash, TheMealDB; show status and time. Run on the live host and record results in `CURRENT_ARCHITECTURE.md` section 5
- [ ] Test whether a server-to-server POST to `payfast_notify.php` passes InfinityFree's security check (PayFast sandbox ITN). Record the result
- [ ] Rotate the keys that were exposed earlier (Gemini, database password, Unsplash)
- [ ] Tag the current commit as `pre-api-baseline` for rollback

## Phase 1 — Separate business logic from pages (safe on InfinityFree)

One feature per commit; add or extend a test each time; no URL or visual change.

- [ ] Create `services/`; move nothing yet, agree naming
- [ ] Recipes: listing/search/filter and detail + paywall rules out of `index.php` / `recipe.php`
- [ ] Pantry: add/remove/update/expiry out of `pantry.php`
- [ ] Favourites, ratings
- [ ] Profile, diet preferences, allergens
- [ ] Planner and shopping list
- [ ] Auth: login/registration/lockout checks as functions (pages still own the session)
- [ ] Replace hard-coded `nutritale.freedev.app` (print footer, demo-account host check) with a configurable `APP_BASE_URL`

## Phase 2 — API v1, local and staging only

- [ ] `api_tokens` table (migration) + bearer-token auth, hashed tokens, per-device revoke
- [ ] `/api/v1/` router, JSON error format, input validation, rate limits
- [ ] Endpoints in the order listed in `AI_ARCHITECTURE.md` section 3 (recipes first, AI ideas last)
- [ ] API tests for every endpoint (auth required, other users' data refused, Premium rules enforced)
- [ ] Short API reference (`API.md`)

## Phase 3 — VPS and staging

- [ ] Provision VPS; PHP-FPM + nginx or Apache, MariaDB, HTTPS, firewall, automatic security updates
- [ ] Port `.htaccess` rules (blocked folders, headers, sitemap rewrite) to the VPS web server
- [ ] Staging site + staging database on a subdomain; own `config.local.php`
- [ ] Nightly database backup with `scripts/backup_db.sh` (cron), copied off the server; test one restore
- [ ] Run the outbound diagnostics on the VPS
- [ ] Deploy by SFTP/rsync from GitHub Actions to staging first, production on approval

## Phase 4 — Migration and cut-over

- [ ] Rehearsal: export InfinityFree database → import to staging → run tests → click through the site
- [ ] Lower DNS TTL a few days ahead
- [ ] Cut-over window: maintenance page on old site → final export → import → copy avatars and `data/` → switch DNS
- [ ] Update at the providers: OAuth redirect URLs (Google, Facebook, Apple), PayFast notify/return URLs, Turnstile hostname
- [ ] Redirect the old `freedev.app` address to the new domain
- [ ] **Rollback:** keep the InfinityFree site and database untouched for 14 days; to roll back, point DNS back and remove the maintenance page. Any data written on the new server after cut-over must be exported first

## Phase 5 — Mobile apps

- [ ] Miss Mvula opens Google Play and Apple Developer accounts (business, D-U-N-S)
- [ ] Expo app with configurable API base URL; Android first, then iPhone
- [ ] Sign in, recipes, pantry, AI ideas, favourites, planner, shopping list, account deletion
- [ ] Retailer links open in the system browser
- [ ] Store listings, privacy details, review account

## Retention: "use it up" and expiry reminders (added 2026-11-13; agent jobs J21–J26)

Why: people come back daily when the app saves them food and money. The expiry dates already exist (`includes/pantry_expiry.php`); nothing reaches the person when the app is closed.

Host facts that decide the order (`CURRENT_ARCHITECTURE.md` section 5, plus InfinityFree's own forum): free hosting has no cron and an outside scheduler such as cron-job.org cannot run PHP there; PHP `mail()` is heavily restricted, external SMTP is said to work but is unproven for this app; outbound calls are partly blocked. Web push needs the server to send a request to the browser's push service on a schedule, so it needs a scheduler.

Stage A — free host, no new website
- [ ] "Use it up tonight": one tap from the expiry banner shows recipes that use the soonest-expiring items (reuse `ingredient_matching.php` and `allergens.php`; allergen check stays in code)
- [ ] Admin-only email test page: sends one test email through an SMTP service (Brevo or Resend free tier) to the admin's own address and shows the result. Owner puts the key in `config.local.php`. Decides which Stage B route is possible

Stage B — reminders as a notification people allow
- [ ] Permission prompt: ask only after the person saves their first dated pantry item (never on first visit); plain-words reason; a switch in the profile to turn it off; subscriptions deleted with the account
- [ ] Privacy page and account deletion updated for stored push subscriptions; owner approves the wording
- [ ] Free-host route: the first page load each day sends reminders for items expiring tomorrow (works only if someone opens the app that day). Email is the fallback for people who decline notifications
- [ ] VPS route (needs Phase 3): real cron sends push and email at a set hour; `push` handler in `sw.js` (bump `CACHE_VERSION`), key pair in `config.local.php`, subscriptions table (migration), sender, test with a stubbed push service

## Own model: training and testing (added 2026-11-13)

The app keeps using Gemini until the model passes the agreed test. Training and testing only; nothing here changes the live app.
The generator, `train.jsonl`, `test.jsonl` and the Colab notebook are on the owner's device, not in git (this repo is public). Put them in a private repo before they are shared.

- [ ] Run `NutriTale_Train.ipynb` on a Colab T4 GPU and record the before and after scores and the safety answers (owner)
- [ ] Record the numbers in `AI_ARCHITECTURE.md`
- [ ] Shrink to Q4_K_M (last cells of the notebook) and save to Google Drive
- [ ] Test the file on the VPS with llama.cpp: speed in tokens per second and memory used
- [ ] More training examples: halal, diabetic-friendly, low-salt, gluten-free, keto; cycle examples only after the consent wording is approved
- [ ] Cook or dietitian reads the safety replies and the recipes with no allergen tags (O6)
- [ ] Owner agrees the pass bar against Gemini; real numbers go into the proposal to Miss Mvula

## Not to be built yet

- [ ] Wellness / health logging (mood, weight, symptoms, intake) — blocked until consent, access controls, retention and safety rules are written and approved
- [ ] Self-hosted or self-trained AI model
- [ ] In-app ordering from retailers

## Open items carried over

- [ ] Client details for legal pages: business name, address, support email
- [ ] PayFast real merchant details (still sandbox)
- [ ] Written contract (price or laptop, ownership, maintenance, who pays hosting/AI/store fees)
- [ ] Landing-page redesign / bowl intro — on hold pending owner decision
- [ ] Delete `t.php` from the host if still present
