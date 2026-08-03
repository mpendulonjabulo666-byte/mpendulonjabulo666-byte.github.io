# 14 — The Prompt Sequence

Copy-paste prompts for building PantryChef in an AI builder, in order. Each is
self-contained — you don't need to have the spec loaded for them to work, though it helps.

> ## ⚠️ Building on Newly? Three prompts here are superseded.
>
> Newly generates **native React Native apps**, not web apps. Use these replacements from
> [`15-NEWLY-MOBILE.md`](./15-NEWLY-MOBILE.md):
>
> | Prompt | Use instead |
> |---|---|
> | **1** — design system | § 9 — React Native theme object, not CSS variables |
> | **6** — generation | § 10 — Supabase Edge Function, key never in the app binary |
> | **8** — landing page | § 11 — App Store listing; there's no landing page in an app |
>
> Prompts 2, 3, 4, 5, 7 and 7b work as written — say "screen" where they say "page", and
> ignore the URL paths. Prompt 2 in particular is unchanged, because Newly already uses
> Supabase.

**Rules for using these:**

- **One prompt at a time.** Wait for it to finish, check the result, then send the next.
- **Don't skip ahead.** Each depends on the one before it.
- **Check the verification step** after each prompt before moving on. Catching a problem at
  prompt 3 costs minutes; catching it at prompt 8 costs the weekend.
- If the builder produces something wrong, correct it *before* continuing. Errors compound.

| # | Builds | Risk |
|---|--------|------|
| 1 | Design system and standing rules | Low |
| 2 | Database schema + Row Level Security | **High — verify** |
| 3 | South African ingredient catalogue | Medium |
| 4 | Auth + onboarding + POPIA consent | Medium |
| 5 | Pantry screen | Low |
| 6 | Recipe generation | **Highest — verify every line** |
| 7 | Recipe detail screen | Low |
| 7b | Saved + profile screens | Low |
| 8 | Landing page | Low |

Prompt 1 is in the chat history / `13-AI-BUILDER-WORKFLOW.md` § 4.

---

## Prompt 2 — Database schema and security

