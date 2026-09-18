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
| Admin | `admin.php` `admin_recipes.php` `admin_categories.php` `admin_users.php` `admin_reports.php` `admin_payouts.php` `admin_meal_plans.php` `admin_analytics.php` `admin_settings.php` `admin_profile.php` | Done — real dashboard stats, recipe reports/moderation, user role management, a manual-EFT vendor payout ledger, admin-curated meal plan templates, real analytics (signups/views/AI usage), four enforced platform toggles including maintenance mode. Admins bypass premium gates |
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
refusing to send. `send_notification_email()` (`includes/functions.php`)
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

Steps 1–3 are roughly a session. Step 6 was the long one.

---

## 4. How to work this file

Tick a box only when the change is in the working tree and you've loaded the
affected page in a browser. When a step turns up something this audit missed,
add it to §2 rather than fixing it inline — the value here is that it stays an
accurate picture of the gap, not a wishlist.
