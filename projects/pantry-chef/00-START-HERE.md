# 00 — Start Here: what you actually need to understand

Plain English. No jargon that isn't explained. Read this once before anything else.

---

## 1. The product is not "an app". It's three problems in a trench coat.

When a client says *"an app where you list your ingredients and it makes you a healthy
recipe"*, it sounds like one thing. It's three, and they have completely different
difficulty levels:

**Problem A — Getting ingredients in (medium).**
Someone has to type "2 eggs, some spinach, half an onion". Typing is friction. Every
extra second here loses users. This is a UI problem: fast input, good autocomplete, easy
to remove things, remembers what you had last time.

**Problem B — Understanding what they typed (the hard one, and nobody expects it).**
"Tomato", "tomatoes", "roma tomato", "tinned tomatos" (typo), and "passata" are five
strings and roughly two actual ingredients. If you don't normalise them, your app will
happily tell someone they can't make a tomato pasta because they typed the plural. This
is called **ingredient normalisation**, and it is the single most underestimated part of
every recipe app ever built. Budget real time for it.

**Problem C — Producing a recipe (easy now, expensive forever).**
An LLM will write you a genuinely good recipe from a list of ingredients. This part takes
an afternoon. The catch is it costs money *per recipe generated*, every time, forever.
Which means caching and cost control are product features, not optimisations.

Anyone who quotes this project without separating A, B, and C will underquote it.

---

## 2. "Healthy" is not a fact. It's a setting.

Healthy means different things to a diabetic, a bodybuilder, a person with high blood
pressure, and someone who just wants to lose a bit of weight. If the app decides what
"healthy" means, it will be wrong for most users.

So: **healthy is a user preference, not a constant.** In v1 that means a small set of
goals (see `01-PRD.md`) that the user picks once and can change. The recipe generator
receives that goal and optimises for it.

Related: the nutrition numbers an LLM gives you are *estimates*. Good ones, but estimates.
That's fine for "roughly 450 kcal, high protein". It is **not** fine for someone counting
insulin units. The app must say so, visibly, in the UI. See § 4.

---

## 3. The three things that will actually hurt you

### Allergens
This is the one that matters. If a user says "I'm allergic to peanuts" and the app
generates a satay sauce, that's not a bug report, that's a hospital visit and a lawsuit.

The rule is: **allergen filtering never depends on the AI alone.** You ask the model to
respect allergens *and* you check its output against the user's allergen list in code
before you ever show it. If the check fails, you throw the recipe away and regenerate.
Two layers. Always. This is written into `08-AI-ENGINE.md` as a hard requirement.

### Cost per generation
Every recipe generated is an API call, and API calls cost money. If the app is free and
one bored user hits "generate" 200 times, you've paid for 200 generations. Mitigations:
cache aggressively (same ingredients + same goal = same recipe, serve from the database),
rate-limit per user, and think about the pricing model early. See `08-AI-ENGINE.md` §
Cost control.

### Hallucinated confidence
The model will never say "I don't know". Give it three ingredients that don't go
together and it will invent something and describe it with total conviction. You need a
sanity floor: below N usable ingredients, don't generate — prompt the user to add more.

---

## 4. Things you must put in the UI for legal and ethical cover

Not optional, and not something to "add later":

- **Nutrition disclaimer** — "Nutrition values are AI-generated estimates and should not
  be used for medical purposes." Visible on every recipe with nutrition shown.
- **Allergen disclaimer** — "Always check ingredient labels yourself. We can't guarantee
  a recipe is free of allergens." Near the allergen filter, not buried in a footer.
- **Not medical advice** — one line in the health-goal onboarding.

These cost you three components and remove an entire category of risk.

---

## 5. Vocabulary you'll see in the other files

| Term | What it means here |
|------|--------------------|
| **Pantry** | The user's saved list of ingredients they currently have. |
| **Canonical ingredient** | The one true entry for a food ("tomato"), which all the messy user inputs map to. |
| **Alias** | A messy user input mapped to a canonical ingredient ("tomatos" → "tomato"). |
| **Staples** | Things we assume everyone has: salt, pepper, water, oil. Not required in the pantry. |
| **Missing tolerance** | How many ingredients a recipe is allowed to need that the user doesn't have. Default: 2. |
| **Health goal** | The user's chosen definition of "healthy" (balanced, high-protein, low-carb, etc.). |
| **Generation** | One AI call that produces one recipe. The unit of cost. |
| **Structured output** | Making the AI return strict JSON matching a schema, instead of prose you have to parse. |
| **Token** | The unit AI providers bill in. Roughly ¾ of a word. |

---

## 6. Skills you need for this build (honest list)

You don't need all of these on day one, but you need to know they're coming:

1. **React + Next.js basics** — components, props, pages, server vs client rendering.
2. **TypeScript, at least the easy 80%** — types on props and API responses.
3. **Tailwind CSS** — utility classes. You'll pick it up in a day.
4. **A database and SQL** — Supabase gives you Postgres with a nice UI. You need to
   understand tables, rows, foreign keys, and basic queries.
5. **Auth** — Supabase handles the hard parts. You need to understand "who is logged in
   and what are they allowed to see".
6. **Calling an API from a server** — the recipe generation call. Never from the browser
   (see `07-API.md` § Why generation is server-only).
7. **Deploying** — Vercel. It's mostly `git push`.

That's a real curriculum, not a weekend. If the client's timeline assumes a weekend,
`11-CLIENT-BRIEF.md` has a section on how to have that conversation.

---

## 7. What "done" looks like for v1

A person can:

1. Land on a page that explains the product in five seconds.
2. Sign up.
3. Say what they can't eat and what "healthy" means to them.
4. Add ten ingredients in under a minute.
5. Press one button and get a recipe that uses those ingredients, respects their
   allergens, matches their goal, and shows rough nutrition.
6. Save it and find it again tomorrow.

That's it. Everything else — shopping lists, meal plans, photo recognition, social
sharing — is v2 or later, and is listed as explicitly out of scope in `01-PRD.md` so that
nobody quietly adds it mid-build.

---

**Next:** [`01-PRD.md`](./01-PRD.md) — the actual product requirements.