```
Now set up the database. Create these tables exactly as specified.

ENUMS
health_goal: balanced, high_protein, low_carb, heart_healthy, weight_loss,
             blood_sugar_friendly
diet_tag: omnivore, vegetarian, vegan, pescatarian, halal, kosher, gluten_free,
          dairy_free
allergen: peanuts, tree_nuts, milk, eggs, fish, shellfish, soy, wheat, sesame,
          mustard, celery, sulphites, lupin, molluscs
ingredient_category: produce, protein, dairy, grains, pantry, spices, other
cooking_constraint: none, no_electricity, single_plate, no_cook

TABLES

profiles
  id uuid primary key, references the auth user, cascade on delete
  display_name text
  allergens allergen[] not null default '{}'
  diets diet_tag[] not null default '{omnivore}'
  health_goal health_goal not null default 'balanced'
  budget_mode boolean not null default false
  cooking_constraint cooking_constraint not null default 'none'
  health_data_consent_at timestamptz
  onboarded_at timestamptz
  created_at, updated_at timestamptz not null default now()

ingredients
  id uuid primary key
  name text not null unique
  plural_name text
  category ingredient_category not null
  emoji text
  is_staple boolean not null default false
  high_sodium boolean not null default false
  is_budget_staple boolean not null default false
  allergens allergen[] not null default '{}'
  created_at timestamptz not null default now()
  Full-text search index on name and plural_name.

ingredient_aliases
  id uuid primary key
  ingredient_id uuid not null references ingredients, cascade on delete
  alias text not null unique
  Index on lower(alias).

pantries
  id uuid primary key
  user_id uuid not null references profiles, cascade on delete
  name text not null default 'My Pantry'
  created_at, updated_at timestamptz

pantry_items
  id uuid primary key
  pantry_id uuid not null references pantries, cascade on delete
  ingredient_id uuid references ingredients, null on delete
  custom_name text
  quantity text
  added_at timestamptz not null default now()
  Constraint: ingredient_id or custom_name must be present.
  Unique on (pantry_id, ingredient_id).

recipes
  id uuid primary key
  title text not null
  description text
  cuisine text
  cook_time_minutes int not null
  prep_time_minutes int
  servings int not null default 2
  difficulty text, one of easy/medium/hard
  steps jsonb not null
  nutrition jsonb not null
  image_url text
  generated_by uuid references profiles, null on delete
  model text not null
  health_goal health_goal not null
  diets diet_tag[] not null default '{}'
  excluded_allergens allergen[] not null default '{}'
  cache_key text not null
  created_at timestamptz not null default now()
  Index on cache_key.

recipe_ingredients
  id uuid primary key
  recipe_id uuid not null references recipes, cascade on delete
  ingredient_id uuid references ingredients, null on delete
  raw_name text not null
  quantity text
  is_optional boolean not null default false
  sort_order int not null default 0

saved_recipes
  id uuid primary key
  user_id uuid not null references profiles, cascade on delete
  recipe_id uuid not null references recipes, cascade on delete
  notes text
  saved_at timestamptz not null default now()
  Unique on (user_id, recipe_id).

generation_events
  id uuid primary key
  user_id uuid not null references profiles, cascade on delete
  recipe_id uuid references recipes, null on delete
  cache_hit boolean not null default false
  input_tokens int
  output_tokens int
  latency_ms int
  model text
  status text not null, one of: success, allergen_reject, error, rate_limited
  error_message text
  created_at timestamptz not null default now()

SECURITY — this part is not optional
Enable Row Level Security on profiles, pantries, pantry_items, saved_recipes and
generation_events. Each policy must restrict access to rows belonging to the
currently authenticated user only. Users must never be able to read another
user's data.

ingredients and ingredient_aliases are public read, admin write.

Also add a trigger that creates a profiles row automatically when a new user
signs up, and a trigger that creates their default pantry.

When you are done, show me the Row Level Security policies you created for each
table so I can review them.
```

**Verify before moving on:**
1. Read the RLS policies it shows you. Every user-owned table must have one.
2. Later, once auth works, create two accounts and try to read account B's pantry while
   signed in as A. It must fail. **This is the test that actually proves RLS works** — the
   policy existing is not the same as the policy working.

---

## Prompt 3 — South African ingredient catalogue

