# 01 — Product Requirements

**Codename:** PantryChef
**Version:** v1.0 (MVP)
**Market:** South Africa
**Status:** Draft — pending client sign-off

> **Read [`12-SOUTH-AFRICA.md`](./12-SOUTH-AFRICA.md) alongside this file.** It revises the
> personas, adds a sixth health goal, adds budget mode and load-shedding mode, and replaces
> GDPR with POPIA. Where the two differ, file 12 wins.

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

Full detail in [`12-SOUTH-AFRICA.md`](./12-SOUTH-AFRICA.md) § 9. In short:

### Primary — Thandi, 29, Soweto
Works full time, commutes, cooks for herself and her mother. Monthly shop at Shoprite plus
spaza top-ups. Mid-range Android, watches her data. **Wants:** to eat better without
spending more or cooking anything unfamiliar.

### Secondary — Sipho, 45, Durban
Recently diagnosed with type 2 diabetes. Told to "eat healthier" and handed a pamphlet.
**Wants:** to keep eating the food he grew up with, cooked in a way that won't hurt him.
*This is the user who most justifies the product existing.*

### Tertiary — Lerato, 21, Cape Town
Student. Res kitchen, one hot plate, tiny budget, limited skills. **Wants:** cheap, fast,
one-pot.

All three want the same core loop. Thandi defines the default experience; Sipho justifies
the health-goal system; Lerato justifies budget mode and load-shedding mode.

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
| F17 | **Budget mode** toggle | For much of this market, "healthy" and "affordable" are the same question |
| F18 | **Load-shedding mode** (cooking constraint) | An electric stove isn't always available. Cheap to build, immediately obvious value |
| F19 | POPIA consent capture at onboarding | Health data has special protection under South African law |
| F20 | Local-language ingredient aliases | Typing "mielie meal" or "morogo" must work |

F17–F20 are specified in [`12-SOUTH-AFRICA.md`](./12-SOUTH-AFRICA.md).

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
| `blood_sugar_friendly` | Lower-GI starches, fibre alongside carbs, no added sugar. **Added for the South African market** — see `12-SOUTH-AFRICA.md` § 2. |

Deliberately six, not fifteen. Each one must be visibly different in output or it isn't
earning its place in the UI.

`blood_sugar_friendly` is **not** the same as `low_carb`. In a country where starch is the
foundation of most meals, "remove the starch" is advice people ignore. "Choose samp over
refined maize meal, and eat beans with it" is advice they can act on.

**Budget mode and cooking constraint are separate settings, not health goals.** They're
orthogonal — someone can want high-protein *and* cheap *and* cookable on a gas ring.

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
