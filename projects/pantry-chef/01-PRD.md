# 01 — Product Requirements

**Codename:** PantryChef
**Version:** v1.0 (MVP)
**Status:** Draft — pending client sign-off

---

## 1. Problem

People open the fridge, see food, and still order takeaway. Not because they can't cook,
but because turning "half a cabbage, three eggs, and some rice" into a decision takes more
energy than they have at 7pm. Recipe sites solve the opposite problem — they assume you've
already picked a dish and are willing to go shopping.

The gap: **no shopping trip, no decision fatigue, no wasted food.** Start from what's
already in the house.

## 2. Solution

The user maintains a lightweight list of what they have. One tap produces a healthy recipe
built from that list, tuned to their dietary needs and health goal, with clear steps and
rough nutrition.

## 3. Who it's for

### Primary — "Weeknight Wren"
Works full time, cooks 4–5 nights a week, cares about eating well but isn't tracking
macros. Has a half-stocked fridge and 30 minutes. **Wants:** a decision made for them from
what they already own.

### Secondary — "Goal-driven Gugu"
Actively working toward something — cutting, bulking, managing blood sugar. Reads
nutrition labels. **Wants:** recipes that fit a specific target, not generic "healthy".

### Tertiary — "Frugal Fikile"
Tight budget, hates throwing food away, plans around what's about to expire.
**Wants:** zero waste and no extra shopping.

All three want the same core loop. Wren defines the default experience; Gugu justifies the
health-goal system; Fikile justifies the "use what expires first" nudge in v2.

## 4. Value proposition

> Stop staring at your fridge. Tell us what's in it — get a healthy meal you can cook
> tonight, no shopping required.

## 5. Features — v1 (in scope)

| # | Feature | Why it's in v1 |
|---|---------|----------------|
| F1 | Email/password + Google sign-up | Recipes and pantry must persist across devices |
| F2 | Onboarding: allergens, diet, health goal | Safety-critical and defines "healthy" |
| F3 | Pantry: add / remove / search ingredients | The core input |
| F4 | Ingredient autocomplete from a seeded catalogue | Makes input fast; enables normalisation |
| F5 | Category grouping in pantry (Produce, Protein, Dairy, Grains, …) | Ten items is a list; forty is a mess |
| F6 | Generate recipe from pantry + preferences | The core output |
| F7 | Recipe detail: ingredients, steps, time, servings, nutrition | The deliverable |
| F8 | "Missing ingredients" callout (max 2) | Recipes get far better with a tiny tolerance |
| F9 | Save / unsave recipe | Retention |
| F10 | Saved recipes list | Retention |
| F11 | Regenerate ("give me another") | First result isn't always right |
| F12 | Servings scaler | Cooking for 1 vs 4 |
| F13 | Marketing landing page | Acquisition |
| F14 | Profile: edit allergens, diet, goal | Preferences change |
| F15 | Rate limiting + generation counter | Cost protection |
| F16 | Nutrition and allergen disclaimers | Legal / ethical baseline |

## 6. Explicitly OUT of scope for v1

Write this section down and get the client to read it. It is the difference between a
finished project and a project that never ends.

- Photo-of-your-fridge ingredient detection
- Barcode scanning
- Weekly meal planning / calendars
- Auto-generated shopping lists
- Grocery-delivery integrations
- Social feed, comments, following, sharing to a profile
- User-submitted recipes
- Offline mode
- Native iOS/Android apps (v1 is a responsive web app — installable, but web)
- Multi-language
- Voice input
- Expiry-date tracking and "use this first" nudges
- Cost-per-meal estimation

Each of these is a reasonable v2 conversation. None of them is v1.

## 7. Success metrics

| Metric | Target for v1 |
|--------|---------------|
| Signup → first generated recipe | ≥ 60% |
| Median ingredients in pantry after onboarding | ≥ 8 |
| Recipes saved per active user per week | ≥ 1 |
| Week-1 retention | ≥ 30% |
| Median generation latency | < 12s |
| Cache hit rate on generation | ≥ 25% by week 4 |
| Allergen violations reaching a user | **0** — this is a hard failure, not a metric to optimise |

## 8. Health goals (v1 set)

The user picks exactly one. It's editable at any time.

| Goal | What the generator optimises for |
|------|----------------------------------|
| `balanced` | Sensible default. Vegetables present, whole grains preferred, moderate portions. |
| `high_protein` | ≥ 30g protein per serving where ingredients allow. |
| `low_carb` | ≤ 30g net carbs per serving. |
| `heart_healthy` | Low saturated fat, low sodium, fibre-forward. |
| `weight_loss` | ≤ 500 kcal per serving, high satiety (protein + fibre + volume). |

Deliberately five, not fifteen. Each one must be visibly different in output or it isn't
earning its place in the UI.

## 9. Diets (v1 set)

Multi-select, applied as hard constraints on generation.

`omnivore` (default) · `vegetarian` · `vegan` · `pescatarian` · `halal` · `kosher` ·
`gluten_free` · `dairy_free`

## 10. Allergens (v1 set)

Multi-select. **Hard exclusions, double-enforced** (prompt + code check).

`peanuts` · `tree_nuts` · `milk` · `eggs` · `fish` · `shellfish` · `soy` · `wheat` ·
`sesame` · `mustard` · `celery` · `sulphites` · `lupin` · `molluscs`

This is the EU's 14 major allergens. Using an established list rather than inventing one
means the client can point at a standard if anyone ever asks.

## 11. Constraints and assumptions

**Constraints**
- Solo developer, part-time.
- Budget for AI generation is real and finite. Cost control is a v1 feature, not v2.
- No nutritionist on the team. The app gives estimates and says so.

**Assumptions** (verify with the client — see `11-CLIENT-BRIEF.md`)
- Web-first is acceptable; native apps are not expected in v1.
- The client will supply or approve the initial ingredient catalogue (~500 items).
- Free at launch; monetisation is a post-launch conversation.

## 12. Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Allergen appears in a generated recipe | Medium | **Severe** | Two-layer enforcement + regenerate-on-fail + disclaimer. See `08-AI-ENGINE.md`. |
| Generation costs exceed budget | Medium | High | Caching, rate limits, per-user monthly cap, model choice reviewed monthly. |
| Ingredient matching is poor, recipes feel wrong | High | High | Seeded canonical catalogue + alias table + autocomplete-first input. |
| Recipes are bland or repetitive | Medium | Medium | Cuisine variety hint in prompt; regenerate excludes previous titles. |
| Scope creep from the v2 list | High | High | § 6 exists; get it signed. |
| Client expects native apps | Medium | Medium | Clarify in the kickoff — it's a blocking question. |

## 13. Open questions

Tracked in full in [`11-CLIENT-BRIEF.md`](./11-CLIENT-BRIEF.md). The two that block the
data model:

1. Is one pantry per user enough, or do households share a pantry? (Changes ownership on
   the `pantries` table.)
2. Do we need to store generated recipes permanently, or only ones the user saves?
   (Changes retention, storage cost, and cache design.)

---

**Next:** [`02-USER-FLOWS.md`](./02-USER-FLOWS.md)
