# NutriTale — agent job queue (created 2026-11-13)

Source: `nutritale/TASKS.md` (all 47 items open on this date) plus the owner's decisions of 10–11 Nov 2026.
Status values: `todo`, `doing`, `done`, `blocked` (waiting on something), `owner` (only a person can do it).
Work branch: `claude/agent-work`. Never push to `claude/app-continuation-s4hyzj`.

## Owner-only (agents must not attempt)

| ID | Task | Status |
|---|---|---|
| O1 | Full database export from the InfinityFree control panel, stored off the host | owner |
| O2 | Download `config/config.local.php`, `assets/img/avatars/`, `data/` from the host | owner |
| O3 | Rotate the exposed keys (Gemini, database password, Unsplash) | owner |
| O4 | Decide domain, VPS provider and budget; VPS and store accounts in the client's name | owner |
| O5 | Approve the consent wording and name the Information Officer | owner |
| O6 | Have a cook or dietitian check the 13 recipes with no allergen tags and the safety replies | owner |
| O7 | Decide how Premium is sold in the store apps | owner |
| O8 | Open the FTP deploy workflow yourself and check it does not upload `.claude/`, `CLAUDE.md` or the `.md` docs to the live site (agents may not read workflows) | owner |

## Agent jobs

| ID | Job | Done when | Depends on | Status |
|---|---|---|---|---|
| J01 | Tag the current commit `pre-api-baseline` (local tag, report the hash) | Tag exists | — | todo |
| J02 | Admin-only diagnostics page: one small outbound request each to Gemini, Unsplash, TheMealDB; show HTTP status and time; no keys shown | Page works for admins only, 403 for others, test for the access check | — | todo |
| J03 | Create `nutritale/services/` with a README stating the rules (ids and arrays in, arrays out) | Folder and README exist | — | todo |
| J04 | Recipes: move listing/search/filter and detail + paywall rules out of `index.php` / `recipe.php` into `services/recipes.php` | Pages unchanged in the browser, new test passes | J03 | todo |
| J05 | Pantry: move add/remove/update/expiry out of `pantry.php` into `services/pantry.php` | Same | J03 | todo |
| J06 | Favourites and ratings into `services/` | Same | J03 | todo |
| J07 | Profile, diet preferences, allergens into `services/` | Same | J03 | todo |
| J08 | Planner and shopping list into `services/` | Same | J03 | todo |
| J09 | Auth: login/registration/lockout checks as functions (pages still own the session) | Same | J03 | todo |
| J10 | Replace hard-coded `nutritale.freedev.app` (print footer, demo-account host check) with configurable `APP_BASE_URL` | No hard-coded host left in PHP, test passes | — | todo |
| J11 | `api_tokens` table migration + bearer-token auth (hashed tokens, per-device revoke) | Migration runs locally, tests for valid, expired, revoked token | J09 | todo |
| J12 | `/api/v1/` router, JSON error format, input validation, rate limits | Local only; test for 401, 404, 429 | J11 | todo |
| J13 | API endpoints in the order of `AI_ARCHITECTURE.md` section 3, one endpoint group per commit, AI ideas last | Each has tests: auth required, other users' data refused, Premium rules enforced | J04–J09, J12 | todo |
| J14 | `API.md` reference | Every endpoint listed with an example | J13 | todo |
| J15 | Wrap the provider call behind `ai_generate_meals()` in one file so the model can be swapped | Existing `ai_pantry_test.php` still passes, new test with a stub generator | — | todo |
| J16 | Assistant endpoint `POST /api/v1/ai/chat`: takes page name and question, server adds profile, runs tools (`get_recipe`, `search_recipes`, `find_stores`, `nutrition_lookup`), returns JSON `{reply, tool, recipe_id}`; caps, cooldown and allergen filter apply | Local only; tests with a stub model, including the four safety questions | J12, J15 | todo |
| J17 | Crisis backstop in code: fixed reply with the SADAG Suicide Crisis Helpline 0800 567 567 (checked on sadag.org/help, 2026-11-11) when a message matches self-harm phrases, before any model call | Test proves the model is not called for those messages | J16 | todo |
| J18 | USDA FoodData Central lookup service; key read with `nt_env()`, never sent to the browser; cached | Test with a stubbed HTTP response | J03 | todo |
| J19 | Consent screen with four separate, unticked boxes; nothing stored before consent | Blocked until O5 | O5 | blocked |
| J20 | Cycle and mood features | Blocked until J19 and O6 | J19, O6 | blocked |

## Not for agents yet

- VPS setup, migration and cut-over (TASKS.md phases 3–4): needs O4.
- Mobile app (phase 5): needs the API live on a VPS.
- Swapping Gemini for the self-trained model: only after it passes the agreed test.
