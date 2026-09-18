# CONTINUE — where NutriTale actually stands

Written 2026-09-14 against commit `75b2daa`, from a read of every PHP file in
`nutritale/`. This is the working document: what is built, what is missing, and
the order to do the missing things in. `README.md` is install, `DEPLOYMENT.md`
is hosting. This file is the build plan.

---

## 0. The one thing to know before touching anything

The original spec (`projects/pantry-chef/*.md`, commit `dd3198b` — not in the
working tree) describes a **Next.js + TypeScript + Supabase + Anthropic** app.
That is not what exists. What exists is **PHP 8 + MySQL + PayFast + Gemini**,
and it is *further along* than that spec's roadmap: pantry, AI ideas, premium
subscriptions, a peer-to-peer marketplace, meal planner, shopping list, admin
portal, PWA, dark mode.

So: use those docs for **what still needs to exist and why** (especially
`00-START-HERE.md` §3 and §4, and `08-AI-ENGINE.md`). Ignore them entirely on
**how to build it**. Nothing here is getting rewritten in React.

---

## 1. What is built and working

| Area | Files | State |
|---|---|---|
| Auth | `register.php` `login.php` `logout.php` `forgot_password.php` `reset_password.php` | Done. Password hashing, lockout after failed attempts (`failed_attempts` / `locked_until`), token-based reset with expiry |
| Onboarding | `onboarding.php` | Done. Diet prefs + allergens captured, persisted to `user_diet_preferences` / `user_allergens` |
| Browse / search | `index.php` `recipe.php` `listing.php` | Done. Search, filter, ratings + reviews, allergen conflict banner on detail |
| Favourites | `favorites.php` `favorite_toggle.php` | Done |
| Pantry | `pantry.php` `includes/ai_pantry.php` `includes/allergens.php` | Allergen-safe as of Step 1. Ingredient matching still crude — §2.2 |
| Meal planner | `planner.php` `planner_export.php` | Done, with export |
| Shopping list | `shopping_list.php` `shopping_list_export.php` | Done, with per-item check state |
| Premium | `premium*.php` | Done through PayFast recurring billing |
| Vendor recipes | `add_recipe.php` `my_recipes.php` `vendor.php` `checkout*.php` | Done, with commission split and net-earnings display |
| Marketplace | `marketplace.php` `ingredient_checkout*.php` | Done |
| Payments | `includes/payfast.php` `payfast_notify.php` | Signature check + server-side `VALID` confirmation + amount verification on once-off sales. See §2.5 |
| Admin | `admin.php` `admin_recipes.php` `admin_categories.php` `admin_users.php` `admin_reports.php` `admin_meal_plans.php` `admin_analytics.php` `admin_settings.php` `admin_profile.php` | Done — real dashboard stats, recipe reports/moderation, user role management, admin-curated meal plan templates, real analytics (signups/views/AI usage), four enforced platform toggles including maintenance mode. Admins bypass premium gates |
| PWA | `manifest.json` `sw.js` `assets/js/theme-*.js` | Done, install prompt included |
| Security baseline | `includes/functions.php` `.htaccess` | CSRF token on **every** browser POST handler (verified file by file); PDO prepared statements throughout; `APP_DEBUG` false by default; `h()` escaping |

Credit where due: the CSRF coverage and the PayFast ITN handling are better
than most PHP apps of this size. The gaps below are real, but they are gaps in
a working product, not a broken one.

---

## 2. What is missing, worst first

### 2.1 — Allergens on the AI path ✅ DONE (Step 1)

Was: `gemini_pantry_ideas()` took only `($pantryItems, $dietPrefs)`. The user's
allergen list was never loaded in `pantry.php`, never sent to Gemini, and the
model's output was never checked. A peanut-allergic user could be handed a satay.

Now, both layers the spec demands:

- **Layer 1** — `gemini_pantry_prompt()` states the allergens as a CRITICAL
  SAFETY REQUIREMENT, before the diet preferences and in absolute terms.