```
Seed the ingredients table with South African foods. This is a South African app —
do not use a generic international ingredient list.

Insert these ingredients with their category and allergens. Give each a sensible
emoji.

GRAINS / STARCH
maize meal (grains, budget staple), samp (grains, budget staple),
samp and beans (grains, budget staple), sorghum (grains, budget staple),
white rice (grains, budget staple), brown rice (grains),
bread (grains, allergens: wheat), pasta (grains, allergens: wheat),
potatoes (produce, budget staple), sweet potatoes (produce, budget staple)

LEGUMES
dried beans (pantry, budget staple), tinned baked beans (pantry, budget staple),
lentils (pantry, budget staple), split peas (pantry, budget staple),
chickpeas (pantry), soya mince (protein, allergens: soy),
peanut butter (pantry, allergens: peanuts)

VEGETABLES (all produce)
morogo, spinach, cabbage (budget staple), butternut (budget staple),
gem squash, pumpkin, carrots (budget staple), onions (budget staple),
tomatoes (budget staple), green beans, beetroot, brinjal, green pepper,
mealies, frozen mixed veg (budget staple), garlic, lemon, avocado, banana, apple

PROTEIN
chicken pieces (budget staple), whole chicken, chicken livers (budget staple),
chicken feet (budget staple), beef mince, stewing beef, boerewors, pork chops,
mutton, tinned pilchards (allergens: fish, budget staple),
tinned tuna (allergens: fish), hake (allergens: fish),
eggs (allergens: eggs, budget staple), polony, viennas, biltong

DAIRY
amasi (allergens: milk, budget staple), milk (allergens: milk),
yoghurt (allergens: milk), cheddar (allergens: milk),
cream (allergens: milk), custard (allergens: milk)

PANTRY AND FLAVOUR
curry powder (spices), masala (spices), chakalaka (pantry),
atchar (pantry), chutney (pantry),
stock cubes (pantry, HIGH SODIUM), aromat (spices, HIGH SODIUM),
brown onion soup powder (pantry, HIGH SODIUM),
tomato paste (pantry), peri-peri (spices), brown vinegar (pantry),
cooking oil (pantry, staple), rooibos (pantry), barbecue spice (spices),
sugar (pantry), flour (pantry, allergens: wheat)

STAPLES — mark is_staple true so we don't require them in the pantry
salt, black pepper, cooking oil, water

ALIASES — this is critical for search. Insert into ingredient_aliases so that
typing any of these finds the right ingredient:

mielie meal, mealie meal, pap, phutu, impuphu, putu -> maize meal
stampmielies, stamp -> samp
umngqusho -> samp and beans
imifino, marog, wild spinach, african spinach -> morogo
maas, sour milk -> amasi
mabele -> sorghum
aubergine, eggplant -> brinjal
mielie, mealie, corn on the cob, corn -> mealies
mince, ground beef -> beef mince
pilchards, tinned fish, sardines -> tinned pilchards
sugar beans, speckled beans -> dried beans
walkie talkies -> chicken feet
braai meat -> boerewors
dhania -> coriander
naartjie -> mandarin
pawpaw -> papaya
tamatie -> tomatoes
uanyanisi, anyanisi -> onions
inkukhu -> chicken pieces
amaqanda -> eggs
ubhontshisi -> dried beans
isinkwa -> bread
Also add the plural of every ingredient as an alias.

Then build an ingredient search that queries BOTH the ingredients table and the
aliases table, and returns the canonical ingredient. Searching "mielie meal"
must return "Maize meal". Searching "pilchards" must return "Tinned pilchards".
```

**Verify before moving on:** search for `mielie meal`, `morogo`, `amasi`, `pilchards`,
`brinjal`, `pap`. All six must return a result. If any returns nothing, the alias table
didn't seed properly — fix it now, because every screen after this depends on search
working.

---

## Prompt 4 — Auth and onboarding

```
Build authentication and the onboarding flow.

AUTH
Sign up and sign in with email/password and with Google. Centred card, maximum
420px wide. Google button first, then a divider, then email and password.
Password field has a show/hide toggle. Validate on blur, not on every keystroke.
After signup, send new users to onboarding. Returning users go to /pantry.

ONBOARDING — three steps, progress dots at the top, back always available

Step 1: Allergens
Heading: "Anything you can't eat?"
Subtext: "We'll keep these out of every recipe."
Show all 14 allergens as toggleable chips, plus a prominent "None of these"
option that clears the rest.

IMPORTANT — South African privacy law (POPIA) requires explicit consent for
health-related data. Above the chips, add a required checkbox:
"I agree to PantryChef storing this information to personalise my recipes."
with the text: "We use this to keep allergens out of your recipes. We don't
share it with anyone. You can change or delete it any time."
Record the timestamp of consent in profiles.health_data_consent_at.
The Continue button is disabled until the checkbox is ticked.

Below the chips, show this disclaimer in small muted text with a warning icon:
"We filter for the allergens you've told us about, but always check ingredient
labels yourself."

Step 2: Diet
Heading: "How do you eat?"
Eight diet chips, multi-select, omnivore preselected. Skip link available.

Step 3: Health goal
Heading: "What does healthy mean for you?"
Six cards, single select, balanced preselected. Each card has an icon, a name,
and one line of explanation:
- Balanced — "A bit of everything, sensibly"
- High protein — "Build and maintain muscle"
- Low carb — "Fewer starches and sugars"
- Heart healthy — "Lower salt and saturated fat"
- Weight loss — "Filling meals, fewer calories"
- Blood sugar friendly — "Steadier energy, better for diabetes"

Below, in small muted text: "PantryChef gives general suggestions, not medical
or dietary advice."

Button: "Let's fill your kitchen" — saves everything and goes to /pantry.

Apply all the standing rules from our design system setup.
```

