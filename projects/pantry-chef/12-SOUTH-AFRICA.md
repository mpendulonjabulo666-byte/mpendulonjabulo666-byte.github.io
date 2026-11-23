# 12 — South African Localisation

The app is built for South Africa. This is not a translation layer bolted on at the end —
it changes the ingredient catalogue, the health goals, the legal basis, and one hard
performance constraint. Read this alongside every other file; where it contradicts an
earlier document, **this file wins**.

---

## 1. Why a generic recipe app fails here

An app built on an American or British ingredient list will be wrong for most South
African kitchens in the first thirty seconds. Someone types "mielie meal" and gets nothing.
They type "morogo" and get nothing. They open the fridge and see amasi, and the app has
never heard of it.

Worse, the *recipes* will be wrong. A generic model suggests quinoa bowls and kale salads
to a household cooking on a R700 monthly grocery budget with pap as the base of most meals.
That's not a healthy-eating app. That's an app that tells people their food is wrong.

The correct framing:

> **Make the food South Africans already eat, healthier — don't replace it with food from
> somewhere else.**

Pap is not the enemy. Pap with morogo, beans, and less salt is a genuinely good meal. An
app that understands this is useful. An app that suggests replacing it with couscous is
not.

---

## 2. Health context

South Africa carries a heavy and well-documented burden of diet-related
non-communicable disease — high rates of hypertension, type 2 diabetes, and obesity,
alongside continuing under-nutrition in poorer households. This "double burden" is the
public-health reality the app operates in.

Two practical consequences:

**Sodium matters more than usual.** South Africa has regulated maximum salt levels in
several categories of processed food, and high blood pressure is widespread. Sodium should
be a first-class number in the nutrition panel, not a footnote — and the `heart_healthy`
goal should be genuinely aggressive about it.

**Blood sugar deserves its own goal.** Given the diabetes burden, add a sixth health goal
to the five in `01-PRD.md`:

| Goal | Optimises for |
|------|---------------|
| `blood_sugar_friendly` | Lower-GI starches, fibre alongside carbohydrate, no added sugar, portion-aware starch. |

Note it is *not* the same as `low_carb`. In a country where starch is the foundation of
most meals, telling people to remove starch is advice they will ignore. Telling them to
choose samp over refined maize meal, and to eat beans alongside it, is advice they can act
on.

### Anchor the guidance to something official

Use the **South African Food-Based Dietary Guidelines** (Department of Health) as the
reference the app's definition of "healthy" is built on. Paraphrased, they cover: making
starchy foods part of most meals; plenty of vegetables and fruit daily; dry beans, split
peas, lentils and soya regularly; milk, maas or yoghurt daily; fish, chicken, lean meat or
eggs daily; plenty of clean safe water; fats, sugar and salt used sparingly; and being
active.

**Why this matters for the client:** it lets the app say "aligned with the South African
Food-Based Dietary Guidelines" instead of "healthy according to us". For a lecturer's
project especially, citing a national standard is worth a great deal more than an
invented rubric. Cite the current published version and check it before launch — do not
quote from this file as a source.

The disclaimers in `04-COMPONENTS.md` stay exactly as they are. Alignment with guidelines
is not medical advice.

---

## 3. Ingredient catalogue — South African seed

This replaces the generic ~500-item catalogue in `06-DATA-MODEL.md`. Same structure, South
African contents.

### Staples and starches
maize meal (mielie meal) · samp · samp and beans · sorghum (mabele) · rice · brown rice ·
bread (white/brown) · vetkoek dough · potatoes · sweet potatoes · pasta · phutu

### Legumes — the cheap-protein backbone
dried beans (sugar beans, speckled) · tinned baked beans · lentils · split peas ·
chickpeas · soya mince · peanuts *(allergen)*

### Vegetables
morogo (imifino) · spinach · cabbage · butternut · gem squash · pumpkin · carrots ·
onions · tomatoes · green beans · beetroot · brinjal · green pepper · mealies (corn) ·
mixed veg (frozen)

### Protein
chicken (whole, pieces, feet, livers) · beef mince · stewing beef · boerewors · pork ·
mutton · tinned pilchards *(fish allergen)* · tinned tuna *(fish allergen)* · hake ·
eggs *(allergen)* · polony · viennas · biltong · russians

**Tinned pilchards deserve special attention.** They are one of the cheapest and most
nutritionally valuable proteins available in South Africa — high in protein and omega-3,
shelf-stable, in nearly every spaza shop. Any healthy-eating app for this market that
doesn't handle pilchards well is missing the single best value-for-money ingredient in the
country. Seed them, alias them, and make sure the generator uses them.

