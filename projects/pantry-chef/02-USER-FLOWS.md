# 02 — User Flows & Screen Map

---

## Screen map

```
PUBLIC
  /                     Landing page
  /login                Sign in
  /signup               Create account
  /legal/privacy        Privacy policy
  /legal/terms          Terms

AUTHENTICATED
  /onboarding           3-step wizard (first run only)
  /pantry               HOME. The pantry + the generate button.
  /generating           Transient loading state (can be a modal instead of a route)
  /recipe/[id]          Recipe detail
  /saved                Saved recipes
  /profile              Preferences, account, sign out
```

Seven real screens. If the count starts creeping toward fifteen, something from the v2
list has snuck into v1.

---

## Flow 1 — First-time user (the critical path)

```
Landing (/)
   │  clicks "Get started free"
   ▼
Signup (/signup)
   │  email + password, or Google
   ▼
Onboarding step 1 — Allergens
   │  "Anything you can't eat?"
   │  14 chips, multi-select, "None of these" option
   ▼
Onboarding step 2 — Diet
   │  "How do you eat?"
   │  8 chips, multi-select, omnivore preselected
   ▼
Onboarding step 3 — Health goal
   │  "What does healthy mean for you?"
   │  5 cards, single-select, "balanced" preselected
   │  Fine print: "This isn't medical advice."
   ▼
Pantry (/pantry) — EMPTY STATE
   │  "Let's fill your kitchen. Add a few things you have."
   │  Quick-add chips for 12 common staples (eggs, rice, onion, …)
   │  Search field, focused automatically
   │
   │  user adds ≥ 3 ingredients
   ▼
Generate button becomes enabled and visually prominent
   │  taps "Find me a recipe"
   ▼
Generating (loading, 6–12s)
   │  progress copy that changes every ~3s so it doesn't feel frozen
   ▼
Recipe detail (/recipe/[id])
   │  title, image placeholder, time, servings, nutrition
   │  what you have / what you're missing
   │  ingredients, steps
   │  [Save]  [Another one]
   ▼
DONE — user has reached value
```

**Design rule for this flow:** every step is skippable except allergens, and the whole
thing is under 90 seconds. Onboarding that asks fifteen questions loses the user before
they see a single recipe.

---

## Flow 2 — Returning user (the loop that matters)

```
Open app → /pantry (pantry already populated from last time)
   │  adjusts a couple of items (bought chicken, used the spinach)
   ▼
"Find me a recipe"
   ▼
Recipe detail
   ├── likes it   → Save → cooks
   └── doesn't    → "Another one" → new recipe, previous title excluded
```

This is the loop the whole product lives or dies on. It should be possible in **three
taps** from cold open. If a returning user needs more than three, the pantry screen is
doing too much.

---

## Flow 3 — Regenerate

```
Recipe detail → "Another one"
   │
   ├─ Same pantry, same preferences
   ├─ Previously-seen recipe titles sent as exclusions
   ├─ Cuisine-variety hint rotated
   ▼
New recipe, replaces the current one in view
   │
   └─ Rate limit reached?
        → Friendly cap message + when it resets
          "You've generated 20 recipes today. Fresh batch at midnight."
```

Regeneration is where cost runs away. The rate limit is part of the flow, not an error
state bolted on later — write the copy for it now.

---

## Flow 4 — Not enough ingredients

```
Pantry has < 3 usable ingredients
   ▼
Generate button is DISABLED (not hidden — hidden is confusing)
   ▼
Helper text under it: "Add at least 3 ingredients to get started."
   ▼
Quick-add chips shown for common staples
```

Never send a generation request that's set up to fail. Blocking the button is cheaper and
kinder than an AI apologising for having nothing to work with.

---

## Flow 5 — Allergen conflict caught after generation

Internal, invisible to the user when it works:

```
Recipe generated
   ▼
Code-level allergen check against the user's list
   │
   ├─ PASS → show recipe
   └─ FAIL → discard silently, regenerate with a hardened instruction
              │
              └─ fails twice more?
                   → show an honest error:
                     "We couldn't build a safe recipe from these ingredients.
                      Try adding a few more."
                   → log it. Repeated failures mean the prompt needs work.
```

The user must never see a recipe that failed the check — not even briefly, not greyed
out, not with a warning. Discard and retry.

---

## Flow 6 — Editing preferences

```
/profile
   ├── Allergens      → edit → save → affects all FUTURE generations
   ├── Diet           → edit → save
   ├── Health goal    → edit → save
   ├── Account        → change email / password / delete account
   └── Sign out
```

Changing preferences does **not** retroactively re-check saved recipes. But if a newly
added allergen conflicts with something already saved, flag it on the saved list:
*"Contains an ingredient you've marked as an allergen."* Cheap to build, and exactly what
a careful person would expect.

---

## Empty and error states (write the copy now, not later)

| Situation | What the user sees |
|-----------|--------------------|
| Pantry empty | Illustration + "Let's fill your kitchen" + quick-add chips |
| Pantry < 3 items | Disabled button + "Add at least 3 ingredients to get started." |
| No saved recipes | "Nothing saved yet. When you find a keeper, tap the bookmark." |
| Generation failed (network) | "Something went wrong on our end. Try again?" + retry button |
| Generation failed (no safe recipe) | "We couldn't build a safe recipe from these. Try adding a few more ingredients." |
| Rate limited | "You've hit today's limit of 20 recipes. Resets at midnight." |
| Offline | "You're offline. Your pantry is saved — we'll generate when you're back." |
| Search returns nothing | "No match for 'quinoaa'. Add it as a custom ingredient?" |

Every one of these is a real moment a real person will hit. Unwritten empty states are
where an app stops feeling finished.

---

**Next:** [`03-DESIGN-SYSTEM.md`](./03-DESIGN-SYSTEM.md)
