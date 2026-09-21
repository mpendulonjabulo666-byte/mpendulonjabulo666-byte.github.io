# 05 — Page Specifications

Section-by-section for every screen. Build from this; don't improvise layout.

---

## `/` — Landing page

Public. Its only job: get the right person to sign up in under thirty seconds.

### Section 1 — Hero
- **Headline:** *"What's in your fridge? Let's make dinner."*
- **Sub:** *"Tell us what you've got. We'll give you a healthy recipe you can cook
  tonight — no shopping trip required."*
- **Primary CTA:** `Get started free` → `/signup`
- **Secondary:** `See how it works` → scrolls to section 3
- **Visual:** an interactive mock of the pantry. Ingredient chips animate in one by one,
  then a recipe card appears. If that's too much for week one, use a static screenshot —
  but it's worth building, because it demonstrates the product instead of describing it.
- Full-height on desktop, natural height on mobile.

### Section 2 — The problem
Three short cards, icon + one line each:
- 🗑️ **Food goes off** — "The average household bins a third of what it buys."
- 😵 **Decision fatigue** — "You know how to cook. You just don't know what."
- 🥡 **Takeaway wins** — "Not because you wanted it. Because it was easier."

### Section 3 — How it works
Three numbered steps, horizontal on desktop, stacked on mobile:
1. **Add what you have** — small pantry screenshot
2. **Tell us how you eat** — allergen/goal chips
3. **Cook something good** — recipe card

### Section 4 — Feature grid
Six tiles: personalised to your diet · allergen-aware · nutrition estimates · save your
favourites · scale to any number of servings · works on any device.

### Section 5 — Sample recipes
Three static, pre-generated `<RecipeCard>`s. Proof that the output is actually appetising.
Real photos here matter more than anywhere else on the site.

### Section 6 — FAQ
Accordion: *Is it free? · How accurate is the nutrition? · What about allergies? · Do I
need to enter exact quantities? · Can I use it on my phone?*

The allergy answer must be honest and match `<Disclaimer variant="allergen">` word for
word.

### Section 7 — Final CTA
Full-width primary band, one button: `Get started free`.

### Footer
Links, legal, contact. Nothing clever.

**SEO:** proper `<title>`, meta description, Open Graph image, `Organization` and `FAQPage`
JSON-LD.

---

## `/signup` and `/login`

Centred card, max 420px wide.

- Logo, then a one-line reminder of the value prop.
- Google button first (fewer people abandon), divider, then email + password.
- Password field: show/hide toggle, strength meter on signup.
- Inline validation on blur, not on every keystroke.
- Clear switch to the other page at the bottom.
- Errors are specific: *"No account with that email"* beats *"Invalid credentials"* for
  usability. (Some teams consider that an enumeration risk — if the client cares, use the
  generic message and note the tradeoff.)
- On success: new user → `/onboarding`, returning → `/pantry`.

---

## `/onboarding` — 3 steps

Wizard, progress dots at the top, one question per screen. Back is always available.

### Step 1 — Allergens
- **Question:** *"Anything you can't eat?"*
- **Sub:** *"We'll keep these out of every recipe."*
- 14 allergen chips, multi-select, toggle style.
- A prominent **"None of these"** option that clears the rest.
- `<Disclaimer variant="allergen" />` beneath.
- Continue is always enabled — "none" is a valid answer, and forcing a selection here is
  how you get people mashing buttons.

### Step 2 — Diet
- **Question:** *"How do you eat?"*
- 8 diet chips, multi-select, `omnivore` preselected.
- Skip link available.

### Step 3 — Health goal
- **Question:** *"What does healthy mean for you?"*
- 5 cards, single-select, each with an icon, name, and one line of explanation.
- `balanced` preselected.
- `<Disclaimer variant="medical" />` beneath.
- Button: `Let's fill your kitchen` → `/pantry`

---

## `/pantry` — HOME

The most important screen. Everything else exists to serve it.

### Header
`PageHeader` — title *"Your Pantry"*, subtitle *"{n} ingredients"*. Overflow menu on the
right: `Clear all` (with a confirm dialog).

### Search
`<IngredientSearch />`, sticky under the header. Autofocus on desktop only — autofocus on
mobile pops the keyboard and hides the whole screen.