**Verify:** the consent checkbox must actually block the Continue button, and
`health_data_consent_at` must be populated in the database afterwards.

---

## Prompt 5 — The pantry screen

```
Build the pantry screen at /pantry. This is the home screen and the most
important screen in the app.

HEADER
Title "Your Pantry", subtitle showing the ingredient count. An overflow menu on
the right with "Clear all" behind a confirmation dialog.

SEARCH
An ingredient search field, sticky below the header. Debounce 200ms. Query the
ingredients and aliases tables. Results show emoji, name, and category. Full
keyboard support: up/down arrows to move, Enter to select, Escape to close.
If nothing matches, the last row offers: Add "{what they typed}" as a custom
ingredient.
Autofocus on desktop only — do not autofocus on mobile, it opens the keyboard
and hides the screen.

QUICK ADD
When the pantry has fewer than 5 items, show quick-add chips for these common
South African staples: maize meal, eggs, onions, tomatoes, cabbage, dried beans,
chicken pieces, rice, potatoes, butternut, tinned pilchards, amasi.

THE PANTRY
Group ingredients by category in this order: Produce, Protein, Dairy, Grains,
Pantry, Spices, Other. Each group has a header with an emoji, the category name,
and a count. Ingredients show as pill-shaped chips with the emoji, the name, and
an X to remove.

The X button must have a 44x44px touch target even though it looks small — use
padding, not size. Give it an aria-label of "Remove {name} from pantry".
Chips animate in with a scale and fade over 200ms.

Don't render empty categories. Below the groups, in muted text:
"We assume you have salt, pepper, oil, and water."

EMPTY STATE
Fridge icon, heading "Let's fill your kitchen", text "Add a few things you have
and we'll find you something to cook", with the quick-add chips below.

COOKING CONTROLS
Above the generate button, two controls:
1. A toggle: "Cook on a budget" with helper text "We'll stick to affordable
   ingredients." Saves to profiles.budget_mode.
2. A small selector: "How are you cooking today?" with options Normal,
   No electricity, One hot plate, No cooking. Saves to
   profiles.cooking_constraint. Default Normal.

GENERATE BUTTON
Sticky to the bottom of the viewport, full width on mobile. Label "Find me a
recipe" with a sparkles icon. Large size.
Disabled when the pantry has fewer than 3 non-staple ingredients, with helper
text below: "Add {n} more ingredient{s} to get started."
When it becomes enabled, pulse it gently once — once, not looping.

Adding and removing ingredients should feel instant — update the UI immediately
and sync in the background.

DESKTOP
Two columns: pantry on the left taking two thirds, and a sticky sidebar on the
right with the generate button, the cooking controls, and a summary of the
user's current allergens and health goal with a link to edit them.

Apply all the standing rules from our design system setup.
```

**Verify:** on a phone, try removing a chip. If you have to aim carefully, the touch target
is too small — send it back.

---

## Prompt 6 — Recipe generation

**This is the one to read carefully rather than trust.** Everything else in the app is an
inconvenience when it breaks. This one can hurt somebody.

