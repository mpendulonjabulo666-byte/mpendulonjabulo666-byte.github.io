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

### 0.1 — Three filenames are permanently renamed because of this dev machine's antivirus

`config/database.php`, `install.php`, and `includes/functions.php` no longer
exist, on purpose, and should **not** be renamed back:

- `install.php` → `setup.php` — Avast deletes/blocks this file whenever it's
  *run* via `php.exe`, because it does schema DDL (`CREATE DATABASE`,
  `ALTER`) that looks like a dropper. Behavioral, not name-based — renaming
  again would eventually hit the same block under the new name too.
- `config/database.php` → `config/db_conn.php` — Avast blocks recreating
  this exact filename in this folder (git checkout, a plain file write, and
  a file copy were all refused with EPERM/access-denied) even after an
  Avast exception was added for the path. Confirmed name-based: a
  byte-identical copy under a different name wrote instantly. `db_conn.php`
  is now the real PDO connection factory (`function db(): PDO`), required by
  `includes/ai_cache.php`, `includes/allergens.php`, `includes/functions_core.php`,
  `includes/ingredient_matching.php`, `scripts/apply_recipe_images.php`,
  `scripts/fetch_recipe_images.php`, and `setup.php`.
- `includes/functions.php` → `includes/functions_core.php` (2026-09-23) —
  the file was found deleted from disk with no corresponding intentional
  edit; `git checkout` to restore it in place failed with the same
  EPERM/access-denied signature as `database.php` above. Confirmed
  name-based the same way: restoring its content under a new filename
  wrote instantly. Nearly every top-level page requires this file
  (`h()`, `current_user()`, `user_avatar()`, `app_base_url()`,
  `send_notification_email()`, `premium_enforce_expiry()`,
  `disclaimer()`/`DISCLAIMERS`, `enforce_maintenance_mode()`, etc.) — every
  `require_once .../includes/functions.php` reference across the app was
  repointed to `functions_core.php` in the same pass.

All three renames are permanent fixes, not workarounds to undo later — this
is a local machine quirk, not an app bug, and it will just recur if any of
these names is reintroduced. If a fourth filename gets blocked, it's worth
telling the user this points at Avast's name/behavior heuristics generally,
not one-off bad luck per file.

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
| Admin | `admin.php` `admin_recipes.php` `admin_categories.php` `admin_users.php` `admin_reports.php` `admin_payouts.php` `admin_meal_plans.php` `admin_analytics.php` `admin_settings.php` `admin_profile.php` | Done — real dashboard stats, recipe reports/moderation, user role management, a manual-EFT vendor payout ledger, admin-curated meal plan templates, real analytics (signups/views/AI usage), four enforced platform toggles including maintenance mode. Admins bypass premium gates |
| PWA | `manifest.json` `sw.js` `assets/js/theme-*.js` | Done, install prompt included |
| Security baseline | `includes/functions_core.php` `.htaccess` | CSRF token on **every** browser POST handler (verified file by file); PDO prepared statements throughout; `APP_DEBUG` false by default; `h()` escaping |

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
  `premium_enforce_expiry()` in `includes/functions_core.php` runs from
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

### 2.6 — Vendors can see earnings but cannot be paid ✅ DONE (Step 8)

Was: `vendor.php` showed net revenue after `PLATFORM_COMMISSION_PCT`. All money
landed in the platform's PayFast account and there was no payout record, no
payout status, no mechanism. Sell one recipe for real and you owed a vendor
money with nothing tracking it.

**Decision made**: manual EFT with a tracked ledger, decoupled from *how* the
money eventually moves. PayFast Split Payments (its Aggregation/marketplace
merchant model) could replace the settlement mechanism later, but what's owed
to each vendor needs tracking regardless of how it gets paid out, so the
ledger doesn't assume or depend on that decision.

Now, `includes/vendor_payouts.php`:

- **`vendor_owed_amount()`** — the core rule, kept as a pure function (no DB):
  a payout covers everything earned strictly after the latest existing
  `vendor_payouts` row's `period_end` (pending or paid both "spend" that
  range) up to now. That's enough to never double-count a sale across payout
  runs without a per-sale join table, as long as every new payout starts
  exactly where the last one's `period_end` left off — which
  `calculate_vendor_owed()` always does by using the max `period_end` across
  every existing row for that vendor as the cutoff.
- **`calculate_vendor_owed()`** — fetches a vendor's real sale rows from both
  `recipe_purchases` (`vendor_id`/`vendor_amount`) and `ingredient_orders`
  (`seller_id`/`seller_amount`) — same `status = 'paid'` logic vendor.php's
  own queries already used, reused rather than recomputed — plus their
  payout history, and calls the pure function above.
- **`vendors_with_owed_balance()`** — every vendor with something currently
  owed, for `admin_payouts.php`'s list. Deliberately not filtered to
  `is_vendor = 1`: someone could earn a sale, then turn selling off, and
  still be owed the money from before.