### Dairy
amasi (maas) · milk *(allergen)* · yoghurt *(allergen)* · cheddar *(allergen)* ·
cream *(allergen)* · custard

### Pantry and flavour
Rajah curry powder · masala · chakalaka (tinned) · atchar · chutney · stock cubes ·
tomato paste · Bovril/Marmite · peri-peri · aromat · brown vinegar · cooking oil ·
rooibos · mixed spice · barbecue spice

**Stock cubes and aromat carry a lot of sodium.** Flag them in the catalogue with a
`high_sodium` boolean so the `heart_healthy` and `blood_sugar_friendly` goals can steer
away from them and suggest herbs, onion, garlic, and lemon instead. This is one of the
highest-impact health interventions the app can make, because it changes a habit rather
than a shopping list.

Add to `ingredients`:

```sql
alter table ingredients add column high_sodium boolean not null default false;
alter table ingredients add column is_budget_staple boolean not null default false;
```

---

## 4. Language and aliases

English is the app's interface language for v1. But **ingredient input must accept the
words people actually use**, across languages — that's the whole point of the alias table
in `06-DATA-MODEL.md`.

South Africa has 12 official languages. You are not translating the app in v1. You are
making sure that typing a food's common local name finds the right ingredient.

Seed `ingredient_aliases` with at least these:

| User types | Canonical |
|---|---|
| mielie meal · mealie meal · pap · phutu · impuphu · putu | maize meal |
| stampmielies · stamp | samp |
| umngqusho | samp and beans |
| imifino · marog · wild spinach · African spinach | morogo |
| maas · sour milk · amasi | amasi |
| mabele | sorghum |
| brinjal · aubergine · eggplant | brinjal |
| naartjie | mandarin |
| pawpaw | papaya |
| mielie · mealie · corn on the cob | mealies |
| mince · ground beef | beef mince |
| pilchards · tinned fish · sardines | tinned pilchards |
| gem squash | gem squash |
| chakalaka | chakalaka |
| dhania | coriander |
| sugar beans · speckled beans | dried beans |
| chicken feet · walkie talkies | chicken feet |
| skop | sheep head |
| braai meat | boerewors |
| jeqe · steamed bread · ujeqe | steamed bread |

Add isiZulu, isiXhosa, Sesotho, Setswana, and Afrikaans names for the top ~100 ingredients.
This is a day of work with a native-speaker check, and it is the difference between an app
that feels local and an app that feels imported.

**Have the lecturer review this list.** They will know regional variations you don't, and
it's a natural, low-cost way to involve them in something concrete early.

> **v2, worth flagging now:** full interface translation into isiZulu and Afrikaans is a
> strong second-phase feature. Design the UI with `next-intl` string extraction from the
> start so this isn't a rewrite later — the cost of preparing for it is nearly zero, and
> the cost of retrofitting it is high.

---

## 5. Budget mode — the feature that matters most here

For a large share of the market, "healthy" and "affordable" are the same question. A
recipe that is nutritionally perfect and costs R120 a serving is not a recipe, it's an
insult.

**Budget mode is a toggle, not a health goal.** It's orthogonal — someone can want
high-protein *and* cheap. Combining them into one setting forces a false choice.

```sql
alter table profiles add column budget_mode boolean not null default false;
```

When on, the generator is constrained to:
- Prefer ingredients flagged `is_budget_staple`
- No more than **one** missing ingredient (down from two), and it must be cheap
- Prioritise dried beans, lentils, samp, eggs, tinned pilchards, seasonal vegetables
- Avoid out-of-season produce, imported items, and expensive cuts

UI: a switch on the pantry screen and in `/profile`, labelled *"Cook on a budget"* with
the helper text *"We'll stick to affordable ingredients."*

**Deliberately not in v1:** actual rand cost estimates per meal. Real prices vary by
retailer, region, and week, and a wrong number is worse than no number. Revisit in v2 with
a real price data source.

---

## 6. Load-shedding mode — a genuinely local feature

When rolling blackouts occur, an electric stove is unavailable for hours at a time, often
right at dinner. Most recipe apps have no concept of this. Handling it is cheap to build
and immediately, obviously useful — which makes it the best demo feature in the whole
product.

```sql
alter table profiles add column cooking_constraint text
  check (cooking_constraint in ('none','no_electricity','single_plate','no_cook'))
  default 'none';
```