```
Build recipe generation. Read every requirement carefully — several are safety
critical.

SECURITY — non-negotiable
This runs on the SERVER ONLY. The Anthropic API key must never appear in
client-side code, in the browser bundle, or in any request the browser makes.
The browser calls our own backend endpoint; our backend calls Anthropic. If you
cannot do this, stop and tell me instead of building a client-side version.

Read the user's allergens, diets, health goal, budget_mode and cooking_constraint
from the DATABASE using their session. Never accept these from the request body.

THE CALL
Model: claude-opus-5
max_tokens: 8000
Use structured JSON output with a strict schema — do not parse prose.
Check stop_reason before reading the response content.

The recipe schema: title, description, cuisine, difficulty, prepTimeMinutes,
cookTimeMinutes, servings, ingredients (array of name, quantity, isOptional,
fromPantry), steps (array of text and optional durationMinutes), nutrition
(calories, proteinG, carbsG, fatG, fiberG, sodiumMg), and healthGoalRationale
(one line explaining why this recipe suits their goal).

SYSTEM PROMPT
You are a recipe developer for PantryChef, cooking for South African households.

Hard rules:
1. ALLERGENS ARE ABSOLUTE. No ingredient may contain an excluded allergen in any
   form, including derivatives, sauces and garnishes. Soy sauce contains soy and
   usually wheat. Pesto usually contains nuts and cheese. Worcestershire sauce
   contains fish. If you are not certain an ingredient is free of an excluded
   allergen, do not use it.
2. Respect every dietary restriction. Vegan excludes honey. Halal excludes pork
   and alcohol.
3. At least 70% of non-staple ingredients must come from the user's pantry.
4. At most 2 ingredients the user does not have, and they must be cheap and
   common. If budget mode is on, at most 1.
5. Assume salt, pepper, cooking oil and water are available.
6. Every step must be a real instruction with time, temperature, and what to
   look for. "Cook until done" is not acceptable.
7. Nutrition is per serving and must be realistic for the quantities given.

South African context:
- Use ingredients available at Shoprite, Pick n Pay, Spar, Boxer or a spaza shop.
- Pap, samp, morogo, amasi, chakalaka and tinned pilchards are normal, good
  ingredients. Build on them.
- NEVER suggest replacing a traditional South African staple with an imported
  one, and never imply that familiar South African food is unhealthy. Improve
  dishes from the inside: more vegetables, more legumes, less salt, better
  method.
- South African English and metric units. Say braai, mielie meal, brinjal, mince.
- Sodium is a serious health concern here. Keep it low. Build flavour with onion,
  garlic, chilli, curry powder, lemon and herbs rather than stock cubes, aromat
  or added salt.
- If budget mode is on, use only cheap widely available ingredients: dried beans,
  lentils, samp, maize meal, eggs, tinned pilchards, cabbage, butternut, carrots,
  onions.
- Respect the cooking constraint absolutely. no_electricity means gas, fire or
  braai only, no oven or microwave or kettle. single_plate means one hob and one
  pot. no_cook means no heat at all.
- For blood_sugar_friendly: prefer lower-GI starches like samp, sorghum and brown
  rice over refined maize meal, always pair starch with protein and fibre, no
  added sugar.

Titles should be appetising and specific. Between 4 and 10 steps.

THE ALLERGEN CHECK — this is the most important part of the app
After the model returns a recipe, run a SEPARATE check in code. Do not rely on
the prompt alone.

For every ingredient in the generated recipe:
- Look it up in our ingredients table by name and by alias.
- If found, check its allergens array against the user's allergens.
- If NOT found in our table, scan the ingredient name for these keywords and
  flag any that match one of the user's allergens: peanut, almond, cashew,
  walnut, pecan, hazelnut, pistachio, milk, cream, butter, cheese, yoghurt,
  egg, mayonnaise, soy, tofu, miso, edamame, wheat, flour, bread, pasta,
  sesame, tahini, prawn, shrimp, crab, lobster, anchovy, fish sauce,
  worcestershire.
- Be deliberately over-cautious. A false positive costs one regeneration. A
  false negative could hurt someone.

If the check fails: discard the recipe entirely and generate again, telling the
model exactly which ingredient violated which allergen. Maximum 3 attempts
total, then return an error saying we couldn't build a safe recipe.

THE USER MUST NEVER SEE A RECIPE THAT FAILED THIS CHECK. Not greyed out, not
with a warning, not briefly. Discard it on the server.

AFTER GENERATION
- Match the returned ingredient names against our ingredients table and split
  them into "have" and "missing" based on the user's pantry.
- Save the recipe and its ingredients to the database.
- Log every attempt to generation_events, including cache hits, allergen
  rejections and errors.

CACHING — this saves real money
Before calling the model, build a cache key by sorting the pantry ingredient IDs
alphabetically and hashing them together with the health goal, diets, allergens,
budget_mode and cooking_constraint. Sorting matters — adding tomatoes before
onions must produce the same key as onions before tomatoes.
If a recipe with that cache key already exists, return it instead of calling the
model. Log it as a cache hit and don't count it against the rate limit.

RATE LIMITING
20 generations per user per day, not counting cache hits. When exceeded, return
a friendly message saying when it resets.

Minimum 3 non-staple ingredients required — reject before calling the model.

LOADING STATE
The call takes 6 to 12 seconds. Show a skeleton of the recipe layout, not a
spinner. Cycle this text every 3 seconds: "Reading your pantry...", "Finding
something good...", "Checking it's safe for you...", "Almost there...". Announce
changes politely to screen readers. Show a cancel option after 5 seconds.

When you are done, show me the allergen check function.
```