### Quick add
`<QuickAddChips />` — shown when the pantry has fewer than 5 items, hidden after.

### The pantry itself
`<CategoryGroup />` per category, in this order: Produce · Protein · Dairy · Grains ·
Pantry · Spices · Other. Empty categories aren't rendered.

Below the groups, a muted line: *"We assume you have salt, pepper, oil, and water."*

### Empty state
`<EmptyState>` — 🧊 icon, *"Let's fill your kitchen"*, *"Add a few things you have and
we'll find you something to cook."*, with quick-add chips below.

### Generate bar
Sticky to the bottom, above the tab bar. `<GenerateButton />` plus, when relevant, the
helper text about needing more ingredients.

### Layout
- Mobile: single column, sticky search top, sticky generate bottom.
- Desktop: two columns — pantry left (2/3), a sticky sidebar right (1/3) with the generate
  button, current preferences summary, and a link to edit them.

---

## `/generating` — loading

Can be a route or a full-screen overlay; overlay is simpler and avoids a history entry.

- `<LoadingRecipe />` skeleton.
- Cycling copy (see `04-COMPONENTS.md`).
- Cancel button after 5 seconds — never trap someone in a loading state.
- If it exceeds 25s: *"This is taking longer than usual. Still working…"*
- On failure, route to the error state with a retry.

---

## `/recipe/[id]` — Recipe detail

### Hero
Image or gradient placeholder with cuisine emoji. Back button top-left, bookmark
top-right, both over the image with a scrim so they stay legible.

### Title block
- `H1` recipe title.
- One-line description.
- Meta row: `Clock` cook time · `Users` servings · `Flame` kcal · cuisine badge.

### Match summary
*"Uses 8 of your 11 ingredients"* + `<MissingIngredientsCallout />` when anything's
missing.

### Servings scaler
`<ServingsScaler />`. Rescales ingredients and nutrition instantly.

### Ingredients
`<IngredientChecklist />` — "You have" then "You'll need".

### Steps
`<StepList />`. Large text, tap to mark done.

### Nutrition
`<NutritionPanel />` + `<Disclaimer variant="nutrition" />`.

### Actions
Sticky bottom bar: `Save` (or `Saved`) and `Another one` (`RefreshCw`).

### Desktop layout
Two columns: ingredients + nutrition in a sticky left rail, steps on the right. On mobile
it's one column in the order above.

---

## `/saved` — Saved recipes

- `PageHeader` — *"Saved"*, *"{n} recipes"*.
- Search field (client-side filter over titles) once there are more than 6.
- Filter chips: All · Quick (<30 min) · High protein · Vegetarian.
- Grid of `<RecipeCard>` — 1 column mobile, 2 tablet, 3 desktop.
- Any recipe conflicting with a newly-added allergen shows an `AlertTriangle` badge and
  the text *"Contains an ingredient you've marked as an allergen."*
- Empty state: 🔖 *"Nothing saved yet"*, *"When you find a keeper, tap the bookmark."*

---

## `/profile`

Sections, each a `Card`:

1. **Account** — avatar, email, member since.
2. **Allergens** — chip editor, saves on change with a toast.
3. **Diet** — chip editor.
4. **Health goal** — the 5 cards again.
5. **Usage** — *"{n} of 20 recipes generated today"* + reset time. Transparency here
   prevents support emails when someone hits the cap.
6. **Preferences** — theme (System / Light / Dark), units (Metric / Imperial).
7. **Danger zone** — `Clear pantry`, `Delete account` (typed-confirmation dialog).
8. `Sign out`.

---

## `/legal/privacy` and `/legal/terms`

Plain, readable, max-width 680px. Actual content comes from the client — do not write
legal text for them, and say so in writing. A placeholder page with a `TODO` is more
honest than invented terms.

---

## Cross-cutting requirements

Applies to every page:

- Unique `<title>` and meta description.
- Loading, empty, and error states designed — not left to chance.
- Works at 375px and 1440px.
- Works in light and dark.
- Keyboard navigable end to end.
- Route-level `<ErrorBoundary>`.
- No layout shift on load — reserve space for images and async content.

---

**Next:** [`06-DATA-MODEL.md`](./06-DATA-MODEL.md)