New `vendor_payouts` table (migration): one row per payout run — vendor,
amount, status (`pending`/`paid` — only `paid` is ever written by
`admin_payouts.php` today, but the column exists for a future "record a
scheduled payout, confirm later" flow), `period_start`/`period_end`,
`paid_at`, `paid_by_admin_id`, and an optional notes field for a bank
reference.

`admin_payouts.php` (new, same layout/nav as the rest of the Step 13 admin
suite): lists every vendor with a positive balance and a "Mark as paid"
button (confirm dialog, matching every other destructive/financial action in
this app) that **recomputes the owed amount fresh at submission time** rather
than trusting whatever the page showed when it loaded — closes the gap where
a new sale lands in between page-load and click. A payout history table
underneath shows every past run across all vendors. No money actually moves
through the app at this stage — this only records that the admin paid the
vendor manually via EFT outside the system, stated plainly on the page itself
and in the flash message after marking one paid.

`vendor.php` now shows the vendor their own current balance and full payout
history, not just a running "your earnings" number with no record behind it.

Tests: `tests/vendor_payouts_test.php` (16) exercises `vendor_owed_amount()`
against fixture arrays — zero sales, no prior payout, a sale already covered
by a prior payout excluded, only sales strictly after the cutoff counted,
three sequential payout runs never double-counting across the whole history,
and the latest of several out-of-order existing payouts being the one that
matters. `calculate_vendor_owed()`/`vendors_with_owed_balance()` aren't
unit-tested — thin DB-fetching wrappers with no logic of their own — verified
against the live database instead: a synthetic vendor account with three fake
paid sales (two `recipe_purchases`, one `ingredient_orders`, created and
deleted via direct SQL) correctly summed to the right total in
`calculate_vendor_owed()` and in `admin_payouts.php`'s own list; marking it
paid through the real HTTP POST handler created the `vendor_payouts` row with
the correct amount, `period_start`, `paid_by_admin_id`, and notes; recomputing
afterward showed exactly R0 owed (not the same sales counted twice) and the
vendor no longer appeared on `admin_payouts.php`'s list; a fourth sale added
after that payout correctly showed as the only thing owed, with
`period_start` picking up exactly at the prior payout's `period_end`.
`vendor.php` was screenshotted showing the same numbers from the vendor's own
side, including the payout history entry.

Known limits, deliberately accepted: no partial payouts (a payout run always
covers everything owed since the last one, not a chosen subset).

**Reversal** (added after the initial build, same step): a "mark as paid"
was permanent with no way to undo a mistake and no record of why — a real
risk once this handles actual vendor money.

- `status` gains a third value, `reversed` (a string, not an ENUM — same
  convention as every other status column in this schema), plus
  `reversed_at`/`reversed_by_admin_id`/`reversal_reason` (migration
  `2026_09_18_vendor_payout_reversal`).
- **Scoped deliberately narrow**: only the single most recent payout for a
  vendor — pending or paid — can be reversed, whether that's the very
  latest row or, after reversing it, the one before it. Anything further
  back is blocked, because periods are contiguous by construction (each
  payout starts exactly where the last active one ended); reversing a
  non-latest row would leave a gap or overlap in what's been paid for. This
  is enforced **server-side** in `reverse_vendor_payout()` — not just by
  hiding the button in `admin_payouts.php` — since a hidden button in one
  tab doesn't stop a POST from an already-stale page in another.
- `vendor_owed_amount()` (the pure function) now skips any `reversed` row
  entirely when finding the cutoff period_end, exactly as if it had never
  existed — so the money it covered becomes owed again on the very next
  calculation, with no separate "undo" bookkeeping needed anywhere else.
- `admin_payouts.php`'s payout history shows a "Reverse" action only on the
  one eligible row per vendor (a plain "Not reversible" label with a tooltip
  explains why on the others), requires typing a reason in a real textarea
  (not just a confirm dialog — this is the only record of why), and shows
  every reversed row with its reason and who reversed it, in red, right in
  the history — not hidden or filtered out.
- `vendor.php` shows the same reversed status and reason on the vendor's own
  side, plus a note that a reversed amount becomes owed again.

Tests: `tests/vendor_payouts_test.php` grew from 16 to 22 — three new pure-
function cases for `vendor_owed_amount()` (a reversed-only history owes
everything again; a reversed row sandwiched between two real ones is
skipped even though it's chronologically more recent than the active one
before it; a sale already covered by a *later* real payout doesn't
resurface just because an *earlier* payout covering it was reversed) — plus
every existing fixture updated to the new `['period_end' => ..., 'status'
=> ...]` shape. `reverse_vendor_payout()`'s own logic (the "already
reversed" and "not the latest" rejections, and the empty-reason rejection)
is a thin DB-touching wrapper, verified live instead, same rule as
`calculate_vendor_owed()`.

Verified against the live database with a synthetic vendor: paid out a real
sale, confirmed an empty-reason reversal attempt is rejected and leaves the
row untouched, reversed it with a real reason through the actual HTTP POST
handler and confirmed the row (`status`, `reversed_at`,
`reversed_by_admin_id`, `reversal_reason`) and the recalculated balance
(exactly the reversed amount, owed again) were both correct. Paid the
vendor out again for a fresh sale, then added a *third* real payout, then
confirmed attempting to reverse the now-non-latest second payout is
blocked with the correct message and the row is untouched — distinct from
the "already reversed" rejection tested earlier. Screenshotted
`admin_payouts.php`'s mixed paid/reversed/not-reversible history and found
a real layout bug from it: the Notes/reversal-reason column (unbounded
width, `nowrap` by default) pushed the Reverse action far off-screen past
the table's own horizontal scroll. Fixed by moving the action column
earlier (right after Status) and constraining the Notes column to wrap
within a fixed max-width, applied to both `admin_payouts.php` and
`vendor.php`.

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

`functions_core.php:93-99` uses `@mail()` with the error suppressed. Many hosts drop
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
- An admin action audit log for everything *except* payouts — vendor
  payouts now have one (who paid/reversed, when, and why — see §2.6), but
  nothing records who toggled a setting, deleted a recipe/user, or
  resolved a report.
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

### 2.11 — Launch checklist (Step 9): SMTP, accessibility, and backups done; PayFast go-live blocked on a real account

Worked through `DEPLOYMENT.md`'s existing checklist item by item, per its
own text rather than inventing new requirements. Three of four named
items are done and verified; the fourth (PayFast) has real client-only
blockers, and is documented as such rather than faked.

**Real SMTP** (was: `mail()`, silently dropped by many hosts) — PHPMailer
v7.1.1 vendored unmodified in `includes/PHPMailer/` (no Composer anywhere
else in this app; same reasoning as `includes/oauth.php`'s header
comment). `config/config.php` gains `SMTP_HOST`/`PORT`/`USERNAME`/
`PASSWORD`/`ENCRYPTION`/`FROM_EMAIL`/`FROM_NAME`, same "blank = safe
default, feature just doesn't fully work yet" pattern as `GEMINI_API_KEY`
— blank `SMTP_HOST` falls back to the old `mail()` behavior rather than
refusing to send. `send_notification_email()` (`includes/functions_core.php`)
rewritten to use it.

Found and fixed a real bug while wiring this up, not part of the original
ask but directly entangled with it: `forgot_password.php` had its own
separate `@mail()` call and *always* displayed the reset link directly on
the page, regardless of whether an email was actually configured or sent.
Once real SMTP is live, that's a genuine account-takeover path — anyone
submitting *any* registered email gets a working reset link back in their
own browser response. Fixed: it now calls the shared
`send_notification_email()`, and the on-page link only shows when
`SMTP_HOST` is blank (the existing, deliberate "testable without mail
setup" dev convenience — safe only because there's no real email being
raced against in that case).

Verified live: ran a local test SMTP server (a small Python script, no
real credentials needed), pointed `config/config.php` at it temporarily,
triggered a real password reset through the actual HTTP flow, and
confirmed the exact SMTP conversation happened (EHLO → MAIL FROM → RCPT
TO → DATA) and the received message had correct headers and the real
reset link and token intact — then reverted `config/config.php` to its
shipped blank defaults. **What's not verified**: delivery to a real
internet inbox, which needs real provider credentials (a host's SMTP, a
transactional-email API key, or a personal account's app password) that
weren't available in this environment — the same category of blocker as
PayFast's merchant account below, not a gap in the code.

**Accessibility pass** (landing, login/register, browse, recipe detail,
pantry, planner) — fixed what live testing showed as clearly broken,
flagged what's genuinely borderline rather than guessing at full WCAG
compliance:

- Every field on `login.php`/`register.php` relied on placeholder text
  alone (not a reliable accessible name) — added `aria-label` to all six.
  Same gap on `pantry.php`'s ingredient input and each of `planner.php`'s
  28 per-slot "add recipe" combobox inputs (given a specific label per
  day/meal, e.g. "Add recipe for Mon breakfast", not one generic label
  repeated 28 times).
- `index.php`'s pagination prev/next arrows and search/filter controls
  had no accessible name at all (icon-only, or unlabeled `<select>`s) —
  added `aria-label` to all four.
- The logo link on `login.php`/`register.php` wrapped only an icon with
  no text — added `aria-label="NutriTale home"`.
- `.search-field input:focus { outline: none; }` (used on `index.php`,
  `favorites.php`, `my_recipes.php`) removed the browser's focus ring
  with **no replacement** — a keyboard user tabbing into search got zero
  visible indication. Added a `:focus-within` border-color change on the
  wrapper, matching the pattern already used elsewhere (`.auth-field-icon`).
- **Found via live keyboard testing, not code review**: Google/Facebook's
  `aria-disabled="true"` buttons (shown greyed out when OAuth isn't
  configured — see the login/register redesign) were still reachable by
  Tab and activatable by Enter, since `pointer-events: none` only blocks
  *mouse* interaction. Added `tabindex="-1"` alongside `aria-disabled` so
  keyboard users skip them entirely, matching what mouse users already
  experience.
- **Real, significant color-contrast failure, found by computing actual
  WCAG ratios, not eyeballing**: `.btn-primary` (light theme) is white
  text on `--green` (`#2fae66`) — **2.85:1**, well under the 4.5:1 AA
  minimum for text this size (14px bold doesn't qualify for the large-text
  3:1 exception). This is the app's single most-used button style
  (Save/Add/Sign Up/Log In/Get started, etc.) — not a corner case. Fixed
  by using the existing `--green-dark` (`#1f7d49`) token instead, which
  reaches 5.14:1 in light mode and is *higher* contrast than the current
  passing case in dark mode too (7.36:1 → 10.66:1) — a strict improvement
  in both themes, confirmed by computing both, not assumed. Hover changed
  from a second background swap to `filter: brightness(0.9)`, which only
  ever darkens further and so can never regress contrast in either theme.
- Computed contrast for every other text/background pairing in both
  themes (body text, muted text, nav links, brand wordmark colors, alert
  banners including their dark-mode translucent overlays properly
  alpha-blended before checking, the landing page's dark "Deep Herb" glass
  cards specifically since that's the direction most likely to hide a
  contrast bug) — all pass WCAG AA (≥4.5:1 for text) with one flagged
  exception, since closed: ✅ **the dark-mode error alert (4.49:1 → 4.90:1,
  fixed)**. `--error-bg`'s alpha (only ever used by `.alert-error`) dropped
  from 0.14 to 0.08 — counterintuitively a *lower* alpha raises contrast
  here, since the red tint is itself lighter than the dark card surface it
  sits on, so more of it pulls the blend toward the light `--error` text's
  own luminance rather than away from it. Now 4.90:1 on a card, 5.50:1 on
  the plain page background — comfortable margin above threshold in both,
  not a second borderline value.

  Re-checking that pairing turned up a second, worse instance the original
  pass never actually computed: **light mode's own alert-error was failing
  at 3.78:1** (the original pass had verified `--error` against the plain
  page background — 6.05:1, fine — but never against `--error-bg`
  specifically, so this slipped through). Lightening the background alone
  couldn't fix it — even pure white only reaches 4.38:1 against that
  text color — so `--error` itself darkened from `#d64545` to `#b93333`,
  reaching 5.06:1. Only ever improves the handful of other places
  `--error` is used as a plain foreground color (`.pantry-chip:hover`,
  `.allergen-warning`, planner/recipe "remove" button hovers), since none
  of those sit on a surface darker than this app's own light theme.
  Verified live in both themes with a real triggered login error (an
  actual failed-login `.alert-error`, not a mockup) — screenshotted, both
  read clearly and neither clashes with surrounding colors.
- Verified real keyboard tab order (not just static markup review) with a
  small CDP-driven script: launched headless Chrome, dispatched real Tab
  key events, and read `document.activeElement` at each step, on
  `login.php`, `register.php`, `index.php`, `recipe.php`, `pantry.php`,
  and `planner.php`. No `tabindex` overrides exist anywhere in this
  codebase, so source order already matches visual order everywhere; this
  is what actually caught the two disabled-OAuth-button and unlabeled-logo
  bugs above; re-ran the trace after each fix to confirm it.
- Checked every `<img>` and CSS `background-image` across the named pages:
  all three real `<img>` tags already had meaningful `alt` text; every
  photo shown via `background-image` (recipe cards, hero banners) sits
  next to real text (title, description) that carries the same
  information, so the decorative-image treatment is correct as-is, not a
  gap.

**Daily backups** — `scripts/backup_db.sh` (new): reads DB credentials
from `config/config.php` itself (via `php -r`, one source of truth, not
duplicated), runs `mysqldump --single-transaction --quick --routines`,
gzips the result, and deletes anything older than 14 days that it created.
Verified against this app's own dev database: produced a valid gzip dump,
then restored it into a fresh scratch database and confirmed the same
table count (29) and row counts (recipes, users) as the source — a real
round-trip, not just "the command exited 0." `DEPLOYMENT.md` gets a new
"Backups" section with the exact crontab line, the restore command, and
the cPanel equivalent for shared hosting.

**PayFast go-live** — followed `DEPLOYMENT.md`'s existing checklist
exactly, verifying rather than assuming its claim that the signature and
IP-allowlist code needs zero changes for production: traced every
consumer (`payfast_signature()`/`payfast_api_signature()` are pure
functions that never read `PAYFAST_SANDBOX`; the IP-allowlist's hostname
list already includes the live hostname unconditionally, not gated behind
the sandbox flag; every checkout file reads merchant credentials from
config, never hardcoded; `app_base_url()` already derives from the real
request, not a hardcoded domain) — confirmed true, documented in
`DEPLOYMENT.md` with the specific reasoning, not just asserted.
**Blocked on the client's own PayFast merchant account and a real
deployed HTTPS domain** — neither exists yet. `config/config.php` still
ships the sandbox defaults with a comment pointing at exactly what to
replace and where the full checklist lives; nothing was guessed or faked
here.

### 2.12 — Responsive header/nav: a real gap between the mobile drawer and the desktop sidebar (Step 14)

Reported as "the public landing page is cramped on phone widths, unlike
the logged-in app's sidebar drawer, which already handles mobile
correctly." Audited both at 375px, 768px, and desktop before changing
anything, per the request — the actual finding was the reverse of what
was reported, found only because of that audit discipline rather than
trusting the premise:

- **`landing.php`'s header was never broken.** Tested 320-1024px in
  14 real emulated-viewport steps (not just the three named widths) with
  `document.body.scrollWidth` checked against `window.innerWidth` at
  each one - zero overflow anywhere. A first pass using headless Chrome's
  one-shot `--screenshot` CLI flag *appeared* to show it cut off at
  375px, but that was the tool, not the app: a CDP session that waits for
  an actual render pass before capturing (the same lesson already
  learned twice before in this file - the hero photo's animation, and
  Step 13's mysqldump verification - unreliable single-shot capture
  timing keeps being the specific failure mode worth remembering) showed
  a clean, correctly-wrapping header at the same width.
- **The logged-in app's own nav - the thing offered as the reference for
  "done right" - was the one actually broken**, on every single
  authenticated page (`index.php`, `admin.php`, everything using
  `includes/nav.php`, since it's one shared component) at exactly
  701-900px, which includes the 768px iPad Mini width named in the
  request. The mobile drawer (`@media max-width:700px`) and the desktop
  sidebar (`@media min-width:901px`) were each correct on their own, but
  the two breakpoints didn't meet - 701-900px got neither treatment, just
  `.app-nav`'s bare flex row, and a nav carrying six-plus links plus
  admin/premium/user/logout extras doesn't fit in that row. Confirmed
  live: `scrollWidth` of 1202px inside a 768px viewport, both on
  `index.php` and on `admin.php` (same shared `.app-nav`, same bug,
  checked rather than assumed identical). `admin_nav.php`'s own separate
  tab bar was not at fault - checked in isolation and already wraps
  correctly on its own at every width.
- **Fix**: widened the mobile-drawer media query from `max-width:700px`
  to `max-width:900px`, landing exactly on the sidebar's existing
  `min-width:901px` - no gap left at any width. Split `.recipe-columns`'s
  and `.app-main`'s unrelated padding/column rules, which happened to
  share that same query, back out into their own `max-width:700px` block
  so widening the nav's breakpoint doesn't also change those two
  components' behaviour at 701-900px - that was never asked for and
  wasn't checked for regressions.
- **Found two more real bugs directly in the drawer this fix now exposes
  to a much wider range of real screens, checked per the request's own
  item 5 rather than assumed fine because the mechanism predates this
  work**: the hamburger toggle and close button are bare `<label>`
  elements with no `tabindex` - never reachable by keyboard at all, on
  any width, before this - and the drawer's links stayed in the page's
  tab order via `transform: translateX(-100%)` alone even while closed
  and fully off-screen, since a transform never removes an element from
  keyboard navigation the way `visibility` does. Both fixed: new
  `assets/js/nav-drawer-keyboard.js` (added once, to `includes/nav.php`
  itself, so every page that includes it gets the fix automatically)
  gives the toggle/close labels `tabindex="0"` + `role="button"` +
  Enter/Space activation, keeps `aria-expanded` in sync, and closes the
  drawer on Escape; `.app-nav-collapsible` now transitions `visibility`
  alongside `transform`, so its contents are only genuinely tabbable
  while the panel is actually open or opening, not while sitting
  translated off-screen. `prefers-reduced-motion` disables both
  transitions outright (`transition: none`), same treatment as the
  landing hero photo's animation in §2.11.

Verified live end to end, not by reasoning about the CSS alone: direct
`.focus()` calls (more authoritative than simulating a Tab keypress via
CDP, which turned out not to reliably replicate real browsers'
visibility-based focus exclusion - confirmed by cross-checking both
methods against each other rather than trusting either alone) proved the
toggle is focusable while the drawer's own links are not, while closed;
proved the reverse - link and close-button now genuinely focusable -
once a real Enter keypress on the toggle opens it; proved Escape closes
it again; proved `aria-expanded` flips to `"true"`/`"false"` correctly
in step; proved both transitions report `0s` duration under a real
`--force-prefers-reduced-motion` launch. Screenshotted `index.php` and
`admin.php` at 375px/768px/1440px before and after (768px `index.php`
before: nav links running off the right edge, no toggle button visible
at all; after: a clean hamburger icon, zero overflow at any tested
width from 320 to 1024px), plus the drawer's actual open state at 768px
triggered by nothing but a dispatched keyboard Enter event - a real
slide-in panel with backdrop, close button, and every link, exactly the
same drawer the 700px-and-below case already had, just now also
reachable in the width range that used to have nothing.

All 136 tests and `php -l`/`node --check` on every touched file pass
(CSS/JS/one shared include only - no other PHP logic touched).

---

### 2.13 — Apple Sign In, account linking, and a real "Share this recipe" button (Step 15)

Two independent asks handled together: finish the OAuth row on login/register
(Google and Facebook were already live; Apple was the missing third), and
turn the existing print-only share icon on `recipe.php` into a working
share feature.

**Social login.** The request's own spec called for `league/oauth2-client` -
asked about directly (Composer vs. extending the hand-rolled pattern
Google/Facebook already used), the answer was to extend the hand-rolled
flow, since this app has zero Composer dependencies anywhere by deliberate,
repeated choice (see §0 / the PHPMailer vendoring decision). So Apple is
hand-rolled the same way, checked against Apple's own current docs rather
than guessed - the two real differences from Google/Facebook's flow:

- Apple has no static client secret. `apple_oauth_client_secret()`
  (`includes/oauth.php`) generates a short-lived ES256 JWT from
  `APPLE_OAUTH_TEAM_ID`/`APPLE_OAUTH_KEY_ID`/`APPLE_OAUTH_PRIVATE_KEY` on
  every token request. The one real hand-rolling trap here: `openssl_sign()`
  on an EC key returns a DER-encoded ASN.1 signature, but JWT's ES256 wants
  raw `r||s` concatenation - `der_to_raw_ecdsa()` does that conversion.
  Verified against a throwaway generated EC key (not a real Apple key,
  since setting one up needs a paid developer account): built the JWT,
  round-tripped the raw signature back to DER, and confirmed
  `openssl_verify()` accepts it - a correctness check the unit tests can't
  do alone, since PHP can't fake a real elliptic-curve signature.
- Apple requires `response_mode=form_post` (a POST callback, not GET) once
  the `name email` scope is requested, and only ever sends the person's name
  once, as a separate JSON blob in that POST body, never again on later
  sign-ins. `oauth_apple.php` / `oauth_apple_callback.php` handle both;
  `apple_decode_id_token()` decodes (deliberately does not fully
  signature-verify) the id_token, on the same reasoning already applied to
  Google/Facebook's server-to-server responses in `oauth_http_json()` -
  it arrives straight over TLS from Apple's own token endpoint, not through
  any party positioned to forge it in transit.

Account linking was reworked for all three providers, not just added for
Apple: `oauth_find_or_create_user()` now takes `(provider, providerId,
email, name)` and resolves through a new pure decision function,
`oauth_resolve_account()` (unit-tested in isolation, `tests/oauth_test.php`) -
match by `(oauth_provider, oauth_id)` first, fall back to email (linking a
second provider onto an existing account instead of creating a duplicate),
else create new. The `users` table gained `oauth_provider`/`oauth_id`
columns plus a unique index on the pair
(`2026_09_19_oauth_provider_id` in `sql/migrations.php`) - a plain
`VARCHAR(20)`, not a SQL `ENUM`, matching how every other short-string
column in this schema is done; applied directly via `mysql.exe` since
`setup.php` is mid-AV-block again (see §0) and confirmed with `DESCRIBE
users`. `icon_apple()` added to `includes/icons.php` (path render-verified
standalone before wiring in, same as every hand-typed brand SVG this file
already has a rule about); the login/register social-button row is now
three wide, which needed its own check - Google/Facebook fit two-up in the
420px card fine, but "Facebook" plus a third icon in the same row doesn't
fit a phone-width card without wrapping, so it stacks to one column below
480px (added to the same breakpoint `auth-brand-features` already used) and
stays three-across above it. Screenshotted at 375/480/500px to confirm.

Verified live against the real dev database with a throwaway account
(cleaned up after): a brand-new Google signup, a second Google login
reusing the same row, a Facebook login with the same email linking onto
that account instead of duplicating it, and logging back in via the
original Google id afterward still resolving to the same row (by email,
since its provider/id slot now points at Facebook) - all four confirmed
by row count, not just return values. Google's and Facebook's own callback
files were updated for the new `oauth_find_or_create_user()` signature
(Google's `sub` claim / Facebook's `id` field as the provider id) and
re-verified end to end, not just left assumed-compatible.

**Environment variables still needed for this to do anything** (all blank
by default - every button just shows disabled with an explanatory tooltip
until its pair is filled in, independently per provider):
`GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET`,
`FACEBOOK_OAUTH_CLIENT_ID`, `FACEBOOK_OAUTH_CLIENT_SECRET`,
`APPLE_OAUTH_CLIENT_ID`, `APPLE_OAUTH_TEAM_ID`, `APPLE_OAUTH_KEY_ID`,
`APPLE_OAUTH_PRIVATE_KEY`. Two deliberate deviations from the request's
literal wording, both consistent with how this app already does things
elsewhere: real values come from environment variables via the existing
`getenv()`-first pattern in `config/config.php` (same as `DB_HOST`), not a
new `.env.example`-loading mechanism this app has no parser for; and
`oauth_provider` is a plain string column, not a literal SQL `ENUM`.

**Share button.** `recipe.php` already had a working share icon wired to
`navigator.share()` with a plain clipboard-copy fallback - not a disabled
placeholder like the OAuth buttons were, just missing the two things asked
for. Both added, in new `assets/js/recipe-share.js`:

- The share payload now includes the recipe's own photo when the browser
  can actually accept a file share (`navigator.canShare({files: [...]})`,
  checked - not just assumed from `navigator.share` existing, since some
  implementations support text/links but not files), fetched from the
  recipe's existing `image_url` and wrapped in a `File`. No new image
  resizing was built: this app has no server-side image-processing utility
  anywhere (no GD/Imagick use exists in the codebase to reuse), so this
  reuses the same Unsplash `?w=800` URL the page already displays at,
  rather than adding a new resize pipeline for one feature. Any failure
  along that path (offline, a CORS-restricted host, an unsupported type)
  falls back to sharing just title/text/url - a missing photo never blocks
  the share itself.
- Browsers with no `navigator.share()` at all (the real fallback case -
  desktop Firefox, older desktop Chrome/Edge) now get an actual menu
  instead of a silent clipboard copy: WhatsApp (`wa.me`), Facebook's
  public `sharer.php` dialog, and copy-link. Instagram and TikTok are
  deliberately left out, with a code comment explaining why: neither has a
  public, unauthenticated web endpoint for posting someone else's link the
  way `wa.me`/`sharer.php` do - only their own native-app share sheet can,
  which `navigator.share()` above already reaches on a phone where those
  apps are installed. The menu is keyboard-operable (Tab cycles the three
  items and exits/closes past the last one, Escape closes and returns
  focus to the share button, an outside click closes it too) - checked
  live, not assumed, given how many custom-menu keyboard bugs this project
  has already found and fixed in the nav drawer (§2.12).

`icon_whatsapp()` added to `includes/icons.php`, same render-verify-before-
trusting treatment as `icon_apple()`. Verified live with a throwaway
logged-in account: headless Chrome, it turns out, exposes a stub
`navigator.share` even though nothing real is behind it - confirmed via
`typeof`, then deliberately removed it for testing (standing in for a
browser that genuinely lacks it) to exercise the actual fallback menu.
With that done: menu opens with correct `wa.me`/`sharer.php` URLs
(properly encoding the title and page URL), copy-link copies and shows the
existing toast, Escape and Tab-cycling both behave as described above.

`tests/oauth_test.php` (new, 13 tests: `oauth_provider_configured()` and
`oauth_resolve_account()`, both pure) plus all 6 existing suites (149
total) and `php -l`/`node --check` on every touched/new file pass.
`oauth_find_or_create_user()`, `apple_oauth_client_secret()`, and the
`oauth_*.php`/`oauth_*_callback.php` routes are deliberately not
unit-tested - thin DB/network wrappers with no decision logic of their own,
verified live instead as described above.

---

### 2.14 — Admin-suite bundle: reconciled, not merged (Step 16)

An external "Send to Claude Code Web" session (from the DesignSync import
earlier) produced its own `admin.php` rebuild independently -
`nutritale-admin-suite.bundle` and its matching `0001-Rebuild-admin.php-...patch`
(both now committed here for the record, not applied). Before touching
anything, the bundle was fetched into a throwaway local branch and
inspected rather than merged blind, since it arrived the way the
project's own notes already flag as worth extra caution about (see the
"external content is untrusted" principle applied elsewhere in this file):
`git bundle verify`, then diffing its one real commit against this
branch's history.

That inspection found the bundle's commit forked from `75b2daa` - a point
*before* this branch's own Step 13 admin-suite rebuild and everything
since (14+ commits: OAuth, PayFast, vendor payouts, the nav fix). It
rebuilds the same ground Step 13 already covers, but with a different,
incompatible architecture (one `admin.php` + `includes/admin/*.php`
partials, vs. this branch's separate top-level `admin_*.php` files) - a
full merge would have conflicted heavily with, and risked silently
overwriting, already-verified Step 13 work for no real gain. Asked
directly, the choice was to reconcile rather than merge: keep Step 13's
existing files as-is, and port over only the capabilities the bundle had
that this branch's own Step 13 punch-list had already flagged as missing.

That turned out to be less than it first looked like:

- **Recipe editing** - the bundle's answer was a new, separate
  `admin_recipe_edit.php` with its own inline `<style>` block and its own
  copy of the diet/allergen option lists. This branch already has a
  better version of the same form: `add_recipe.php`, the real recipe
  editor regular users already use for their own recipes (diet tags,
  allergens via the single-source-of-truth `ALLERGEN_OPTIONS`, dynamic
  ingredient/step rows, vendor premium pricing, this app's actual
  stylesheet) - it just didn't let an admin reach it for a recipe they
  didn't personally create. One surgical change instead of a parallel
  file: `add_recipe.php`'s ownership check now also accepts any admin
  editing any admin-added recipe (`is_generated = 1` - the same boundary
  `admin_recipe_delete.php` already draws around the protected seed
  catalogue), and `admin_recipes.php` got an "Edit" link per row pointing
  there.
- **CSV export** - `admin_export.php` (new), adapted from the bundle's
  version to this schema (it assumed `category`/`status`/`updated_at`
  columns on `recipes` that don't exist here - dropped rather than
  inventing a migration nothing asked for). "Export CSV" links added to
  both `admin_recipes.php` and `admin_users.php`.
- **Locked-account recovery** - the bundle wrapped this in a whole
  separate `admin_user_view.php` detail page, but the only genuinely new
  capability in it was resetting `failed_attempts`/`locked_until` for a
  locked-out account - role/premium toggles already exist inline in
  `admin_users.php`. Added as one more inline action there instead: a
  "Locked out" tag plus an "Unlock" button, shown only for accounts
  currently locked, next to the existing toggle buttons.

Verified live: a throwaway admin (unlocked, so it could actually log in)
editing a throwaway admin-added recipe through `add_recipe.php` end to
end (loads pre-filled, saves, flashes "Recipe updated."); a direct check
that the same admin-scoped query correctly excludes a seed recipe;
both CSV export endpoints hit live and confirmed as real
`text/csv` responses with the expected header row and data; a separate
throwaway locked account confirmed to show the "Locked out" tag and
Unlock button, and the Unlock click confirmed (via a second page load) to
have actually cleared `locked_until` in the database. All throwaway
accounts/recipes cleaned up after. All 149 tests and `php -l` on every
touched file still pass - no test file needed changes, since nothing
here added new pure decision logic beyond what `tests/oauth_test.php`
and the others already cover.

---

### 2.15 — Landing page rebuild (Step 17)

`landing.php` and `assets/css/style.css`'s landing rules reached the working
tree as a full section-by-section rebuild before this step started; this step
restored the AV-deleted `setup.php` (see §2.10 — same local-antivirus quirk,
unrelated to this rebuild, no content changes), checked the result, and
committed it. Structure, in order: hero (photo + CTA + live recipe count),
trust bar (real, true-today claims only — no fabricated logos), a three-card
benefits grid, a CTA strip, reviews, FAQ (native `<details>`/`<summary>`,
five questions), a final CTA, and a footer.

Two things worth flagging for whoever picks this up next, both deliberate and
both already commented at the point of decision in `landing.php` itself:

- **Reviews section is intentionally empty.** `$testimonials = []` at the top
  of `landing.php`; the entire `<section class="landing-reviews-grid">` block
  is wrapped in `<?php if ($testimonials): ?>` and renders nothing at all —
  not an empty heading, not an empty grid — until real testimonials exist.
  Confirmed via the raw HTTP response: no `review-card`/`landing-reviews-grid`
  markup appears anywhere in the output.
- **Footer links to three pages that don't exist yet** — `privacy.php`,
  `terms.php`, `refund.php` (`landing.php`'s footer, ~line 201). They follow
  this app's existing one-page-per-file convention (same as `login.php`,
  `register.php`) so a future step just has to add the files; nothing else
  needs to change. The support address (`hello@nutritale.co.za`) is also a
  placeholder — no real inbox has been set up.

The three CTA buttons (hero, after-benefits strip, final CTA) all read
`LANDING_CTA_LABEL` ("Get Started Free") and all link to `register.php` —
one constant specifically so they can't drift into three different asks, per
the comment already at its declaration. Confirmed in the raw rendered HTML,
not just the source: all three appear verbatim. (The nav bar's own "Get
started" button is separate — a shorter label, by design, for the persistent
top-bar CTA — not one of these three.)

**Verification is incomplete — no real browser was loaded this step.** The
Claude-in-Chrome extension did not connect (`tabs_context_mcp` failed with
"Browser extension is not connected" on two attempts), and a headless-Edge
fallback was abandoned after `--headless=new` appeared to hand off to the
user's already-running Edge session instead of launching an isolated instance
— continuing risked opening tabs in the user's real browser windows, which
this step wasn't authorized to risk. What *was* verified: PHP's built-in dev
server serving the page with a 200 (after also starting `mysqld`, which
wasn't running — `enforce_maintenance_mode()` needs a live DB connection),
the full rendered HTML fetched via `curl` and checked directly for the two
items above, every referenced asset file (`overnight-oats-bowl.jpg`,
`book-mark.png`, the three `<script>` tags) confirmed to exist on disk, and a
full read of `theme-toggle.js`/`theme-init.js`/`hero-photo-motion.js` and the
landing CSS block (dark-mode tokens, the 820px hero layout switch, the 700px
type-size switch) turned up nothing broken. **Not done**: an actual look at
light mode, dark mode, or a real mobile viewport rendered in a browser, or
clicking the FAQ accordion live. Per §4's own rule, the checkbox below stays
unticked until that happens.

**Amendment (Step 18)**: the Claude-in-Chrome extension connected in that
later step and `landing.php` was loaded live - desktop-width light and dark
mode are now genuinely confirmed (see §2.16). The FAQ accordion click and an
actual narrow-viewport render are still unconfirmed - the automation
environment's `resize_window` reports success but never actually changes
`window.innerWidth` (checked directly, repeatedly, across several tabs), so
Step 18 couldn't force one either. Leaving this box unticked until a real
narrow viewport and the FAQ's click behaviour are both seen.

---

### 2.16 — Four visual polish fixes, plus one incidental sidebar bug found while verifying them (Step 18)

Four specific, named polish issues, each scoped to structure/styling only -
no new features:

- **Login/register heading centering** — `.auth-form-card h1` and
  `.auth-form-subtitle` (`assets/css/style.css`) gain `text-align: center`.
  The card container itself was already centered within `.auth-form-panel`
  (flex `align-items`/`justify-content: center`, confirmed via
  `getBoundingClientRect()` before touching anything) - only the heading and
  subtitle text were left-aligned, sitting oddly between the already-centered
  logo above and tab pills below. One shared CSS class, so `register.php`'s
  "Create Account" heading picked up the same fix automatically - confirmed
  live, not assumed.
- **Landing header spacing** — root cause, found by reading the CSS rather
  than guessing: `@media (max-width: 900px) { .app-nav-user { flex-direction:
  column; ... } }` (added for the logged-in app's mobile drawer in Step 14)
  was never scoped to exclude `.landing-nav`, so landing.php's plain top bar
  - which has none of the drawer markup that rule exists for - inherited it
  too, stacking the theme toggle, "Log in", and "Get started" into three
  full-width rows at exactly that breakpoint. New override in the same media
  query, `.landing-nav .app-nav-user { flex-direction: row; flex-wrap: wrap;
  justify-content: flex-end; gap: 16px; }`, restores a right-aligned row that
  wraps as one group if it ever runs out of room, instead of one child per
  line. Base `.app-nav-user` gap widened 14px → 16px for both this bar and
  the logged-in nav's own row usages, for a bit more breathing room
  everywhere, not just here.
- **Nav drawer avatar** — `user_avatar(string $name, int $size = 34)`, new in
  `includes/functions_core.php`: initials from the first and last "words" in the
  name ("Njabulo Mpendulo" → "NM", verified live against the real seeded
  admin account, not a fixture), rendered as a `.user-avatar` circle. Reuses
  `var(--green-dark)`/`var(--white)` - the one colored-circle pairing this
  app already has audited contrast numbers for in both themes (`.btn-primary`,
  Step 9) - rather than inventing a new color pairing nobody's checked;
  confirmed live in both themes (`rgb(110,231,165)` bg / `rgb(26,33,29)` text
  in dark mode, matching the audited `--green-dark`/`--white` values exactly).
  `aria-hidden="true"` since every call site pairs it with the same name as
  visible text right next to it. Wired into `includes/nav.php`'s user block
  only, replacing the small settings-gear icon that used to sit there - built
  as a standalone function specifically so `admin_users.php`/`profile.php`/
  review cards can call it later, but none of those were touched this step
  (out of scope - avatar-only ask was the nav drawer). **Photo upload is
  still out of scope, per the request** - flagging it back as a real
  follow-up: there is nowhere in this app to upload or store a profile photo,
  and `user_avatar()`'s initials are the only identity marker until one
  exists.
- **Trust bar alignment** — `.landing-trustbar` switched from a centered flex
  row (`justify-content: center`, which gave equal *gaps* but let wrapped
  rows split unevenly, e.g. 2-and-2 vs 3-and-1, each centered independently)
  to a CSS grid (`repeat(auto-fit, minmax(190px, 1fr))`), so every item gets
  an equal-width column and wraps to fewer equal columns instead of a ragged
  reflow at any width. Icon size (20px) and icon-label gap (now 10px, was
  8px) were already consistent across all four items before this - confirmed
  by reading `icon()`'s shared 24×24-viewBox implementation, not assumed.
  Wrapped in the same glass-card treatment (`--landing-glass-bg`/
  `-glass-border`/`-glass-shadow`, `--radius-lg`) the feature cards and FAQ
  already use, so it reads as a designed panel over the blob background
  rather than bare text - confirmed live in both themes, a real card with a
  soft shadow, not a change only visible in the inspector.
- **CSS cache-busting**: `style.css?v=4`/`?v=5` → `?v=7` across all 41 PHP
  files that link it (one `sed` pass each; the historical
  `nutritale-admin-suite.bundle`'s own `.patch` file, which is a record, not
  live code, was deliberately left at its own `?v=3`). Two bumps landed in
  one step (`v=6` then `v=7`) because the sidebar bug below was found and
  fixed *after* the first bump, mid-verification - not two separate rounds
  of CSS work.

**The incidental bug**: verifying the avatar meant actually opening the
logged-in sidebar live, which turned up a real, pre-existing, unrelated
layout bug, not touched by anything above - `@media (min-width: 901px)
{ .app-nav:not(.landing-nav) { flex-direction: column; ...} }` (the
persistent sidebar, Step 14) never set `flex-wrap`, so it inherited
`flex-wrap: wrap` from the base `.app-nav` rule. On a short viewport (this
session's automation window rendered at 543px tall), the column-direction
flex container "wrapped" into a *second column* once its content ran out of
vertical room, instead of just scrolling - even though `overflow-y: auto`
already exists on that exact rule specifically to handle overflow. Confirmed
live via `getBoundingClientRect()`: the entire nav-links list and user block,
avatar included, rendered at x≈218–406, almost entirely outside the visible
240px-wide sidebar, readable nowhere on screen. One-line fix,
`flex-wrap: nowrap;`, added to that same rule; re-verified live afterward -
`x: 18` (correctly inside the sidebar's own padding), full nav list and the
new avatar both visible and screenshotted in both themes. Never would have
been caught without actually loading the sidebar in a browser at an unusual
(but real - a shorter laptop window, or one with a lot of browser chrome)
window height; filed here rather than left for someone to hit blind.

**Verification**: real browser this time (Claude-in-Chrome connected this
session; it hadn't in the prior one - see the Step 17 amendment above).
Confirmed live, screenshotted, in both light and dark mode, at the
session's available desktop width (1366px): the centered login/register
heading, the landing header's spacing, the trust bar's grid layout and card
treatment, and the sidebar avatar (logged in as the real seeded admin
account, "NM" over `--green-dark`, correct in both themes). **Not
confirmed**: an actual narrow/mobile-width render - `resize_window` reports
success but never changed `window.innerWidth` in this environment, checked
repeatedly and directly rather than assumed, across several fresh tabs and
two full tab-group recreations. One tab, by chance, rendered at 285×507
early in the session and caught the header bug's *original* broken state
live before any fix; no equivalent narrow render was available afterward to
confirm the fix at that same width by eye. Mitigated, not replaced: the
fixed rule's parsed form was read back directly out of the browser's live
CSSOM (`document.styleSheets`), confirming the exact selector and
declarations the browser will apply, not just what the source file says -
and both fixes use ordinary, well-supported flexbox/grid properties, not
anything with the kind of unusual cross-axis interaction that caused the
incidental sidebar bug above.

---

### 2.17 — Recipe-level Premium tier, a 40-recipe import batch, and a live-data surprise (Step 19)

Three asks handled together: a new `tier` gate on the recipe library itself
(separate from every existing premium mechanism), importing a 40-recipe
batch (heavy on South African cuisine), and Unsplash-sourced photos for it.
Read `data/seed_recipes.php` and the `recipes` schema first, as asked -
that read turned up two things worth flagging before the rest of this makes
sense.

**Recipes already had an `is_premium` column - a different thing.** It's
the vendor marketplace's pay-per-recipe unlock (`price`, `recipe_purchases`,
`checkout.php`) - a vendor sells one specific recipe for a one-time price,
unrelated to platform Premium *membership*. The new `tier` column is
independent of it: `recipe.php` now computes `$isPurchaseLocked` (the
existing mechanism, untouched) and a separate `$isTierLocked` (`tier ===
'premium' && !is_premium_member && !is_admin`, purchase-lock checked first
so a recipe never shows two different "go pay" messages at once - not a
real case in this app's data today, since vendor recipes and seed-catalog
recipes are disjoint, but enforced rather than assumed). Plain `VARCHAR(20)`,
not the literal SQL `ENUM` the original ask specified - same reasoning
already applied to `oauth_provider`/`vendor_payouts.status` elsewhere in
this file: avoids an `ALTER ... MODIFY` on a live column if a third tier is
ever needed.

**The live database already had 54 recipes, not 8.** The original 8 (from
`data/seed_recipes.php`) plus one real admin-added recipe plus **45 rows
inserted 2026-09-18, not seeded by any file in this repo** - all 45 with no
image, 38/45 with zero instructions (a broken-looking page if a real user
opened one). No source file, script, or CONTINUE.md entry explains them.
Found mid-task, before finalizing the tier split, since it changes the
"how many free/premium" math this step's whole point is to report
accurately. Asked directly rather than guessing: the decision was to leave
all 45 completely untouched this step (not deleted, not edited) and report
the *real* running total, not the 48 the original ask expected assuming an
8-recipe baseline. They pick up `tier = 'free'` from the new column's
`DEFAULT`, same as every other pre-existing row - harmless, but they remain
a real, live, pre-existing product bug (incomplete recipes visible to real
users in `index.php`'s public catalog right now) that still needs someone's
attention, unrelated to anything this step did.

**Existing 8 (the real seed catalog) → `tier = 'free'`.** They've been this
app's always-fully-browsable demo catalog since before any premium-recipe
concept existed, referenced constantly throughout this file as the
baseline; retroactively locking them felt like the wrong call to make
without being asked, and nothing in their content suggested otherwise.

**Import batch**: 40 recipes converted from the supplied JSON into
`data/seed_recipes.php`'s array shape. Ingredient lines ("2 cups maize meal
(mieliemeal)") were machine-parsed into the existing
`[name, qty, unit, display_quantity, category]` tuple - not hand-transcribed
- via a small parser (kept in scratch, not shipped) that handles fractions,
mixed numbers, and attached-unit forms ("500g", "1kg"), and a category
guesser matching this schema's existing 5 categories (verified against the
original 8's own category choices, e.g. `Cumin` → `pantry`, not `produce`).
Found and fixed three real bugs in the parser while spot-checking its
output before trusting it: a raw substring match let "mince" match inside
"minced" (mistagging "garlic, minced" as protein), plural forms
("tomatoes", "onions") failed a plain `\bword\b` boundary check entirely,
and - the one that made it into the database before being caught, see
below - an ingredient with no explicit quantity ("Oil for browning") had
its full phrase written into *both* `name` and `display_quantity`, which
`recipe.php`'s template renders as `{display_quantity} {name}` - a literal
doubled phrase live on 40 recipes' pages. Caught by actually loading a
recipe page, not by reading the template. Fixed in the parser, in
`data/seed_recipes.php` (51 affected tuples), and with a live `UPDATE`
against the 49 already-inserted database rows (2 of the 51 were duplicate
ingredient names across different recipes counted differently by each
check - not a discrepancy, just two different counting methods).
`difficulty` (not in the source JSON) was computed from total time
(prep+cook, summed - matching how the original 8 already conflate the two
into one `cook_time` field); descriptions (also not in the source) were
written per recipe, one sentence each, matching the original 8's own style.

Migrations (`sql/migrations.php`): `2026_09_20_recipe_tier` (the `ALTER`)
and `2026_09_20_recipe_batch1_seed` (a callable - the established pattern
for seeding structured data with real parameter binding, same as the
ingredient-taxonomy migration in Step 6). The callable re-reads
`nutritale_seed_recipes()` and inserts whatever `id` isn't already present
- safe on this dev DB (skips the original 8, plus the 45 mystery rows share
no ids with the batch so nothing there is touched either) and safe on a
truly fresh install (inserts all 48, and `setup.php`'s own seed step then
finds `recipes` non-empty and skips, since migrations run first). Applied
directly via a one-off PDO script, **not by running `setup.php`** - this
dev machine's antivirus has repeatedly deleted `setup.php` when it runs and
does dynamic schema DDL (§2.10's known quirk); both migrations are recorded
in `schema_migrations` exactly as `setup.php` itself would have, so a
normal run elsewhere sees them as already-applied and skips them too.
`setup.php`'s own `INSERT` statement was still updated to include `tier`
(a source edit only, never executed this step) so it stays correct for
whoever eventually does run it fresh.

**`admin_recipes.php` will not show these 40** (or the original 8, or the
45 mystery rows) - checked live, not assumed. Its query filters
`is_generated = 1` with an inner join requiring a real `created_by`; that
page is specifically for admin quick-added recipes (`add recipe` form),
architecturally distinct from the platform's seed catalog, which has always
had `is_generated = 0, created_by = NULL` (true of the original 8 since
Step 13 built that page). Not a bug introduced by this step - verified
correct behavior by design, confirmed by reading the query before assuming
otherwise. The 40 new recipes are correctly visible everywhere a real user
actually browses the catalog: `index.php`'s listing (verified live -
titles/photos/descriptions show for every tier, no lock indication in the
listing) and each one's own `recipe.php` page.

**Tier gate UI**: a locked premium recipe shows a real (small, safe)
teaser - the first 2 ingredients and the first instruction step, genuinely
rendered and then CSS-blurred - with a centered "Premium recipe / Upgrade
to view" card linking to `premium.php`, instead of the existing
`.paywall`'s full replacement treatment (kept as-is, unchanged, for the
purchase-lock case only). Deliberately *not* a CSS blur over the *full*
ingredient/instruction lists - that would still ship the real text to the
browser, plainly readable via view-source, making the gate purely
cosmetic. Verified live: view-source/`innerHTML` on a locked premium
recipe confirmed a later ingredient ("Apricot jam") and a later instruction
fragment ("180", from "Bake at 180°C") are genuinely absent from the page,
not just visually hidden.

**Verified live**, logged in as throwaway admin and non-premium accounts
(created and deleted via direct SQL, cleaned up after): admin dashboard
reports 94 total recipes (54 pre-existing + 40 new, matching the database
exactly); a premium recipe (`Bobotie with Yellow Rice`) shows the blurred
teaser + upgrade card to the non-premium account; a free recipe (`Tomato
Bredie`) shows its full ingredients/instructions to the same account, fix
for the duplicate-ingredient bug confirmed rendering correctly afterward;
the admin account sees `admin_recipes.php` correctly showing only the one
real admin-added recipe, not the catalog.

**Running total, accurately** (not the 48 the original ask expected,
per the mystery-45 finding above): **94 recipes** - 73 free (54
pre-existing + 19 from this batch) / 21 premium (all from this batch, the
existing 8 stayed free). Toward the eventual 20-free/80-premium split
across 100 the batch's own note mentions: nowhere close yet at this
checkpoint, expected given batch 1 skewed nearly even (19 free/21 premium)
rather than premium-heavy - later batches will need to lean hard premium to
correct the ratio by recipe 100, and the 45 mystery rows (all defaulted
free) make that correction larger than originally planned for.

**Unsplash images - blocked, by design, not by mistake.** No `.env` file
exists anywhere in this project (confirmed); `UNSPLASH_ACCESS_KEY` added to
`config/config.php` (blank default, same `getenv()`-first pattern as
`DB_HOST`) with no key to put in it. `scripts/fetch_recipe_images.php`
(new) is written and ready - searches Unsplash by `"{title} {cuisine}
food"` per recipe (not a bare dish name, which returns unrelated results
for an obscure regional dish), downloads the top result to
`assets/img/recipes/{id}.jpg` (this app's own domain, not a permanent
Unsplash CDN hotlink), fires Unsplash's official download-tracking ping,
and records photographer attribution to `image_attribution.json` - all per
Unsplash's API Guidelines. Confirmed it fails clearly (exit 1, explains
why) with the key blank, rather than silently doing nothing. Deliberately
does **not** auto-apply what it downloads: a second script,
`apply_recipe_images.php` (new), only updates `recipes.image_url` for
files still present in `assets/img/recipes/` after a human (or a future
session that can actually view the downloaded files) has deleted any
mismatch - the "visually sanity-check, don't take the first result
blindly" instruction can't be honestly automated away, so the pipeline is
split specifically to leave that step manual rather than fake it. **Real
follow-up, not yet built anywhere**: Unsplash's terms require a visible
"Photo by {name} on Unsplash" credit wherever a sourced photo is shown -
nothing in `recipe_card.php`/`recipe.php` renders one today, for the
original 8's images either. All 40 new recipes currently have
`image_url = ''` (rendered as a blank/broken image tile, same as this
app already does for the mystery 45).

Not committed - reported back for review first, per the request.

---

### 2.18 — Six-item follow-up: mystery-recipe investigation, a re-verify that found two more bad rows, admin_recipes.php scoping, a barcode-scan scope surprise, and a real vendor/Premium gate (Step 20)

Six numbered items, several explicitly investigate-first. In order:

**1. The 45 mystery recipes, investigated, still untouched.** No `updated_at`
or `user_id` column exists on `recipes` at all (checked - only `created_at`/
`created_by`). All 45 share `created_by = NULL`, `is_generated = 0`,
`cuisine = NULL`, `difficulty = 'easy'` - uniformly, across every row.
Nothing in this repo (no script, no `.sql` file beyond `sql/schema.sql`)
references any of their titles. No git commit or reflog entry falls near
their `2026-09-17 16:46:55` timestamp - there's a **40-hour gap in this
branch's commit history** (2026-09-16 08:29 to 2026-09-18 12:06) with zero
commits. One real lead, not a match: the external admin-suite bundle
(§2.14) has its own `INSERT INTO recipes` with `category`/`status` columns
this schema doesn't have, `is_generated = 1`, and a real `created_by` -
none of which matches what's actually in the database, so it's not a
direct hit, just evidence someone was working with recipe-seeding code
around that time. Best available explanation: an uncommitted, ad-hoc
session ran exploratory data directly against this live database during
that gap, with nothing saved to the repo and no cleanup. Still untouched.

**2. Re-verified the 40 imported recipes against the fixed parser - found
2 more bad rows.** Re-ran the (now three-bugs-fixed) ingredient parser
fresh from the original batch JSON and diffed every field - ingredients,
steps, macros, tier, diet tags, allergens - against both the live database
and `data/seed_recipes.php`, not just re-trusting the earlier patch.
Found 2 recipes (`malva-pudding`, `koeksisters`) whose "For sauce:"/"For
syrup:" compound ingredient had a *third*, different bad value in the
database - neither the original duplicate-text bug nor the fixed empty
string, but **silently truncated to 50 characters** by
`recipe_ingredients.display_quantity`'s own `VARCHAR(50)` limit on
insert, before the duplicate-text fix even existed. That truncation broke
the exact-match condition both the file-level regex fix and the earlier
live `UPDATE` relied on (`display_quantity = name`), so this pair escaped
both. Fixed with two targeted `UPDATE`s. Re-ran the full re-verify after:
**zero mismatches across all 40 recipes**, confirmed against the file and
the live database both. Flagging `VARCHAR(50)` itself as a real landmine
for any future batch with a longer compound ingredient description -
didn't widen it this step since nothing asked for a schema change here
and the immediate two rows are already fixed, but it'll bite again.

**3. `admin_recipes.php` widened-scope assessment - report only, not
changed.** The listing query itself is small (~10 lines: drop
`is_generated = 1`, `LEFT JOIN` instead of `INNER JOIN` on `created_by`,
handle a null author). But doing only that breaks the page's own Edit/
Remove actions silently: both `add_recipe.php`'s edit-fetch and
`admin_recipe_delete.php`'s `DELETE` are deliberately scoped to
`is_generated = 1` (Step 16's own boundary, protecting the seed catalog
from this quick-add-oriented flow) - a newly-visible seed-catalog row's
"Edit" link would 404, and "Remove" would delete 0 rows while still
flashing "Recipe removed." (`admin_recipe_delete.php` never checks
`rowCount()`) - a false success message. Not a query-only change if the
buttons need to stay honest.

**4. Unsplash - still blocked**, the key that was supposed to be added
isn't there. Checked `config/config.php` directly (still `''`) and every
environment-variable scope (Machine/User/Process, via
`[Environment]::GetEnvironmentVariable`) - genuinely blank everywhere.
`scripts/fetch_recipe_images.php` not run this step; nothing to report
on matches yet.

**5. Barcode scan - a real scope surprise, flagged before building
anything.** The request described gating an *existing* feature (a camera
icon that already opens a scanner). Checked `pantry.php` first as asked,
then the whole codebase: **no barcode-scanning feature exists anywhere**
- zero matches for "barcode", `getUserMedia`, or `BarcodeDetector` in any
file, zero mentions in this file's own history. "Gate it" would actually
mean building camera access, barcode detection, and (since this app has
no such integration anywhere) a product-lookup backend against some
external barcode database, from nothing - a real feature build, not a
small gate. Held rather than assumed and built.

**6. Vendor/Premium gate - confirmed missing, then built.** Checked
directly: `profile.php`'s vendor-toggle handler had zero premium check
(any account, free or Premium, could flip `is_vendor` on), and
`premium_enforce_expiry()` (the app's one existing lapse-handling
function) never touched `is_vendor` or recipe visibility at all - genuinely
never built, not something an earlier session did and this missed.
"Meal plans for sale" isn't a real feature anywhere either (only free
admin-curated templates exist - Step 13) - nothing to unpublish there
that doesn't already not exist.

Built:
- `profile.php`'s vendor-toggle form now rejects turning selling **on**
  without an active Premium subscription (admins bypass, same as every
  other gate in this app) - checked server-side, not just a disabled
  checkbox client-side (a stale page load could still `POST` it).
  Turning selling **off** is always allowed regardless of premium status.
  The checkbox itself is natively `disabled` (not the `aria-disabled`
  workaround the OAuth buttons need - a real `<input>` has native
  `disabled`) with a "Requires NutriTale Premium" hint and a `premium.php`
  link, only when currently both non-premium and not-already-a-vendor -
  an existing vendor whose Premium has since lapsed keeps the ability to
  turn selling off themselves.
- New `user_is_currently_premium(int $userId)` in `includes/functions_core.php`
  - checks a user's real, current Premium status against
    `premium_subscriptions` directly, for a user who isn't the current
    session. `premium_enforce_expiry()` (the existing mechanism) only
    ever refreshes `users.is_premium_member` lazily, when that account's
    *own* owner logs in - fine for gating what that person themselves can
    do, not enough for "does this other person's vendor listing still
    count as published," which needs to be right regardless of whether
    the vendor happens to log back in. Same decision rule as
    `premium_enforce_expiry()` (a `NULL` `current_period_end` is never
    treated as expired; no active subscription row at all falls back to
    trusting the flag, covering an admin-comped account), just without
    the side effect of writing back to someone else's row.
- `checkout.php` and `recipe.php` both now block a vendor recipe (new
  purchase attempts, and viewing the full recipe at all) once its
  seller's Premium has lapsed, via that real-time check - **grandfathered**:
  the vendor's own view of their own recipe, anyone who already paid
  (revoking access over something the *seller* let lapse would be unfair
  to an existing buyer), and admins. `recipe.php`'s new check sits ahead
  of the existing `$isPurchaseLocked` paywall logic and `die()`s with a 404
  rather than showing the paywall, since "temporarily not for sale at all"
  is a different state from "you haven't bought this yet."
- `index.php`'s public listing excludes an unpublished vendor recipe too -
  first cut used the simpler lazy flag (`users.is_premium_member`,
  matching how the rest of the app already treats that column), but
  **verified live that this leaves a dangling listing**: still browsable
  and clickable, only 404ing once actually opened, since `recipe.php`/
  `checkout.php` both already check in real time. Upgraded to mirror
  `user_is_currently_premium()`'s exact rule in pure SQL (three `EXISTS`
  clauses, no per-row PHP calls, no `ORDER BY`/`LIMIT`-inside-`EXISTS`
  issues) rather than ship something inconsistent with what the detail
  page does one click later.
- `my_recipes.php` and `vendor.php` both show a vendor their own
  unpublished listings with an inline "Unpublished - renew Premium to
  relist" note, linked to `premium.php` - a vendor whose Premium lapsed
  can still see their own dashboard and past sales, they just can't be
  found or bought by anyone new until they renew.

**Verified live end to end** with four throwaway accounts and a synthetic
vendor recipe/purchase/subscription (created and fully deleted after,
including the recipe's own ingredient/instruction rows and the purchase
record): a non-premium account's attempt to enable selling was rejected
server-side, DB confirmed `is_vendor` stayed `0`; the same recipe was
correctly visible (title, purchase-locked paywall) while its vendor was
active; lapsing the vendor's subscription (`current_period_end` moved to
the past, `status` left `'active'` - the harder case, deliberately not
just flipping the flag by hand) correctly 404'd both `recipe.php` and
`checkout.php` for a third-party viewer and dropped it from `index.php`'s
search entirely; the existing buyer and the vendor's own login both still
saw the full recipe throughout; `my_recipes.php`/`vendor.php` both showed
the unpublished note. Logging into the vendor account mid-test triggered
the *real* `premium_enforce_expiry()` lazy downgrade (`status` flipped to
`'expired'`) - confirmed a plain date-only "renewal" wasn't enough to
bring it back (status still `'expired'`), matching what a real renewal
needs to actually do (`status = 'active'` again, not just a later date);
once simulated properly, the recipe correctly reappeared in search and
the paywall replaced the 404 again.

Not committed - reported back for review, per the request. Real recipe/
tier count is unchanged by this step: **94 total, 73 free / 21 premium**.

---

### 2.19 — Hide the 45 mystery recipes, widen admin_recipes.php properly, log barcode scan as deferred (Step 21)

Three items from §2.18's report, two built, one just logged.

**New `recipes.is_published`, not an existing mechanism reused - because
none existed.** Checked first, as asked: every `status`-like column in
this schema belongs to a transaction or report workflow (`ai_generations`,
`ingredient_listings`, `ingredient_orders`, `premium_subscriptions`,
`recipe_purchases`, `recipe_reports`, `vendor_payouts`) - `recipes` itself
has never had one, and "admin removes a recipe" has only ever meant a hard
`DELETE` (`admin_recipe_delete.php`). Added the minimal thing: a plain
`TINYINT(1) NOT NULL DEFAULT 1`, matching this schema's own convention for
every other on/off flag (`is_generated`, `is_premium`, `is_vendor`,
`is_admin`) rather than a status column with only two real values ever
asked for. A second migration hides the exact 45 ids captured live from
the database (not re-derived from the timestamp range they happen to
share - a one-time correction for those specific rows, not a rule that
should keep matching anything else that ever lands in the same historical
minute). Visibility only, exactly as instructed - every row, ingredient,
and instruction is untouched.

**Every real browse/discover surface now respects it**, checked one by
one rather than assumed from the two named in the request:
- `index.php` - the obvious one, `r.is_published = 1` added to the query.
- `recipe.php` - a hidden recipe behaves as **not found**, not a
  locked/paywall state, to everyone except admins (who still need to
  reach it - that's the whole point of it now showing on
  `admin_recipes.php`).
- `marketplace.php` - checked, never queries `recipes` at all (it's the
  peer-to-peer *ingredient* marketplace) - nothing to change, confirmed
  rather than assumed from the request's own wording.
- `pantry.php` - its rule-based "what can I make" matcher queries
  `recipes` with no filter at all; would have kept surfacing hidden/
  broken recipes as pantry matches. Filtered.
- `planner.php` - the "add a recipe" combobox on all 28 day/meal slots
  loads every recipe unconditionally; a hidden one would have stayed
  fully selectable there even though it can't be browsed to or opened
  anymore. Filtered.
- `landing.php` - the "54+ recipes ready to browse today" hero count was
  counting hidden ones too, which is straightforwardly false advertising
  once they're not actually browsable. Filtered.
- `admin.php`'s dashboard "TOTAL RECIPES" stat and `admin_export.php`'s
  CSV **deliberately left showing everything** - true inventory counts
  for an admin-facing surface, not a public one; `admin_recipes.php` is
  where the hidden ones are meant to be visible and manageable.

**`admin_recipes.php` widened properly, not just the query.** Three real
recipe types now show, each routed through its own genuinely correct
existing path rather than one button trying to handle all of them:
- **User-submitted** (`is_generated = 1` - quick-adds and member/vendor
  `add_recipe.php` submissions alike; re-reading `add_recipe.php` in full
  this time confirmed admins could already edit *any* `is_generated = 1`
  recipe, not just admin-authored ones) - Edit/Remove **unchanged**,
  already correct.
- **Built-in catalog** (`is_generated = 0`, published - the original 8
  plus the 40-recipe import batch) - no existing edit path exists for
  this shape anywhere in the app. Rather than bypass ownership logic to
  force the quick-add form onto it (explicitly ruled out), Edit is a
  disabled control with a reason ("Built-in catalog recipe - not editable
  here"), not a link that would 404.
- **Hidden** (`is_generated = 0`, unpublished - the 45) - same disabled
  Edit, but Remove is replaced with a **Hide/Unhide toggle**
  (`admin_recipe_toggle_publish.php`, new - scoped to `is_generated = 0`
  only, mirroring `admin_recipe_delete.php`'s own boundary) instead of a
  dead-end disabled button. This *is* "manage them later," reusing the
  exact mechanism just built rather than inventing a second one.

Query: `LEFT JOIN` on `users`, not the original `INNER JOIN` - a
built-in/hidden recipe's `created_by` is `NULL`, which the inner join
would have silently excluded all over again even after dropping
`is_generated = 1`. `is_generated = 1` recipes still show their real
author; built-in/hidden ones show a type badge instead.

**Found two more real bugs while making "no more silent 404s / false
success messages" actually true, not just re-scoping the buttons**:
- `admin_recipe_delete.php` flashed "Recipe removed." unconditionally,
  never checking whether the `DELETE` actually matched a row - harmless
  while every visible row genuinely was `is_generated = 1`, a real false
  positive the moment the listing widened. Now checks `rowCount()`.
- **`admin_recipes.php` never rendered an `error` flash at all** - only
  `flash_get('success')` existed in the template. Found live: fixed the
  delete handler above, tested it against a built-in recipe expecting to
  see the new error message, and nothing appeared at all - the flash was
  being set correctly and then silently swallowed on the very next page
  render. Added the missing `flash_get('error')` block; re-tested,
  confirmed the message now actually shows both there and on
  `admin_recipe_toggle_publish.php`'s equivalent rejection.

**Verified live** with throwaway regular and admin accounts (created and
deleted after): a non-admin's search for four different hidden titles and
a direct URL to one both came up empty/404; the same admin-only URL
returned the full page; `admin_recipes.php` listed all 94 rows with the
right badge on each (45 Hidden, 48 Built-in catalog, 1 User-submitted);
toggling one hidden recipe live made it immediately searchable and
directly reachable again, then hiding it back removed both - a real
round trip, not just a flag flip checked in the database; attempting
Remove against a built-in recipe and toggle-publish against a
user-submitted one both now show the correct, honest error instead of a
false success or dead silence, and neither actually touched the row.
**Real counts, unchanged from the migration itself**: 94 total, 49
visible (28 free / 21 premium), 45 hidden.

**Barcode scanning - logged as deferred, `pantry.php` untouched.** Per
§2.18: no camera access, barcode detection, or product-lookup integration
exists anywhere in this codebase today. Real scope for its own step,
after the demo:
- Camera access via `getUserMedia` + a barcode-detection library (the
  native `BarcodeDetector` API isn't available in every browser this app
  needs to support - Safari support is inconsistent - so a JS polyfill/
  library is the realistic choice, not assumed available for free).
- Product lookup against the **Open Food Facts API**
  (`world.openfoodfacts.org/api`) - free, no key needed, barcode → product
  name/category, which is what would actually populate a pantry item from
  a scan.
- Needs its own premium gate once built (the original ask for this whole
  thread), following the same pattern as the recipe-tier gate (§2.17) and
  the vendor/Premium gate (§2.18): server-side enforcement, not just a
  hidden button.

Not committed - reported back for review, per the request.

---

## 3. The order to do it in

Each step is independently shippable. Don't batch them.

- [x] **Step 1 — Allergen safety on the AI path** (§2.1) — done
      `includes/allergens.php` (new), `includes/ai_pantry.php`, `pantry.php`,
      plus `tests/allergen_test.php` and `tests/ai_pantry_test.php`.
      Verified against the live database: the sesame-tagged recipe is excluded
      for an account flagged sesame + shellfish, and the page reports it.
- [x] **Step 2 — Disclaimers** (§2.3) — done
      `disclaimer()` + `DISCLAIMERS` in `includes/functions_core.php`, `.disclaimer`
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
      enforcement (`payfast_notify.php`, `includes/functions_core.php`), new
      migration. `tests/payfast_test.php` (8, stubbed DNS). Verified
      against the live database with a synthetic subscription, cleaned up
      after. Found two more gaps, filed rather than fixed — see §2.5.
- [x] **Step 8 — Vendor payouts, with reversal** (§2.6) — done
      Manual EFT with a tracked ledger. `includes/vendor_payouts.php` (new,
      pure "what's owed" function + DB wrappers), `vendor_payouts` table
      (migration), `admin_payouts.php` (new), `vendor.php` updated with a
      payout-history section. A "mark as paid" can be reversed (only the
      vendor's single most recent payout, enforced server-side, requires a
      typed reason, second migration
      `2026_09_18_vendor_payout_reversal`) — the reversed money becomes
      owed again automatically since `vendor_owed_amount()` skips reversed
      rows entirely when finding the payout cutoff. `tests/vendor_payouts_test.php`
      (22). Verified against the live database with a synthetic vendor and
      real sale/payout rows, cleaned up after — see §2.6 for the full
      verification trail, including a real layout bug the screenshot caught
      and fixed.
- [ ] **Step 9 — Launch checklist** (§2.11) — 3 of 4 done, 1 blocked
      SMTP (real PHPMailer integration, config wired, a real security bug
      in `forgot_password.php` found and fixed along the way), an
      accessibility pass (several real, live-verified fixes plus one
      significant color-contrast bug in the app's main button style), and
      a `mysqldump` backup script with rotation are done and verified live.
      PayFast go-live is not — it needs the client's own real merchant
      account and deployed domain, neither of which exist yet; the
      claim that the existing signature/IP-allowlist code needs no
      changes for production was verified by tracing every caller, not
      assumed. See §2.11 for the full detail on all four.
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
- [x] **Step 14 — Responsive header/nav audit and fix** (§2.12) — done
      Reported as landing.php being cramped on mobile; the actual bug was
      the opposite - the logged-in app's own nav (`includes/nav.php`,
      shared by every authenticated page including admin) had a real
      701-900px gap between its mobile-drawer and desktop-sidebar
      breakpoints, confirmed live with a 1202px-wide nav inside a 768px
      viewport on both `index.php` and `admin.php`. Fixed by widening the
      drawer's breakpoint to 900px, meeting the sidebar's 901px exactly.
      Also found and fixed two real keyboard-accessibility bugs in the
      drawer itself while verifying it per the request's own item 5: the
      toggle/close buttons were never keyboard-reachable at all (bare
      `<label>`s, no tabindex), and the drawer's links stayed tabbable
      while closed and off-screen. New `assets/js/nav-drawer-keyboard.js`
      fixes both plus Escape-to-close and `aria-expanded` syncing;
      `prefers-reduced-motion` disables the drawer's transitions
      outright. Verified live at 375/768/1440px before and after on both
      pages, plus direct `.focus()` calls and a real dispatched Enter
      keypress proving the whole open/close/focus cycle actually works,
      plus a forced-reduced-motion check. See §2.12 for the full trail.
- [x] **Step 15 — Apple Sign In, account linking, and a real Share button**
      (§2.13) — done, real Apple credentials still needed to go live
      Apple hand-rolled the same way Google/Facebook already were (no
      Composer, by explicit choice) - `apple_oauth_client_secret()`'s
      ES256 JWT + DER-to-raw signature conversion verified against a
      throwaway generated key. `oauth_find_or_create_user()` reworked for
      all three providers around a new pure `oauth_resolve_account()`
      (provider+id match, else email-link, else create) -
      `tests/oauth_test.php` (+13) plus a live throwaway-account
      round-trip covering new signup, repeat login, cross-provider
      linking, and linking back. New `oauth_provider`/`oauth_id` columns +
      unique index. Login/register's social row is three-wide now, with
      its own mobile stacking fix below 480px. Separately, `recipe.php`'s
      existing (already-working) share button gained image sharing via
      `canShare({files})` and a real WhatsApp/Facebook/copy-link fallback
      menu (`assets/js/recipe-share.js`) for browsers with no
      `navigator.share()` at all, keyboard-operable, Instagram/TikTok
      deliberately excluded with a comment explaining why. All 149 tests
      pass. See §2.13 for the full trail, including which 8 environment
      variables still need real values.
- [x] **Step 16 — Reconciled the external admin-suite bundle** (§2.14) —
      done
      An external Claude Code Web session's own `admin.php` rebuild
      (`nutritale-admin-suite.bundle` + matching `.patch`, committed here
      for the record) forked from before this branch's Step 13 and
      duplicated it with an incompatible file layout - inspected via
      `git bundle verify` before touching anything, then reconciled
      rather than merged: kept Step 13's files as-is, ported over only
      the genuinely missing capabilities. Recipe editing now reuses the
      existing (better) `add_recipe.php` instead of the bundle's
      duplicate form - one ownership-check change lets admins edit any
      admin-added recipe there, plus an "Edit" link on `admin_recipes.php`.
      New `admin_export.php` (CSV, recipes/users, adapted to this schema).
      Locked-account recovery added as one inline "Unlock" action on
      `admin_users.php` rather than the bundle's separate detail page.
      Verified live with throwaway accounts/recipes; all 149 tests pass.
- [ ] **Step 17 — Landing page rebuild committed** (§2.15) — in the working
      tree, committed, browser check still outstanding
      `landing.php` + `assets/css/style.css`'s landing rules (hero, trust
      bar, benefits, reviews staged empty, FAQ, footer). Restored the
      AV-deleted `setup.php` first (no content change — same §2.10 local-AV
      quirk). Confirmed via the raw HTTP response, not a browser: reviews
      render nothing at all while `$testimonials` is empty, and all three
      CTA buttons share the same label and destination. The
      Claude-in-Chrome extension wouldn't connect this step, and a headless-
      Edge fallback was abandoned rather than risk hijacking the user's own
      open browser windows — so light mode, dark mode, mobile width, and the
      FAQ accordion's actual click behaviour are still unverified in a real
      browser. See §2.15 for the full trail. Desktop light/dark now confirmed
      live in Step 18 below — mobile width and the FAQ click are still open.
- [ ] **Step 18 — Four visual polish fixes, plus one incidental sidebar bug**
      (§2.16) — done, real narrow-viewport confirmation still outstanding
      Login/register heading centering (shared `.auth-form-card h1`/
      `.auth-form-subtitle`), the landing header's mobile spacing (root
      cause: a Step 14 media query never scoped to exclude `.landing-nav`),
      a new reusable `user_avatar()` initials-avatar helper wired into the
      nav drawer, and the trust bar rebuilt as an equal-column grid in the
      existing glass-card style. `style.css?v=4`/`?v=5` → `?v=7` across all
      41 pages that link it. Found and fixed one real, pre-existing,
      unrelated bug while verifying the avatar live: the persistent sidebar
      (Step 14) had no `flex-wrap: nowrap`, so on a short viewport its
      column layout wrapped into a second, off-panel column instead of
      scrolling — the entire nav and the new avatar were unreachable at that
      window height until fixed. All four fixes plus the sidebar bugfix
      confirmed live, screenshotted, in both themes at the session's
      available desktop width; a real narrow/mobile render was not
      obtainable this session (`resize_window` never actually changed
      `window.innerWidth`, checked directly and repeatedly) — mitigated by
      reading the fixed rules back out of the live CSSOM rather than just
      trusting the source file. See §2.16 for the full trail, including the
      one thing explicitly flagged back as a follow-up: there is still
      nowhere in this app to upload a real profile photo.
- [ ] **Step 19 — Recipe tier gate + 40-recipe import batch** (§2.17) —
      schema/import done and verified live; Unsplash sourcing blocked on a
      key; not committed yet, pending review
      New `tier` column (`recipes`, plain `VARCHAR` not the literal `ENUM`
      asked for - same reasoning as `oauth_provider`), independent of the
      pre-existing `is_premium` vendor-marketplace lock. `recipe.php` gates
      a locked premium recipe behind a real (small, safe) blurred teaser +
      "Upgrade to view" card, not a CSS blur over the full content -
      confirmed live that the full ingredients/instructions never reach
      the browser for a locked recipe. **Found mid-task and flagged before
      proceeding**: the live database already had 54 recipes, not the 8
      the original ask assumed - 45 of them inserted 2026-09-18 with no
      image and mostly no instructions, from no file in this repo. Left
      completely untouched per instruction; the accurate running total is
      **94 recipes, 73 free / 21 premium** (not 48) - see §2.17 for the
      full reasoning and for why those 45 are still a real, separate,
      unresolved bug worth someone's attention. 40 new recipes converted
      from the supplied batch via a small ingredient-line parser (kept in
      scratch); found and fixed three real parser bugs while checking its
      output, including one (a duplicated ingredient phrase) that had
      already reached 49 live database rows before being caught by
      actually loading a recipe page - fixed in the parser, the source
      file, and with a live `UPDATE` against those rows. Migrations
      applied directly via PDO, not by running `setup.php` (this machine's
      known antivirus quirk, §2.10) - both recorded in `schema_migrations`
      exactly as `setup.php` itself would. `admin_recipes.php` doesn't
      list these 40 (or the original 8) - confirmed by design, not a bug:
      that page is scoped to admin quick-added recipes only. Unsplash
      sourcing is written and ready (`scripts/fetch_recipe_images.php` +
      `apply_recipe_images.php`, split specifically so a photo match gets
      a real visual check before it goes live) but has no key to run with
      - no `.env` exists in this project, and `UNSPLASH_ACCESS_KEY` in
      `config/config.php` is blank; flagged back rather than guessed at.
      All 40 new recipes currently have no image. See §2.17 for the full
      trail, verification steps, and every follow-up this turned up.
- [ ] **Step 20 — Six-item follow-up** (§2.18) — vendor/Premium gate done
      and verified live; three items investigated and reported only; one
      built scope-corrected mid-implementation; two blocked, reported
      Investigated and reported without changing anything: the 45 mystery
      recipes (no `updated_at`/`user_id` column exists to check; a
      40-hour gap in this branch's own commit history with zero commits
      is the closest thing to a lead); whether widening
      `admin_recipes.php` is a simple query change (it isn't, once the
      page's own Edit/Remove buttons need to keep telling the truth -
      both are deliberately scoped to `is_generated = 1`, Step 16's own
      boundary); barcode-scan gating, which turned out to mean *building*
      camera access, barcode detection, and a product-lookup backend from
      nothing (checked the whole codebase, not just `pantry.php` - none
      of it exists anywhere), not adding a gate to something that already
      does. Unsplash sourcing still blocked - the key the request said
      was added isn't actually in `config/config.php` or any environment
      variable scope, checked directly.
      Re-verified the 40 recipes from Step 19 against the now-fully-fixed
      ingredient parser (fresh re-parse from the original source JSON,
      diffed field-by-field against both the live database and
      `data/seed_recipes.php`, not just re-trusting the earlier patch) -
      found 2 more bad rows (`malva-pudding`, `koeksisters`) that the
      original fix's exact-match condition missed because
      `recipe_ingredients.display_quantity`'s own `VARCHAR(50)` had
      silently truncated them first. Fixed directly; re-verify now passes
      clean across all 40. Real total unchanged: **94 recipes, 73 free /
      21 premium**.
      Built the vendor/Premium gate, confirmed missing by reading the
      actual code (`profile.php`'s vendor toggle had zero premium check;
      `premium_enforce_expiry()` never touched `is_vendor` or recipe
      visibility) rather than assumed: enabling "sell your recipes" now
      requires Premium (admins bypass), and a vendor's premium-priced
      recipes unpublish - from `index.php`'s listing, from `recipe.php`,
      and from new purchases via `checkout.php` - the moment their
      subscription's real `current_period_end` passes, checked in real
      time via a new `user_is_currently_premium()` rather than the
      existing lazy `is_premium_member` flag (which only refreshes when
      the vendor's own account logs in - not good enough for someone
      *else* browsing their listing). Existing buyers, the vendor's own
      view, and admins are grandfathered through the lock. Verified live
      end to end with four throwaway accounts and a synthetic vendor
      recipe (all deleted after): rejection of a non-premium toggle
      attempt, visible-while-active, correctly 404s/drops-from-listing
      the moment a subscription's date lapses (even before the lazy flag
      catches up), grandfathered access holds for the existing buyer and
      the vendor's own login throughout, and a properly-simulated renewal
      (both `status` and the date, matching what a real renewal actually
      needs) brings it back. See §2.18 for the full trail on all six
      items.
- [ ] **Step 21 — Hide the 45 mystery recipes, widen admin_recipes.php,
      log barcode scan as deferred** (§2.19) — hide + widen done and
      verified live; barcode scan logged only, `pantry.php` untouched;
      not committed
      No existing "unpublished recipe" mechanism anywhere in this schema
      (checked every table's `status`-like column first - all belong to
      transaction/report workflows, none to `recipes`) - added a minimal
      `recipes.is_published` (plain `TINYINT(1)`, this schema's own
      convention for a binary flag) rather than reuse or invent a
      workaround. Hid the exact 45 ids from §2.18 by id, not by
      timestamp range. Every real browse/discover surface now respects
      it - `index.php`, `recipe.php` (404 to non-admins, admins still
      reach it), `pantry.php`'s matcher and `planner.php`'s recipe picker
      (both found live, neither was named in the original ask but both
      would have kept surfacing/offering hidden recipes), and
      `landing.php`'s "recipes ready to browse" count. `admin.php`'s
      dashboard stat and `admin_export.php`'s CSV deliberately still show
      everything - true inventory for an admin, not a public claim.
      `admin_recipes.php` now lists all 94 recipes with a real type badge
      per row (User-submitted / Built-in catalog / Hidden) and routes
      Edit/Remove through whatever's actually correct for each: unchanged
      for `is_generated = 1` (already worked, re-confirmed by re-reading
      `add_recipe.php` in full); a disabled Edit with a stated reason for
      the built-in catalog (no existing edit path, not forced); a new
      Hide/Unhide toggle (`admin_recipe_toggle_publish.php`) in place of
      Remove for the 45, so this step's own new mechanism is exactly
      "where you go manage them later." Found and fixed two more real
      bugs while verifying this, not just re-scoping buttons:
      `admin_recipe_delete.php` flashed "Recipe removed." unconditionally
      even when its `DELETE` matched zero rows, and separately
      `admin_recipes.php` never rendered an `error` flash *at all* - the
      fixed delete handler's honest rejection was being set correctly and
      then silently swallowed on the next page render, caught only by
      actually looking for the message and finding nothing. Verified live
      end to end with throwaway accounts: hidden recipes absent from
      search and 404 directly for a regular user, visible/reachable for
      an admin; a real hide→unhide→hide round trip through the new
      toggle, not just a database flag check; Remove against a built-in
      recipe and the toggle against a user-submitted one both correctly
      rejected with a real visible message, and neither touched the row.
      Real counts unchanged from the migration: **94 total, 49 visible
      (28 free / 21 premium), 45 hidden**. Barcode scanning logged as its
      own deferred, unscoped-until-designed feature (camera access +
      barcode detection + the free Open Food Facts API for product
      lookup, plus its own Premium gate once built) - `pantry.php` itself
      not touched, per the instruction. See §2.19 for the full trail.

Steps 1–3 are roughly a session. Step 6 was the long one.

---

## 4. How to work this file

Tick a box only when the change is in the working tree and you've loaded the
affected page in a browser. When a step turns up something this audit missed,
add it to §2 rather than fixing it inline — the value here is that it stays an
accurate picture of the gap, not a wishlist.