**Verify — do this by hand, it is the single most important test in the project:**

1. Set your account's allergens to **peanuts**.
2. Put peanut butter, bread, and bananas in the pantry.
3. Generate **ten times**.
4. **Zero recipes may contain peanuts.**
5. Repeat with **milk**, and again with **wheat**.

Then open the browser dev tools, look at the network tab while generating, and confirm
there is **no request to `api.anthropic.com`**. Search the page source for `sk-ant` — it
must not appear.

If the builder can't produce an allergen check you trust after two attempts, write that
function by hand. It's about forty lines and it's the one place in this app where a bug
hurts a person.

---

## Prompt 7 — Recipe detail screen

```
Build the recipe detail screen at /recipe/[id].

HERO
The recipe image, or a gradient placeholder with the cuisine emoji if there's no
image. Back button top left, bookmark button top right, both over the image with
a dark scrim behind them so they stay readable.

TITLE BLOCK
Recipe title as an h1. One-line description. Then a metadata row: clock icon and
cook time, people icon and servings, flame icon and calories, plus a cuisine
badge.

MATCH SUMMARY
"Uses 8 of your 11 ingredients". If anything is missing, show a callout panel in
the accent-subtle colour: "You'll need 2 more things" with the missing
ingredients as chips and a shopping basket icon.

SERVINGS SCALER
Minus, current value, plus. Both buttons at least 44x44px. Changing it rescales
the ingredient quantities and the nutrition values immediately — this is
arithmetic, do not call the API again.

INGREDIENTS
Two sections. "You have" with a check icon and normal styling. "You'll need"
with a basket icon and an accent-subtle background. Each row can be tapped to
strike through, which is useful while actually cooking.

STEPS
Numbered list, large text (1.125rem) with generous line height — people read
these at arm's length with wet hands. Tapping a step marks it done and dims it.
Steps with a duration get a small clock badge. This state is local only, don't
save it.

NUTRITION
Calories large and first. Then protein, carbs and fat as a single horizontal
stacked bar with a legend, and each macro's gram value as text as well — never
colour alone. Show fibre and sodium as text below.

Directly beneath the nutrition panel, in small muted text with a warning icon:
"Nutrition values are AI-generated estimates. Don't rely on them for medical
decisions."

Also show the one-line explanation of why this recipe suits their health goal.

ACTIONS
Sticky bar at the bottom with two buttons: Save (becomes "Saved" with a filled
bookmark when saved) and "Another one" with a refresh icon. "Another one"
generates a new recipe, passing the current recipe's ID so it isn't repeated.

DESKTOP
Two columns: ingredients and nutrition in a sticky left rail, steps on the right.
On mobile it's one column in the order above.

Apply all the standing rules from our design system setup.
```