| Constraint | Generator must produce |
|---|---|
| `none` | Anything |
| `no_electricity` | Gas or fire only — one pot, braai, or paraffin stove. No oven, no microwave, no kettle. |
| `single_plate` | One hob, one pot. No oven. |
| `no_cook` | Nothing requiring heat at all. |

UI: a small selector next to the generate button, labelled *"How are you cooking today?"*
Default `none`, remembered between sessions.

This is the feature to put on the landing page and to lead with in any demo. It signals
immediately that the app was built *here*, by someone who understands the conditions, and
not adapted from somewhere else.

---

## 7. Performance — data cost is a hard constraint

Mobile data is a meaningful expense for many South African users, and a large share of the
market browses on mid- or low-range Android devices over 3G/4G rather than fibre.

This turns performance from a nice-to-have into a product requirement. Revised targets,
replacing the ones in `09-TECH-STACK.md`:

| Metric | Target | Why |
|--------|--------|-----|
| Initial JS bundle | **< 150KB gzipped** | Every kilobyte is money to the user |
| Landing page total weight | **< 500KB** | First impression on a metered connection |
| Recipe page total weight | **< 300KB** | The page they'll open most |
| Largest Contentful Paint on 3G | **< 4s** | Test on throttled 3G, not on your laptop |
| Time to Interactive, mid-range Android | **< 5s** | Test on a real cheap device |
| Lighthouse Performance (mobile) | **≥ 90** | |

Practical consequences:

- **Images are the whole budget.** Next.js `<Image>`, AVIF/WebP, aggressive sizing, lazy
  loading below the fold. One unoptimised hero photo can exceed the entire page budget.
- **Server components by default** — already the convention in `09-TECH-STACK.md`, and now
  it has a second reason.
- **Cache the pantry locally.** It should render instantly from cache on open, then
  revalidate. TanStack Query handles this; configure it deliberately.
- **Make it installable (PWA).** Add to home screen, app icon, splash screen. Cheap to
  add, and it makes the product feel native without building native.
- **Offline read for saved recipes.** Someone who saved a recipe should be able to open it
  in a kitchen with no signal. Service worker caching the saved list is a small,
  high-value addition.

**Test on a real device.** Buy or borrow an entry-level Android phone and use the app on
mobile data. Everything looks fast on a laptop on fibre.

---

## 8. Legal — POPIA, not GDPR

South African users are covered by the **Protection of Personal Information Act (POPIA)**.
It has real obligations, and one of them applies directly to this app.

**Health-related information receives special protection under POPIA.** Allergens,
dietary restrictions, and health goals are plausibly health information about an
identifiable person. That means, at minimum:

- **Explicit consent** to collect it, obtained at onboarding — a clear checkbox with plain
  language, not a pre-ticked box buried in terms.
- **Purpose limitation** — state exactly what it's used for (generating suitable recipes)
  and don't use it for anything else without fresh consent.
- **Retention limits** — don't keep it longer than needed. Ties directly to the
  unresolved retention question in `11-CLIENT-BRIEF.md`.
- **Deletion on request** — `DELETE /api/account` in `07-API.md` must genuinely cascade.
  Verify it does.
- **An Information Officer** must be registered with the Information Regulator. That's the
  client's responsibility, and they need to be told about it in writing.

Onboarding change: step 1 (allergens) gains a consent line above the chips.

> *"We use this to keep allergens out of your recipes. We don't share it with anyone. You
> can change or delete it any time."* — with a required checkbox.

**Say this to the client plainly and put it in writing:** you are a developer, not their
lawyer. POPIA compliance is their legal obligation. You will build the technical controls
— consent capture, deletion, retention — and they need to get the privacy policy and
Information Officer registration handled properly.

Update `11-CLIENT-BRIEF.md` § 7 accordingly: the users are in South Africa, so POPIA
applies, and the "who writes the legal pages" question becomes more pressing rather than
less.

---

## 9. Personas, revised

Replacing the generic set in `01-PRD.md`:

**Thandi, 29, Soweto — the primary user.** Works in Joburg CBD, commutes, cooks for
herself and her mother. Shops monthly at Shoprite with top-ups from the local spaza. Wants
to eat better without spending more or cooking anything unfamiliar. Uses a mid-range
Android on a data bundle she watches carefully.

