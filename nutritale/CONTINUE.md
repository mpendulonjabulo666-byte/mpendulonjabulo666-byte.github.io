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
| Admin | `admin.php` `admin_recipe_delete.php` | Done. Admins bypass premium gates |
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

### 2.2 — Ingredient matching is naive substring comparison

`pantry.php:70` does `str_contains($ingNorm, $p) || str_contains($p, $ingNorm)`.
So pantry "ice" matches recipe "rice" and "juice"; "oil" matches "boiled eggs".
And nothing maps "tomatos" → "tomato" or "passata" → "tomato".

`00-START-HERE.md` calls this *"the single most underestimated part of every
recipe app ever built"* and it's right. Needs a canonical-ingredient table plus
an alias table, and word-boundary matching instead of substring.

### 2.3 — No disclaimers anywhere

`grep -i "disclaimer\|estimate\|medical advice"` across every PHP file returns
**zero hits**. The spec lists three as non-optional:

- Nutrition figures are estimates, not for medical use — on every recipe showing
  macros (`recipe.php:172-177`)
- Allergen filtering isn't a guarantee, check labels — next to the allergen
  filter, not in a footer
- Not medical advice — in the health-goal step of `onboarding.php`

Three small components. Removes a whole category of risk.

### 2.4 — AI cost is unbounded for paying users

`pantry.php:37-41`: free users burn one of `PANTRY_FREE_USES` (3) per AI call.
Premium and admin users are checked by `$isBlocked` — which is false for them —
so there is **no ceiling at all**. No cache, no per-day cap, no logging of what
was spent.

There's also a smaller bug right above it: `pantry.php:28-30` increments the
same counter when a free user merely **adds a pantry ingredient**. Adding three
ingredients exhausts the AI trial before they ever press the button. That is
almost certainly not intended.

No table records generations, so cost can't be measured even retrospectively.

### 2.5 — Payment hardening left for go-live

Self-documented at `payfast_notify.php:9-11`, and correct as far as it goes:

- No PayFast source-IP allowlist on the ITN endpoint
- Subscription renewals skip the amount check that once-off purchases get
  (`payfast_notify.php:82-87` and `102-107` verify `amount_gross`; the
  subscription branch at `62-74` does not)
- `premium_subscriptions` has no period-end column — `is_premium_member` flips
  on and stays on until a cancellation ITN arrives. A silently failed renewal
  leaves someone premium forever
- Config still ships sandbox credentials (correct default, must change)

### 2.6 — Vendors can see earnings but cannot be paid

`vendor.php` shows net revenue after `PLATFORM_COMMISSION_PCT`. All money lands
in the platform's PayFast account and there is no payout record, no payout
status, no mechanism. Sell one recipe for real and you owe a vendor money with
nothing tracking it. Decide the model before real payments go live.

### 2.7 — `install.php` can create but never upgrade

`sql/schema.sql` is all `CREATE TABLE IF NOT EXISTS` and `install.php` has no
`ALTER` statements. Every schema change in §2.1–2.5 below will apply cleanly to
a fresh install and silently do nothing to a deployed one. A migration path is
needed before the first schema change ships.

### 2.8 — Email delivery is best-effort

`functions.php:93-99` uses `@mail()` with the error suppressed. Many hosts drop
it silently. Already noted in `DEPLOYMENT.md`; repeated here because review
notifications quietly not arriving is the kind of thing nobody notices for
weeks.

---

## 3. The order to do it in

Each step is independently shippable. Don't batch them.

- [x] **Step 1 — Allergen safety on the AI path** (§2.1) — done
      `includes/allergens.php` (new), `includes/ai_pantry.php`, `pantry.php`,
      plus `tests/allergen_test.php` and `tests/ai_pantry_test.php`.
      Verified against the live database: the sesame-tagged recipe is excluded
      for an account flagged sesame + shellfish, and the page reports it.
- [ ] **Step 2 — Disclaimers** (§2.3)
      One `disclaimer()` helper in `includes/functions.php`, three variants,
      placed as listed. Pairs naturally with Step 1.
- [ ] **Step 3 — Fix the trial counter** (§2.4, second half)
      Stop charging a use for adding an ingredient. Charge only `ai_suggest`.
      Small, self-contained, currently costing real users their trial.
- [ ] **Step 4 — Migration runner** (§2.7)
      Needed before any further schema change. A `schema_migrations` table and
      an ordered list of statements `install.php` applies once each.
- [ ] **Step 5 — AI generation log + cache + daily cap** (§2.4)
      New `ai_generations` table (user, pantry hash, outcome, timestamp).
      Same pantry + same prefs within N days serves the stored result. Hard
      daily cap for premium. Makes cost visible and bounded.
- [ ] **Step 6 — Ingredient normalisation** (§2.2)
      `ingredients` canonical table + `ingredient_aliases`, seeded. Replace
      substring matching. Biggest quality win, biggest effort — do it once the
      safety work is behind you.
- [ ] **Step 7 — Payment hardening** (§2.5)
      IP allowlist, renewal amount check, `current_period_end` on
      subscriptions with expiry enforcement.
- [ ] **Step 8 — Vendor payouts** (§2.6)
      Decide the model first (manual EFT with a tracked ledger is a legitimate
      v1). Then build to that decision.
- [ ] **Step 9 — Launch checklist**
      `DEPLOYMENT.md` § "Going live with PayFast", real credentials, SMTP,
      accessibility pass, `mysqldump` cron.

Steps 1–3 are roughly a session. Step 6 is the long one.

---

## 4. How to work this file

Tick a box only when the change is in the working tree and you've loaded the
affected page in a browser. When a step turns up something this audit missed,
add it to §2 rather than fixing it inline — the value here is that it stays an
accurate picture of the gap, not a wishlist.