---

## Prompt 7b — Saved recipes and profile

```
Build two more screens.

/saved
Header "Saved" with a count. A search field that filters titles client-side,
shown only once there are more than 6 recipes. Filter chips: All, Quick (under
30 min), High protein, Vegetarian. A grid of recipe cards — one column on
mobile, two on tablet, three on desktop.

Each card: image or gradient placeholder, title, and a metadata row with cook
time, servings and calories. Bookmark button top right, at least 44x44px.

If a saved recipe contains an ingredient matching an allergen the user has since
added to their profile, show a warning badge with a triangle icon and the text
"Contains an ingredient you've marked as an allergen." Compare against their
CURRENT allergens each time the page loads.

Empty state: bookmark icon, "Nothing saved yet", "When you find a keeper, tap
the bookmark."

/profile
Cards for each section:
1. Account — avatar, email, member since
2. Allergens — chip editor, saves on change with a toast confirmation
3. Diet — chip editor
4. Health goal — the six cards again
5. Cooking — budget mode toggle and cooking constraint selector
6. Usage — "{n} of 20 recipes generated today" and when it resets
7. Preferences — theme (System / Light / Dark)
8. Danger zone — "Clear pantry" and "Delete account". Delete account requires
   typing the user's email address to confirm, and must delete every row
   belonging to them across every table.
9. Sign out

Apply all the standing rules from our design system setup.
```

**Verify:** delete a test account, then check the database. If any rows survive in
`pantries`, `pantry_items`, `saved_recipes` or `generation_events`, the cascade is broken
and POPIA compliance fails.

---

## Prompt 8 — Landing page

```
Build the public landing page at /. This is for people who aren't signed in yet.

Section 1 — Hero
Headline: "What's in your fridge? Let's make dinner."
Subtext: "Tell us what you've got. We'll give you a healthy recipe you can cook
tonight — no shopping trip required."
Primary button "Get started free" going to signup. Secondary "See how it works"
scrolling to section 3.
Visual: a mock of the pantry screen with ingredient chips, and a recipe card.

Section 2 — The problem
Three cards with an icon and one line each:
- Food goes off: "The average household bins a third of what it buys."
- Decision fatigue: "You know how to cook. You just don't know what."
- Takeaway wins: "Not because you wanted it. Because it was easier."

Section 3 — How it works
Three numbered steps, horizontal on desktop and stacked on mobile:
1. Add what you have
2. Tell us how you eat
3. Cook something good

Section 4 — Features
Six tiles: built for South African kitchens, allergen aware, works when the power
is out, budget friendly, nutrition estimates, save your favourites.

Section 5 — Sample recipes
Three example recipe cards showing real South African dishes.

Section 6 — FAQ accordion
Is it free? / How accurate is the nutrition? / What about allergies? / Do I need
to enter exact quantities? / Does it work on my phone?
The allergies answer must say: "We filter for the allergens you tell us about,
but always check ingredient labels yourself."

Section 7 — Final call to action
Full width band with one button: "Get started free"

Footer with links and legal.

Keep the page under 500KB total. Optimise every image aggressively — many of our
users are paying for mobile data. Add a proper page title, meta description and
Open Graph image.

Apply all the standing rules from our design system setup.
```

---

## After prompt 8

Run the full checklist in `13-AI-BUILDER-WORKFLOW.md` § 8 before showing anyone. The two
that matter most:

- **No `sk-ant` anywhere in the browser bundle.**
- **Allergen check tested by hand** — peanuts, milk, wheat, ten generations each, zero
  violations.

Then export the code to a repository the client owns.

---

**Back to:** [`README.md`](./README.md)