**Sipho, 45, Durban — the health-driven user.** Recently diagnosed with type 2 diabetes.
Was told to "eat healthier" and given a pamphlet. Doesn't want to stop eating the food he
grew up with; wants to know how to cook it in a way that won't hurt him. **This is the
user who most justifies the whole product existing.**

**Lerato, 21, Cape Town — the student.** Res kitchen, one hot plate, tiny budget, limited
skills. Everything must be cheap, fast, and one-pot. Load-shedding mode and budget mode
are both aimed squarely at her.

Notice all three want the same core loop. Nothing about localising the product complicates
the design — it sharpens it.

---

## 10. Voice and tone

The tone guidance in `03-DESIGN-SYSTEM.md` still applies. Two additions:

- **South African English spelling and idiom.** "Braai", not "barbecue". "Mielie meal",
  not "cornmeal". Metric always. Speak the way the user speaks.
- **Never condescend about food.** No "swap your unhealthy pap for…". The message is
  always *here's a good way to cook what you already eat*. This is a real risk with an
  AI-generated product — models trained largely on Northern-Hemisphere health writing
  carry assumptions about which foods are "clean". Guard against it explicitly in the
  system prompt (§ 11) and check for it in the 50-recipe evaluation.

---

## 11. System prompt additions

Append to the system prompt in `08-AI-ENGINE.md`:

```
## Context: South Africa

You are cooking for South African households. This shapes every recipe you write.

- Use ingredients available in South African supermarkets and spaza shops. Assume
  Shoprite, Pick n Pay, Spar, Boxer, or a local spaza — not a specialist store.
- Respect South African food culture. Pap, samp, morogo, amasi, chakalaka, and tinned
  pilchards are normal, good ingredients. Build on them. Never suggest replacing a
  traditional staple with an imported one, and never imply that familiar South African
  food is unhealthy.
- Improve dishes from the inside: more vegetables, more legumes, less salt, better
  cooking method, better balance. Do not substitute the cuisine.
- Use South African English and metric units throughout. Say braai, mielie meal,
  brinjal, mince.
- Sodium is a serious health concern in South Africa. Keep it low. Build flavour with
  onion, garlic, chilli, curry powder, lemon, and herbs rather than stock cubes,
  aromat, or added salt.
- If budget mode is on, use only cheap, widely available ingredients: dried beans,
  lentils, samp, maize meal, eggs, tinned pilchards, cabbage, butternut, carrots,
  onions, seasonal vegetables. At most ONE missing ingredient, and it must be cheap.
- Respect the stated cooking constraint absolutely:
  - no_electricity: gas, fire, or braai only. No oven, microwave, or kettle.
  - single_plate: one hob, one pot, no oven.
  - no_cook: no heat at all.
- If the health goal is blood_sugar_friendly: prefer lower-GI starches such as samp,
  sorghum, and brown rice over refined maize meal; always pair starch with protein and
  fibre; no added sugar.
```

The anti-condescension instruction is doing real work. Without it, a model will reliably
drift toward suggesting kale and quinoa. Keep it, and test for it.

---

## 12. Revised launch checklist

Additions to `10-ROADMAP.md` Phase 0 and Phase 7:

**Phase 0**
- [ ] Ingredient catalogue seeded with South African foods (§ 3)
- [ ] Aliases seeded across isiZulu, isiXhosa, Sesotho, Setswana, Afrikaans (§ 4)
- [ ] `high_sodium` and `is_budget_staple` columns added and populated
- [ ] Lecturer has reviewed the catalogue and alias list

**Phase 3**
- [ ] `budget_mode` implemented in the generator
- [ ] `cooking_constraint` implemented in the generator
- [ ] `blood_sugar_friendly` goal implemented

**Phase 7**
- [ ] Tested on a real entry-level Android device over mobile data
- [ ] Bundle size verified against § 7 targets
- [ ] PWA installable, saved recipes readable offline
- [ ] POPIA consent captured at onboarding; deletion verified to cascade
- [ ] 50-recipe evaluation includes an explicit check: **does any recipe imply South
      African food is unhealthy, or suggest replacing a staple with an imported
      substitute?** Any hit is a prompt bug.

---

## 13. What this is worth

Worth stating to the lecturer directly, because it's the strongest thing about the
project:

There are many recipe apps. There are very few built around what is actually in a South
African kitchen, in the languages people actually use, aware of load shedding, aware of
what things cost, and aligned to South Africa's own dietary guidelines rather than someone
else's.

That's not a smaller version of an international app. It's a better one, for here.

---

**Back to:** [`README.md`](./README.md)
