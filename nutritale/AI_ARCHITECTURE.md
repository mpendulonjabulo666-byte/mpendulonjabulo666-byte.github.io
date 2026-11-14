# NutriTale — AI and Shared Backend Architecture (proposal, 2026-11-10)

Proposal only. Nothing here is built yet. See `CURRENT_ARCHITECTURE.md` for
the audit and `TASKS.md` for the phased work list.

## 1. AI as it works today

- Provider: Google Gemini, hosted. Model `gemini-2.5-flash` (`GEMINI_MODEL` in `config/config.php`).
- One feature: pantry meal ideas (`pantry.php` → `gemini_pantry_ideas()` in `includes/ai_pantry.php`).
- The call is made by PHP on the server with `GEMINI_API_KEY` from `config.local.php`. The key never reaches the browser.
- Output is forced to a JSON schema (title, description, uses_from_pantry, shopping_list), then filtered server-side for the user's allergens; ideas containing an allergen are discarded.
- Cost controls: per-user cache by pantry hash (`AI_PANTRY_CACHE_DAYS` = 3), caps (free: 2 per 3 days, Premium: 20 per day), 30-second cooldown, admin kill switch (`platform_settings.enable_ai_matching`). All logged in `ai_generations`.
- Photos for ideas: Unsplash lookup, server-side, cached (`includes/idea_photo.php`).

## 2. AI rules going forward

1. **Hosted provider only.** Keep Gemini. The VPS is not assumed to run or train a model.
2. **Keys stay server-side.** Mobile apps call the NutriTale API; the API calls the provider. No AI key is ever shipped inside an app or a web page.
3. **One provider interface.** Wrap the provider call behind a single function (`ai_generate_meals(prompt): array`) so the model or vendor can be changed in one file. `gemini_generate_meals()` already has this shape and `gemini_pantry_ideas()` already accepts a replacement generator for tests.
4. **Same guardrails for every client.** Caps, cooldown, cache and the allergen filter run in the shared service, so web and mobile cannot bypass them.
5. **Test outbound calls on the host before building on them.** Add an admin-only diagnostics check that makes one tiny request each to Gemini, Unsplash and TheMealDB from the live server and reports HTTP status and time. Run it on InfinityFree now and again on the VPS before cut-over.
6. **No sensitive wellness logging yet.** No mood, weight, symptom, medical or daily-intake logging until four things are written down and approved: consent wording, who can access the data, how long it is kept, and safety rules (what the AI may and may not say). `user_goals` (calorie/macro targets) is the only health-adjacent data today and stays as it is.
7. **Nothing fake.** If the provider fails, the API returns an error; it never returns invented meals.

## 3. Shared backend: target shape

```
                 ┌──────────────── one server (VPS later) ────────────────┐
 Website pages ──►  PHP pages (HTML)  ─┐                                   │
 (browser cookie)                      ├─►  services/  ─►  MySQL           │
 Android / iOS ──►  /api/v1/ (JSON)  ──┘   (business       Gemini (hosted) │
 (bearer token)                             logic)         Unsplash, etc.  │
                 └────────────────────────────────────────────────────────┘
```

- **`services/`** (new folder): plain PHP functions that take ids and arrays and return arrays. No `$_POST`, no `$_SESSION`, no HTML, no redirects. Logic is moved here out of the page files one feature at a time. The existing `includes/*.php` logic files already follow this rule and move across unchanged.
- **Pages** keep rendering HTML but call the services. The website keeps working at every step because each move is one feature and is covered by a test.
- **`/api/v1/`** (new folder): thin JSON endpoints that authenticate, validate input, call the same service, and return JSON. Versioned in the path so old app versions keep working.
- **Same database.** No second copy of the data, no sync job.

### API authentication

- Website: unchanged (session cookie + CSRF).
- Apps: `POST /api/v1/auth/login` returns an opaque random token; only its SHA-256 hash is stored in a new `api_tokens` table (user id, hash, device label, created, last used, expires, revoked). Sent as `Authorization: Bearer ...`. Revocable per device. Login keeps the existing lockout rules.
- Google / Apple sign-in from the apps: the app sends the provider's ID token; the server verifies it and issues a NutriTale token. Apple sign-in is required on iOS if Google sign-in is offered.

### First endpoints (read-mostly, lowest risk first)

| Endpoint | Service it calls |
|---|---|
| `GET /recipes`, `GET /recipes/{id}` | recipe listing/detail + paywall rules |
| `GET/POST/DELETE /pantry` | pantry items + expiry |
| `GET /pantry/matches` | ingredient matching |
| `POST /pantry/ideas` | AI ideas (caps, cache, allergen filter) |
| `GET/POST/DELETE /favorites` | favourites |
| `GET/PUT /me`, `/me/preferences` | profile, diet, allergens |
| `GET/PUT /planner`, `GET /shopping-list` | planner and computed list |
| `DELETE /me` | account deletion (store requirement) |

### Rules for the API

- **Base URL is configurable.** Server: `APP_BASE_URL` / `API_BASE_URL` read through `nt_env()`. Apps: one build-time setting (Expo `extra` / env), never a hard-coded host in screens. Staging and production differ only by that value.
- **Retailer links stay external redirects.** The API returns the Pick n Pay URLs built by `includes/store_links.php`; the app opens them in the system browser. No ordering, login or scraping through NutriTale.
- **Payments.** Web keeps PayFast. For store apps, digital subscriptions sold inside the app must use Apple / Google billing; simplest compliant first release is to have the apps read Premium status only and not sell it in-app. Decide before the first store submission.
- **Rate limiting** per token and per IP on login and AI endpoints.
- **Images.** Recipe images are already external URLs, so apps load them directly. Avatars move behind a configurable `UPLOADS_BASE_URL`.

## 4. Why the API cannot go live on InfinityFree

InfinityFree's required browser security check answers any client that cannot
run JavaScript with a challenge page. Native apps cannot pass it. So:

- Service extraction (the `services/` work) **can** start now on InfinityFree: it changes no URLs and benefits the website.
- The `/api/v1/` endpoints can be written and tested locally and on staging, but **can only serve real apps after the move to a VPS** (or any paid host without the check).
- Apps built before then should be developed against the staging API.

## 5. Environments

| Environment | Purpose | Host |
|---|---|---|
| Local | Development and tests | `php -S` + MariaDB (as used today) |
| Staging | Test API + apps with copied, anonymised data | Second vhost / subdomain on the VPS, own database, own `config.local.php` |
| Production | Live site, then live API | InfinityFree now; VPS after cut-over |