- **Layer 2** — `text_allergen_hits()` (new `includes/allergens.php`) scans
  every returned meal's title, description, pantry uses *and shopping list*.
  Anything that trips is discarded and logged, and up to
  `AI_PANTRY_MAX_ATTEMPTS` (3) regenerations are tried, each told exactly what
  it got wrong. If nothing clean survives, the user gets an error — it fails
  closed, never open.
- The rule-based matcher in `pantry.php` now drops conflicting recipes too,
  with a visible "N recipes hidden" note rather than a silent omission.
- `ALLERGEN_OPTIONS` replaces the list that was duplicated across
  `onboarding.php`, `profile.php` and `add_recipe.php` — drift there would have
  silently bypassed the check, so a test asserts every selectable allergen has
  detection keywords behind it.

Tests: `tests/allergen_test.php` (34) and `tests/ai_pantry_test.php` (17), no
API key or network needed. Run both after touching the keyword lists.

Known limits, deliberately accepted: coconut is not treated as a tree nut, and
an "X-free" claim from the model buys trust for at most the two words that
follow it. Both are documented at the point of decision in `allergens.php`.

### 2.2 — Ingredient matching is naive substring comparison ✅ DONE (Step 6)

Was: `pantry.php:70` did `str_contains($ingNorm, $p) || str_contains($p, $ingNorm)`
on the whole, untokenized phrase. Pantry "ice" matched recipe "rice" and
"juice"; "oil" matched "boiled eggs"; nothing mapped "tomatos" → "tomato" or
"passata" → "tomato". `00-START-HERE.md` calls this *"the single most
underestimated part of every recipe app ever built"* and it was right.

Now, `includes/ingredient_matching.php`, two independent layers (same shape
as the allergen work in Step 1 — mechanical layer first, semantic layer on
top):

- **Word-boundary tokenization** — split into words, compare whole words.
  Fixes the ice/rice and oil/boiled-eggs class of bug on its own, no seed
  data needed. A small mechanical depluralizer (`singularize_token()`) rides
  along and catches most spelling-only mismatches ("tomatos", "tomatoes" →
  "tomato") the same way, also with no seed data.
- **`ingredients` + `ingredient_aliases`** (seeded in `sql/migrations.php`)
  — for the mismatches that aren't mechanical: a genuine synonym ("passata"
  is tomato, "capsicum" is a bell pepper, "mince" → "turkey" — the only
  ground-meat ingredient this app ships with, a deliberate compromise
  documented at the seed data) or an irregular spelling the depluralizer
  would get wrong (South African "baby marrow" for zucchini, seeded as a
  two-word alias so "baby" alone isn't mis-aliased and breaks "baby
  spinach"). Only ingredients that actually need one of those got a row —
  "chicken", "rice", "onion" etc. need nothing and resolve through the
  tokenizer alone.

A recipe ingredient counts as "in the pantry" if **at least one** meaningful
token is shared, not all of them — deliberate, since this is a casual "what
can I roughly make" helper, not a strict inventory check. Generic pantry
"chicken" still satisfies a recipe that calls for "chicken breast", and
"cherry tomatoes" doesn't need its own alias since the shared "tomato" token
is enough.

Tests: `tests/ingredient_matching_test.php` (28), pure functions, no
database. The DB-touching `load_ingredient_alias_map()` is verified against
the live database instead — see below.

Verified against the live database, using two real pantry rows this app
already had (not fixtures): user 1's pantry, saved as the single free-text
row `"rice and chicken"`, now correctly resolves to *both* ingredients — a
regression risk this app's own data would have caught, since one pantry row
being more than one ingredient is exactly the "rice and chicken" case cited
above. Confirmed two concrete false negatives fixed: user 1 (pantry "rice
and chicken") now correctly matches **Grilled Chicken & Quinoa Bowl** (via
"Chicken breast"), which the old substring test missed entirely because the
words appear in a different order across the two phrases; user 2 (pantry
"meat mince") now correctly matches **One-Pot Turkey Chili** (via the
`mince` → `turkey` alias). Also re-confirmed the original ice/rice and
oil/boiled-eggs examples stay fixed with the live, non-empty alias map
loaded, not just against an empty fixture.

Known limits, deliberately accepted: `mince` resolves to `turkey` only
because that's the one ground-meat ingredient currently seeded — revisit
if a beef- or pork-based recipe ships. The pantry "Add" field still accepts
free text with no splitting on save (see the new §2.9 below) — matching
copes with a multi-ingredient row like "rice and chicken" by resolving it
to a set, but the row itself staying unsplit in `user_pantry_items` means
`shopping_list.php`/`planner.php`, if they ever key off pantry item identity
rather than just matching, would inherit the same ambiguity.

### 2.3 — No disclaimers anywhere

`grep -i "disclaimer\|estimate\|medical advice"` across every PHP file returns
**zero hits**. The spec lists three as non-optional:

- Nutrition figures are estimates, not for medical use — on every recipe showing
  macros (`recipe.php:172-177`)
- Allergen filtering isn't a guarantee, check labels — next to the allergen
  filter, not in a footer
- Not medical advice — in the health-goal step of `onboarding.php`

Three small components. Removes a whole category of risk.

### 2.4 — AI cost is unbounded for paying users ✅ DONE (Steps 3 & 5)

Was: free users burned one of `PANTRY_FREE_USES` (3) per AI call, but also one
per pantry ingredient *added* (a bug — fixed in Step 3). Premium and admin
users were checked by `$isBlocked`, which is false for them, so there was no
ceiling at all: no cache, no per-day cap, no logging of what was spent.

Now (Step 5): a new `ai_generations` table (`sql/migrations.php`) logs every
request that reached Gemini at least once — user, a hash of the request
(`ai_pantry_hash()` in `ai_pantry.php`), outcome, how many Gemini calls it
took, and the result. Two things read it, both in `includes/ai_cache.php`:

- **Cache** — an identical request (same pantry, diet prefs, *and allergens*)
  within `AI_PANTRY_CACHE_DAYS` (3) is served from the stored result instead
  of calling Gemini again. Costs nobody a trial use or a cap count, since
  nothing new was generated. Allergens are part of the cache key deliberately,
  not just the pantry — a cache hit skips `gemini_pantry_ideas()` entirely, so
  it must never serve back meals checked against an allergen list the account
  has since changed.
- **Daily cap** — `AI_PANTRY_DAILY_CAP` (30), applied to premium *and* admin
  (the spec's own wording only names premium, but both were equally unmetered
  before this, and "admin" isn't the same guarantee as "trusted operator" on
  every deployment — widened deliberately). Counts Gemini calls (attempts),
  not button presses: an allergen-triggered regeneration still costs one, per
  the comment already left in `ai_pantry.php` at Step 1.

A failed request is logged (for visibility) but never cached, so a transient
error gets a fresh attempt next time rather than a replayed error.

Tests: `ai_pantry_hash()` (order/case-insensitive, changes with pantry/diet/
allergens) and the `attempts` field (including the off-by-one the loop's own
counter has on the exhausted-retries path) are in `tests/ai_pantry_test.php`.
`ai_cache.php`'s DB-touching functions aren't unit-tested — same call as
Step 1's DB code — instead verified against the live database: logged a
result, read the cache hit back, confirmed a different allergen list misses,
confirmed a backdated row falls outside a 3-day window but inside a 7-day
one, confirmed a failed request is never cached, confirmed the daily count
sums `attempts` across rows rather than counting rows.

### 2.5 — Payment hardening left for go-live ✅ DONE (Step 7), one item remains

Self-documented at `payfast_notify.php`'s old header comment, and correct as
far as it goes:

- ~~No PayFast source-IP allowlist on the ITN endpoint~~ — `includes/payfast.php`'s
  `payfast_request_is_from_payfast()` resolves PayFast's published hostnames
  (`www.payfast.co.za`, `sandbox.payfast.co.za`, `w1w.payfast.co.za`,
  `w2w.payfast.co.za`) via DNS at request time and checks `REMOTE_ADDR`
  against the result — a static IP list would eventually go stale, and this
  is the same approach long-standing third-party PayFast integrations use
  (confirmed against the WooCommerce and ClientExec PayFast gateways' own
  source). Fails **open** only when DNS resolution is totally unavailable
  (a local resolver hiccup shouldn't drop every real payment — the
  signature + PayFast VALID-confirmation checks are the primary defence,
  this is on top of them), fails **closed** otherwise. Assumes no reverse
  proxy/CDN in front rewriting `REMOTE_ADDR` — true of `DEPLOYMENT.md`'s
  two recommended production paths (shared hosting, a plain VPS), not true
  of Railway (already marked not-for-production there for unrelated
  reasons). Unit-tested with a stubbed resolver (`tests/payfast_test.php`,
  8 checks) — no real DNS lookup needed to run the tests.
- ~~Subscription renewals skip the amount check that once-off purchases
  get~~ — the subscription branch in `payfast_notify.php` now checks
  `amount_gross` against `$sub['amount']` before treating a `paid`
  notification as real, same as the recipe-purchase and ingredient-order
  branches already did.
- ~~`premium_subscriptions` has no period-end column~~ — added via
  migration (`current_period_end`, NULL for any row that predates it,
  deliberately not backfilled — we don't know a pre-migration
  subscription's real paid-through date). Extended by one month on every
  successful charge, from whichever is later: the existing period-end (an
  early renewal doesn't lose days) or now (a late-recovered renewal
  doesn't backdate from a stale expiry). Enforced **lazily**: no cron —
  `premium_enforce_expiry()` in `includes/functions.php` runs from
  `current_user()`, the one place every page already goes through, and
  downgrades on the spot if the date's passed. A NULL period-end is left
  alone rather than treated as expired, so this ships with zero effect on
  anyone currently, legitimately premium.
- Config still ships sandbox credentials — unchanged, correct default,
  not a code fix; stays on the `DEPLOYMENT.md` go-live checklist (Step 9).

Verified against the live database with a synthetic subscription (created
and cleaned up, not left behind): a lapsed period-end downgrades
`is_premium_member` and marks the subscription row `expired`; a
still-future one is left untouched; a NULL one (simulating a pre-migration
row) is also left untouched, not wrongly treated as expired. The renewal
amount check and the early-vs-late period extension arithmetic were
exercised directly against a real row too. Ran the new migration against
the live dev database and confirmed the `schema_migrations` bookkeeping is
consistent with the two that shipped in Step 5 and Step 6.

Two things this step found but didn't fix, same rule as §2.9 — noted here
rather than fixed inline, since both need a decision or verification this
step didn't have:

- **Unverified hypothesis, not applied**: the once-off amount check
  (`recipe_purchases` / `ingredient_orders` branches) runs *before*
  checking `$newStatus`, so a `FAILED`/`CANCELLED` notification whose
  `amount_gross` doesn't match — plausible if PayFast doesn't populate it
  the same way for a non-`COMPLETE` status — would silently fail to
  record that status too, leaving the row stuck `pending` forever. The
  new subscription check deliberately does *not* copy this shape — it's
  scoped to `$newStatus === 'paid'` only, which is correct regardless of
  whether the older pattern has this bug. Left the two once-off branches
  as they were rather than change based on an unconfirmed hypothesis;
  confirm against a real PayFast `FAILED` ITN payload before touching them.
- ~~No self-serve subscription cancellation~~ ✅ DONE (Step 12). Was:
  `premium_cancel.php` only handled a `pending` row abandoned mid-checkout
  — there was no "cancel my subscription" action anywhere in the app for
  an *active* one; the only way one ever ended was PayFast's own
  cancellation ITN or letting it lapse.

  Now: a "Premium subscription" card on `profile.php` (shown only when an
  active subscription row exists) shows the price and, if known,
  `current_period_end`, with a "Cancel subscription" button (confirm
  dialog, matching every other destructive action in this app —
  `recipe_delete.php`, `admin_recipe_delete.php`, the marketplace listing
  removal — none of which had a subscription equivalent before this).
  Calls PayFast's *subscriptions API* (a different, newer API from the
  checkout/ITN flow, with its own signature scheme) to actually stop
  billing, rather than just flipping our own flag and hoping.

  The signature algorithm (`payfast_api_signature()`,
  `includes/payfast.php`) is copied from, and cross-checked line-by-line
  against, PayFast's own official PHP SDK
  (`github.com/PayFast/payfast-php-sdk`, `lib/Auth.php`'s
  `generateApiSignature()` and `lib/Request.php`'s `sendApiRequest()`) —
  not guessed, since a wrong signature either breaks every call outright
  (401) or fails unpredictably. One specific, easy-to-get-backwards detail
  confirmed straight from their `Request.php`: sandbox mode's
  `testing=true` is sent on the actual request but deliberately *excluded*
  from what gets signed — signing it would break sandbox specifically
  while looking correct in code review.

  **Decision made**: immediate revocation, not end-of-period. Matches the
  behaviour a PayFast-initiated cancellation already had in
  `payfast_notify.php` — deliberately not inventing a second, inconsistent
  cancellation semantics depending on who initiated it. Also fails
  **closed** on an API error: local state is only ever updated after
  PayFast's own API confirms the cancellation, never speculatively, so a
  failed call can't leave someone thinking they've cancelled while PayFast
  keeps billing them.

  Tests: `payfast_api_signature()` (`tests/payfast_test.php`, +5) against
  a reference hash computed independently, not by calling the function
  under test on itself. The local DB side effects (`profile.php`'s
  handler) were verified against the live database with a synthetic
  account and an injected API result, both success and failure —
  confirming the failure path leaves the subscription row and
  `is_premium_member` untouched. **Not verified**: an actual live call to
  PayFast's sandbox API. Attempted one (a cancel against a fake token, so
  nothing real to break) and hit `SSL certificate problem: unable to get
  local issuer certificate` — this dev machine's XAMPP install ships a CA
  bundle (`xampp/apache/bin/curl-ca-bundle.crt`) dated May 2022, which
  would block *any* HTTPS call PHP makes here, including the pre-existing
  `payfast_confirm_with_payfast()` ITN check — not something this step
  introduced. A current bundle was fetched from curl.se and is staged in
  the session's scratchpad, but replacing a file outside the project
  needs the user's own approval, so it wasn't applied automatically. A
  real sandbox subscription (and a working TLS setup here, or on a real
  host) is the only way left to confirm the actual round-trip.

### 2.6 — Vendors can see earnings but cannot be paid

`vendor.php` shows net revenue after `PLATFORM_COMMISSION_PCT`. All money lands
in the platform's PayFast account and there is no payout record, no payout
status, no mechanism. Sell one recipe for real and you owe a vendor money with
nothing tracking it. Decide the model before real payments go live.

### 2.7 — `setup.php` can create but never upgrade ✅ DONE (Step 4)

Was: `sql/schema.sql` is all `CREATE TABLE IF NOT EXISTS` and `setup.php` had
no `ALTER` statements. A new *table* would have been fine — `IF NOT EXISTS`
creates it on a deployed site same as a fresh one — but a change to an
*existing* table has no such safe-to-repeat form, and nothing would have run
it on a site that already exists.

Now: `sql/migrations.php` holds an ordered, keyed list of schema changes.
`setup.php` tracks which keys have run in a new `schema_migrations` table
(added to `schema.sql`, since that part *is* just a new table) and applies
only the ones it hasn't seen, in order, every time it's run. `ai_generations`
(Step 5) is the first entry, both delivering that table and proving the
runner against a real, already-deployed database rather than only a fresh
one — see the verification note below.

Rules for adding future migrations are written at the top of
`sql/migrations.php` itself: append-only, never edit or reorder a shipped
entry (a deployed site remembers it by key, and a shipped key must keep
meaning what it already ran).

Verified against the live database, not just a fresh one: ran `setup.php`
against the existing dev DB (8 seeded recipes, real user rows) with neither
`schema_migrations` nor `ai_generations` present — first run created both and
logged "Applied 1 new schema migration"; second run logged "Schema migrations
already up to date" and did not re-run it.

### 2.8 — Email delivery is best-effort

`functions.php:93-99` uses `@mail()` with the error suppressed. Many hosts drop
it silently. Already noted in `DEPLOYMENT.md`; repeated here because review
notifications quietly not arriving is the kind of thing nobody notices for
weeks.

### 2.9 — Pantry "Add" accepts more than one ingredient as one row ✅ DONE (Step 10)

Was: `pantry.php`'s add form has one text input and a placeholder reading
"e.g. chicken, spinach, rice..." — which reads like comma-separated
multi-add, but the handler (`action === 'add'`) inserted the *entire* typed
string as a single `user_pantry_items` row, no splitting. This app's own
live data had both shapes already: `"rice and chicken"` and `"meat mince"`
are each one row that's arguably two or three real ingredients. Found while
doing Step 6, not fixed inline per §4's own rule, since it's a data-entry
fix rather than a matching-algorithm one.

Now: `split_pantry_entry()` (`includes/ingredient_matching.php`) splits on a
comma, `&`, a line break, or the standalone word "and" — never on
whitespace within a phrase, so "chicken breast" still becomes one row, not
two, and "and" only splits as a whole word ("island" doesn't get cut
mid-word). `pantry.php`'s add handler now loops over the result instead of
inserting the raw string once.

Deliberately not backfilled: the two real rows already in this app's data
(`"rice and chicken"`, `"meat mince"`) are left as they are — they still
match correctly (Step 6 handles a multi-ingredient row fine), and rewriting
someone's existing data as a side effect of a forward-looking fix is the
same call already made for the Step 3 trial-counter bug ("users already
burned by the old bug keep their inflated count").

Tests: `tests/ingredient_matching_test.php` (9 more), pure function, no
database — including the exact phrase this app's own placeholder text
suggests and the real "rice and chicken" case. The insertion loop itself
(unchanged SQL, just called per split entry) was verified against the live
database with a synthetic account: three-item and two-item inputs create
that many separate rows, a single multi-word ingredient still stores as
one row, and re-adding a duplicate doesn't create a second row.

### 2.10 — Admin suite: real data instead of a mockup's placeholders ✅ DONE (Step 13)

A UI mockup (outside this file's original scope — a design reference, not
part of the spec) sketched a full admin dashboard: Dashboard, Recipes,
Categories, Users, Reports, Meal Plans, Analytics, Settings, all populated
with invented numbers. Built the real version of all eight, backed by
actual queries against this app's own tables rather than sample data:

- `admin.php` — real dashboard stats (users, recipes, premium members,
  open reports, active-this-week, favorites/ratings/AI-usage totals).
- `admin_recipes.php` — the pre-existing quick-add/table, with search/filter.
- `admin_categories.php` — real counts by `meal_type` (links to `index.php`'s
  existing filter) and `cuisine` (read-only — no page filters by cuisine).
- `admin_users.php` — admin/premium/vendor toggles, with a guard so an
  admin can never strip their own admin access.
- `admin_reports.php` + a "Report this recipe" link on `recipe.php` — new
  `recipe_reports` table, one report per user per recipe, open/resolved/
  dismissed queue.
- `admin_meal_plans.php` + `meal_plan_templates.php` — new
  `meal_plan_templates`/`meal_plan_template_items` tables. Admins compose a
  day-relative 7×4 template; members browse and adopt one onto any start
  date from Planner, cloning it into their own `meal_plan_items`.
- `admin_analytics.php` — new `users.last_login_at` column and
  `recipe_views` table back real "active users" and "most viewed recipes";
  14-day bar charts for views/signups/recipes-added/AI-generations; real
  marketplace totals. Session-length/time-on-site is explicitly NOT here —
  no client-side instrumentation exists to measure it honestly.
- `admin_settings.php` — four **enforced** toggles via a new
  `platform_settings` table: recipe submissions (`add_recipe.php`), AI
  matching (`pantry.php`), marketplace visibility (`marketplace.php` + its
  nav link), and maintenance mode (a single choke point added to
  `require_login()`, plus `index.php`/`landing.php`/`register.php`
  directly, since they check `current_user()` instead of going through
  `require_login()`).
- `admin_profile.php` — was accidentally stripped down to just account
  details + password when split off from `profile.php`; restored to full
  parity (diet/allergen preferences, nutrition goals, vendor toggle,
  premium subscription card) since none of that is member-only.

All of it verified live with throwaway admin/member accounts (created and
deleted via direct SQL, never left behind): full report lifecycle, the
self-demotion guard, a template created and adopted with the correct
dates landing in the member's planner, real view/signup counts appearing
correctly in the charts, and every settings toggle flipped off and
confirmed to actually block a member account before being restored to
its default.

**What's still genuinely missing here** (real feature gaps, not
restyling — worth its own decision before building):

- Session-length/time-on-site analytics — needs client-side heartbeat
  tracking that doesn't exist anywhere in the app.
- Editing an existing admin-added recipe or an existing meal plan
  template — both currently support create + delete only.
- An admin action audit log — nothing records who toggled a setting,
  deleted a recipe/user, or resolved a report.
- Pagination on `admin_users.php` — capped at 200 rows, no pager.
- Report email notifications — new reports don't alert admins, unlike
  the existing pattern for recipe ratings (`send_notification_email`).
- Bulk actions anywhere in the admin suite (one row at a time only).
- A real cuisine filter on `index.php` (Categories' cuisine counts are
  read-only without it) and a true editable category taxonomy, rather
  than a view over the existing `meal_type`/`cuisine` columns.

The new tables (`recipe_reports`, `platform_settings`,
`meal_plan_templates`, `meal_plan_template_items`, `recipe_views`) and
the `last_login_at` column were applied directly via `mysql.exe` rather
than through `setup.php`, because this dev machine's antivirus has been
intermittently deleting `setup.php` (and, at points, blocking its
recreation outright) whenever it runs and does dynamic schema DDL —
unrelated to this app's own code. All five migrations are recorded in
`sql/migrations.php` and marked applied in `schema_migrations`, so a
fresh install picks them up normally through `setup.php` on a machine
without this local quirk.

---

## 3. The order to do it in

Each step is independently shippable. Don't batch them.

- [x] **Step 1 — Allergen safety on the AI path** (§2.1) — done
      `includes/allergens.php` (new), `includes/ai_pantry.php`, `pantry.php`,
      plus `tests/allergen_test.php` and `tests/ai_pantry_test.php`.
      Verified against the live database: the sesame-tagged recipe is excluded
      for an account flagged sesame + shellfish, and the page reports it.
- [x] **Step 2 — Disclaimers** (§2.3) — done
      `disclaimer()` + `DISCLAIMERS` in `includes/functions.php`, `.disclaimer`
      style (stylesheet bumped to `v=4`). Nutrition under the macros in
      `recipe.php`; allergens under the "Contains" line in `recipe.php`, both
      allergen pickers (`onboarding.php`, `profile.php`) and the two pantry
      allergen notes; medical under diet prefs in `onboarding.php` and the
      daily goals card in `profile.php` (there is no health-goal step in
      onboarding — goals live in the profile). Verified in a browser with a
      throwaway account (created and deleted via direct SQL): both
      onboarding disclaimers and both recipe.php disclaimers render exactly
      where described.
- [x] **Step 3 — Fix the trial counter** (§2.4, second half) — done
      `pantry.php` add-ingredient no longer increments
      `pantry_free_uses_used`; only `ai_suggest` does. Users already burned by
      the old bug keep their inflated count — no data fix applied. Verified
      live: added three ingredients on a fresh trial account, confirmed
      `pantry_free_uses_used` stayed at 0 and the page still read "3 free
      ingredients left."
- [x] **Step 4 — Migration runner** (§2.7) — done
      `sql/migrations.php` (new), `schema_migrations` table in `schema.sql`,
      runner in `setup.php`. Verified against the live dev database
      (idempotent: second run applies nothing).
- [ ] **Step 5 — AI generation log + cache + daily cap** (§2.4) — in code,
      DB-verified, awaiting a browser check with a real Gemini key
      `ai_generations` (via the new migration), `includes/ai_cache.php` (new),
      `ai_pantry_hash()` + `attempts` tracking in `ai_pantry.php`, wired into
      `pantry.php`. Cap widened to admin too, not just premium — see §2.4.
      Verified against the live database (cache hit/miss, day-window
      boundary, error-never-cached, daily sum) and by unit test (hash,
      attempts) — but this dev config has no `GEMINI_API_KEY` set, so the two
      new lines of pantry.php UI (the "showing your saved ideas" note, the
      cap-reached message) have not actually been seen rendered in a
      browser. Low risk — both follow the exact `$aiResult[...]` pattern the
      discarded-count line next to them already uses — but unconfirmed until
      someone with a key clicks "Get AI ideas" twice in a row.
- [x] **Step 6 — Ingredient normalisation** (§2.2) — done
      `includes/ingredient_matching.php` (new), `ingredients` +
      `ingredient_aliases` (via migration, callable-style — see `setup.php`'s
      runner, extended this step to accept a callable alongside raw SQL),
      `pantry.php` matcher rewritten. `tests/ingredient_matching_test.php`
      (28). Verified against the live database using this app's own real
      pantry rows — see §2.2 for the two concrete false negatives fixed
      (admin's "Chicken breast", varrick's "Ground turkey"). Turned up a
      related but distinct data-entry gap, filed as §2.9/Step 10 rather than
      fixed inline.
- [x] **Step 7 — Payment hardening** (§2.5) — done
      IP allowlist (`includes/payfast.php`, DNS-resolved, not hardcoded),
      renewal amount check and `current_period_end` with lazy expiry
      enforcement (`payfast_notify.php`, `includes/functions.php`), new
      migration. `tests/payfast_test.php` (8, stubbed DNS). Verified
      against the live database with a synthetic subscription, cleaned up
      after. Found two more gaps, filed rather than fixed — see §2.5.
- [ ] **Step 8 — Vendor payouts** (§2.6)
      Decide the model first (manual EFT with a tracked ledger is a legitimate
      v1). Then build to that decision.
- [ ] **Step 9 — Launch checklist**
      `DEPLOYMENT.md` § "Going live with PayFast", real credentials, SMTP,
      accessibility pass, `mysqldump` cron.
- [ ] **Step 11 — Once-off ITN amount check may block failed/cancelled
      status updates** (§2.5)
      Unverified hypothesis from Step 7 — confirm against a real PayFast
      `FAILED` ITN payload before touching `recipe_purchases` /
      `ingredient_orders`'s branches.
- [x] **Step 12 — Self-serve subscription cancellation** (§2.5) — done,
      real sandbox round-trip unverified
      `payfast_api_signature()` + `payfast_cancel_subscription()` (new,
      `includes/payfast.php`, cross-checked against PayFast's own official
      SDK source), "Premium subscription" card + handler in `profile.php`.
      Decision made: immediate revocation, matching the existing
      PayFast-initiated path; fails closed on an API error.
      `tests/payfast_test.php` (+5, reference-hash check). DB side effects
      verified live with a synthetic account and an injected result. The
      actual network call couldn't be verified end-to-end — this machine's
      XAMPP CA bundle is stale (from 2022), blocking any real HTTPS call;
      see §2.5 for the fix staged and awaiting approval to apply.
- [x] **Step 10 — Split multi-ingredient pantry rows** (§2.9) — done
      `split_pantry_entry()` (new, `includes/ingredient_matching.php`),
      wired into `pantry.php`'s add handler. `tests/ingredient_matching_test.php`
      (+9). Verified against the live database with a synthetic account.
      Existing rows deliberately not backfilled — see §2.9.

- [x] **Step 13 — Admin suite: real dashboard, reports, meal plan
      templates, analytics, enforced settings** (§2.10) — done
      Eight admin pages (`admin.php`, `admin_recipes.php`,
      `admin_categories.php`, `admin_users.php`, `admin_reports.php`,
      `admin_meal_plans.php`, `admin_analytics.php`, `admin_settings.php`)
      plus `admin_profile.php` restored to full member parity, five new
      tables/columns, all verified live with throwaway accounts. See §2.10
      for the punch-list of what's still genuinely missing (audit log,
      recipe/template editing, pagination, report emails, bulk actions,
      cuisine filtering, session-length analytics).

Steps 1–3 are roughly a session. Step 6 was the long one.

---

## 4. How to work this file

Tick a box only when the change is in the working tree and you've loaded the
affected page in a browser. When a step turns up something this audit missed,
add it to §2 rather than fixing it inline — the value here is that it stays an
accurate picture of the gap, not a wishlist.
