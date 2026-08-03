# 08 — The Recipe Engine

How a pantry becomes a recipe, safely and without bankrupting anyone. This is the file to
read twice.

> **The system prompt in § 3 is incomplete on its own.** Append the South African context
> block from [`12-SOUTH-AFRICA.md`](./12-SOUTH-AFRICA.md) § 11 — local ingredients, sodium
> awareness, budget mode, load-shedding mode, and an explicit instruction not to imply
> that South African food is unhealthy. Without it the model will drift toward suggesting
> kale and quinoa to households cooking pap.

---

## 1. Model

**`claude-opus-5`** via the Anthropic API, called server-side only.

| | |
|---|---|
| Model ID | `claude-opus-5` |
| Pricing | $5 / 1M input tokens, $25 / 1M output tokens |
| Context window | 1M tokens (far more than needed here) |
| Max output | 128K tokens |
| Thinking | On by default — see the warning below |

**Two things about Opus 5 that will bite you if you don't know them:**

1. **Thinking is on by default.** Omitting the `thinking` parameter means adaptive
   thinking runs. Thinking tokens are billed as output tokens.
2. **`max_tokens` caps thinking *plus* the response.** Set it too tight and the recipe
   truncates mid-JSON, which fails schema validation and wastes the whole call. Use
   `max_tokens: 8000` — generous headroom for a recipe that's realistically ~1,200 tokens.

If the client later decides the cost isn't worth it, `claude-sonnet-5` ($3/$15, with an
introductory $2/$10 through 2026-08-31) is the obvious step down and would handle this
task well. That's a **client budget decision**, not one to make silently — put the numbers
in front of them (§ 7) and let them choose.

---

## 2. Structured output — never parse prose

Don't ask for a recipe and then regex the answer apart. Constrain the output to a schema
and the model returns valid JSON or the request fails loudly.

```ts
import { z } from 'zod';
import Anthropic from '@anthropic-ai/sdk';
import { zodOutputFormat } from '@anthropic-ai/sdk/helpers/zod';

const RecipeSchema = z.object({
  title: z.string(),
  description: z.string(),
  cuisine: z.string(),
  difficulty: z.enum(['easy', 'medium', 'hard']),
  prepTimeMinutes: z.number().int(),
  cookTimeMinutes: z.number().int(),
  servings: z.number().int(),
  ingredients: z.array(z.object({
    name: z.string(),
    quantity: z.string(),
    isOptional: z.boolean(),
    fromPantry: z.boolean(),      // model's claim; we verify it ourselves
  })),
  steps: z.array(z.object({
    text: z.string(),
    durationMinutes: z.number().int().optional(),
  })),
  nutrition: z.object({
    calories: z.number(),
    proteinG: z.number(),
    carbsG:   z.number(),
    fatG:     z.number(),
    fiberG:   z.number(),
    sodiumMg: z.number(),
  }),
  healthGoalRationale: z.string(),   // one line: why this fits their goal
});

const client = new Anthropic();  // reads ANTHROPIC_API_KEY from the environment

const response = await client.messages.parse({
  model: 'claude-opus-5',
  max_tokens: 8000,
  output_config: {
    format: zodOutputFormat(RecipeSchema),
    effort: 'high',
  },
  system: [
    { type: 'text', text: SYSTEM_PROMPT, cache_control: { type: 'ephemeral' } },
  ],
  messages: [{ role: 'user', content: buildUserMessage(context) }],
});

if (response.stop_reason === 'refusal') {
  throw new GenerationError('GENERATION_FAILED');
}

const recipe = response.parsed_output!;   // typed as z.infer<typeof RecipeSchema>
```

Three details that matter:

- **`output_config.format`**, not the deprecated top-level `output_format`.
- **Check `stop_reason` before reading the result.** A refusal returns HTTP 200 with no
  usable content — code that goes straight to `parsed_output` breaks on it.
- **`cache_control` on the system prompt.** See § 5.

`healthGoalRationale` exists so the UI can show *"High in protein — 34g from the chicken
and chickpeas"*. It makes the personalisation visible instead of implied, which is most of
what makes users believe the feature is real.

---

## 3. The system prompt

Stable across every request. That stability is what makes it cacheable — keep timestamps,
user IDs, and pantry contents **out** of it. All of that goes in the user message.

```
You are a recipe developer for PantryChef. You build healthy, achievable recipes from
the ingredients someone already has at home.

## Hard rules — never break these

1. ALLERGENS ARE ABSOLUTE. If an allergen is listed as excluded, no ingredient in your
   recipe may contain it, in any form, including derivatives, sauces, and garnishes.
   Soy sauce contains soy and usually wheat. Pesto usually contains pine nuts and
   parmesan. Worcestershire sauce contains fish. If you are not certain an ingredient
   is free of an excluded allergen, do not use it.
2. Respect every dietary restriction listed. Vegan excludes honey. Pescatarian excludes
   all meat and poultry. Halal excludes pork and alcohol.
3. Use ingredients from the user's pantry as the foundation of the dish. At least 70%
   of the non-staple ingredients must come from their list.
4. You may require at most 2 ingredients the user does not have. They must be common,
   cheap, and easy to find. Never a specialist item.
5. Assume the user has salt, pepper, cooking oil, and water without listing them.
6. Every step must be a real instruction with enough detail to follow: temperature,
   time, and what to look for. "Cook until done" is not acceptable.
7. Nutrition values are per serving and must be realistic estimates for the quantities
   you specified.
8. Never invent an ingredient the user listed. Only use what is in the pantry, plus your
   two allowed additions.

## Style

- Titles are appetising and specific: "Charred Tomato & Chickpea Skillet",
  not "Tomato Recipe" or "Healthy Vegetable Dish".
- Between 4 and 10 steps. Fewer feels careless, more feels like homework.
- Cook time under 45 minutes unless the user asked otherwise.
- Write for a competent home cook, not a chef and not a beginner.

## Health goals

- balanced: vegetables present, whole grains where sensible, sane portions.
- high_protein: at least 30g protein per serving where the ingredients allow.
- low_carb: at most 30g net carbs per serving.
- heart_healthy: low saturated fat, low sodium, fibre-forward.
- weight_loss: at most 500 kcal per serving; prioritise protein, fibre, and volume
  so the meal is genuinely filling.
```

**Why the allergen rule names specific traps.** "Avoid nuts" is easy to satisfy in the
obvious cases and easy to fail in pesto. Naming the classic hidden sources measurably
improves compliance — and the code-level check in § 4 catches the rest.

---

## 4. Allergen safety — two layers, always

**The single most important section in this documentation.**

### Layer 1 — the prompt
Excluded allergens are stated explicitly in both the system prompt and the user message.
Necessary. Not sufficient.

### Layer 2 — the code check
After every generation, before the recipe is stored or shown:

```ts
async function passesAllergenCheck(
  recipe: ParsedRecipe,
  userAllergens: Allergen[],
): Promise<{ ok: true } | { ok: false; violations: string[] }> {
  if (userAllergens.length === 0) return { ok: true };

  const violations: string[] = [];

  for (const item of recipe.ingredients) {
    // Resolve the model's ingredient name against the catalogue (name + aliases).
    const known = await resolveIngredient(item.name);

    if (known) {
      const hits = known.allergens.filter(a => userAllergens.includes(a));
      if (hits.length > 0) violations.push(`${item.name} contains ${hits.join(', ')}`);
    } else {
      // Unknown ingredient: fall back to keyword matching against known allergen terms.
      const hits = keywordAllergenScan(item.name, userAllergens);
      if (hits.length > 0) violations.push(`${item.name} may contain ${hits.join(', ')}`);
    }
  }

  return violations.length === 0 ? { ok: true } : { ok: false, violations };
}
```

`keywordAllergenScan` matches substrings for ingredients not in the catalogue —
`peanut`, `almond`, `cashew`, `walnut`, `pecan`, `hazelnut`, `pistachio`, `milk`, `cream`,
`butter`, `cheese`, `yoghurt`, `egg`, `mayonnaise`, `soy`, `tofu`, `miso`, `edamame`,
`wheat`, `flour`, `bread`, `pasta`, `sesame`, `tahini`, `prawn`, `shrimp`, `crab`,
`lobster`, `anchovy`, `fish sauce`, `worcestershire`. Deliberately over-eager: a false
positive costs one regeneration, a false negative costs someone's health.

### On failure

```
Attempt 1 fails → log it, retry with a hardened instruction naming the exact violation
Attempt 2 fails → retry once more
Attempt 3 fails → return 422 NO_SAFE_RECIPE, alert monitoring
```

**The user never sees a recipe that failed the check.** Not greyed out, not with a
warning banner, not for a split second. Discarded server-side and regenerated.

Every rejection is logged to `generation_events` with `status = 'allergen_reject'`. If the
rejection rate climbs above ~2%, the prompt needs work — that metric is your early warning
system.

---

## 5. Prompt caching

The system prompt is ~1,200 tokens and identical on every request. Caching it means paying
full price once and roughly a tenth of that on every subsequent call within the cache
window.

```ts
system: [
  { type: 'text', text: SYSTEM_PROMPT, cache_control: { type: 'ephemeral' } },
],
```

Rules:
- Opus 5's minimum cacheable prefix is **512 tokens**. The system prompt clears it
  comfortably.
- Default TTL is 5 minutes. Under steady traffic each request keeps it warm.
- **Nothing volatile in the system prompt.** No date, no user id, no pantry contents. One
  byte of drift and every request is a cache miss.
- Verify it's actually working: `response.usage.cache_read_input_tokens` should be
  non-zero on repeat calls. If it's always 0, something in the prefix is changing.

---

## 6. Recipe caching — the real cost lever

Prompt caching saves on input tokens. Recipe caching skips the call entirely.

### Cache key

```ts
function buildCacheKey(input: {
  ingredientIds: string[];
  healthGoal: HealthGoal;
  diets: DietTag[];
  allergens: Allergen[];
  cuisineHint?: string;
}): string {
  const normalised = {
    ingredients: [...input.ingredientIds].sort(),   // sort — order must not matter
    goal:        input.healthGoal,
    diets:       [...input.diets].sort(),
    allergens:   [...input.allergens].sort(),
    cuisine:     input.cuisineHint ?? null,
  };
  return sha256(JSON.stringify(normalised));
}
```

Sorting is what makes this work. Without it, adding tomatoes before onions produces a
different key than onions before tomatoes, and you never get a hit.

### Lookup

On generate: `select * from recipes where cache_key = $1 and id != all($excludeIds) limit 1`.
Hit → return it, log `cache_hit: true`, don't count it against the rate limit.

**Expected hit rate.** Low at first (every pantry is unique), climbing as the recipe
library grows. Realistically 25–40% by a few thousand users. That's a 25–40% cut to the
biggest variable cost in the product — worth building on day one, not month three.

### Regeneration and the cache

"Another one" passes `excludeRecipeIds`. If a different cached recipe matches the key, use
it — free variety. Only call the model when the cache is exhausted for that key.

---

## 7. Cost

Estimates. **Measure the real thing in week one** by logging `usage` on every call —
`generation_events` already has the columns.

Per generation, uncached, at `effort: 'high'` with adaptive thinking on:

| | Tokens | Cost |
|---|---|---|
| Input (system, cached) | ~1,200 | ~$0.0006 |
| Input (user message) | ~350 | ~$0.0018 |
| Output — thinking | ~1,500 | ~$0.0375 |
| Output — recipe JSON | ~1,200 | ~$0.0300 |
| **Total** | | **~$0.07** |

Thinking tokens dominate. Two honest observations about that:

- **Effort is the dial, and it's worth sweeping.** Opus 5 performs unusually well at
  `low` and `medium` — on a task this well-specified, `medium` may produce recipes
  indistinguishable from `high` at a fraction of the thinking spend. Generate 20 recipes
  at each level, compare them blind, and pick. Don't guess.
- **Effort does not shorten the recipe.** It changes how much the model thinks, not how
  long the output is. Output length is controlled by the schema and the prompt.

At scale, with a 30% cache hit rate:

| Monthly generations | Uncached calls | Est. cost |
|---|---|---|
| 1,000 | 700 | ~$49 |
| 10,000 | 7,000 | ~$490 |
| 100,000 | 70,000 | ~$4,900 |

**This is the conversation to have with the client before launch, not after the first
invoice.** At 100k generations a month a free product is losing roughly $5k. Options:
free tier with a daily cap (already specced), paid tier for unlimited, or ads. Raise it in
the kickoff.

Controls already in the spec: recipe caching, prompt caching, per-user daily cap (20),
per-IP cap (40), and a minimum ingredient count so no call is wasted on a doomed request.

Worth adding as an operational safeguard: a **monthly org-wide spend alarm**. If costs
cross a threshold, generation degrades to cache-only and shows an honest message. Better
than a surprise bill.

---

## 8. The user message

Rebuilt per request. Everything volatile lives here, which is what keeps the system prompt
cacheable.

```
## The user's pantry
Produce: cherry tomatoes, red onion, spinach, garlic, lemon
Protein: chicken thighs, eggs, chickpeas (tinned)
Dairy: feta, greek yoghurt
Grains: basmati rice, wholemeal pasta

## Their requirements
Health goal: high_protein
Diets: omnivore
EXCLUDED ALLERGENS (absolute): peanuts, tree_nuts

## Constraints
Maximum cook time: 45 minutes
Do not produce any of these recipes: "Lemon Chicken Traybake", "Chickpea Curry"
Aim for a different cuisine than: mediterranean

Create one recipe.
```

Notes:
- Ingredients grouped by category — it reads more like a kitchen and less like a dump.
- Allergens repeated here even though they're in the system prompt. Redundancy on the
  safety-critical instruction is free.
- Exclusions and the cuisine nudge are what stop "Another one" returning near-identical
  dishes.

---

## 9. Failure handling

| Failure | Response |
|---------|----------|
| Network / API error | Retry twice with exponential backoff (1s, 3s). Then `502 GENERATION_FAILED`. |
| `stop_reason: "refusal"` | Log with `stop_details.category`, return `502`. Vanishingly rare for recipes, but check for it — reading `content` on a refusal is a crash. |
| Schema validation fails | Retry once. Usually means `max_tokens` truncated the JSON — check `stop_reason === 'max_tokens'` first and raise the limit if so. |
| Allergen check fails | Retry up to 3 total attempts, then `422 NO_SAFE_RECIPE`. |
| Timeout (>30s) | Abort, return `502`. Never leave the user on a loading screen indefinitely. |

Every outcome, including success, writes a `generation_events` row. That table is the only
way you'll ever answer "is it getting worse?"

---

## 10. Quality evaluation

Before launch, generate 50 recipes across varied pantries and score each one:

- [ ] Would a normal person actually cook this?
- [ ] Does it genuinely use the pantry, or did it drift to its own shopping list?
- [ ] ≤ 2 missing ingredients, and are they genuinely common?
- [ ] Do the nutrition numbers look plausible for the quantities given?
- [ ] Does it match the stated health goal?
- [ ] Zero allergen violations. **This must be 50/50.**
- [ ] Are the steps followable without prior knowledge of the dish?
- [ ] **Are the ingredients actually buyable at a Shoprite or a spaza shop?**
- [ ] **Does it use South African English and metric units?**
- [ ] **Does any recipe imply South African food is unhealthy, or suggest replacing a
      staple with an imported substitute?** Any hit is a prompt bug, not a taste
      difference. Fix the prompt and re-run.
- [ ] With budget mode on: is every ingredient genuinely cheap, and is there at most one
      missing item?
- [ ] With a cooking constraint set: is the recipe actually cookable under it?

Keep the set. When you change the prompt, run it again and compare. A prompt change with
no evaluation behind it is a guess.

---

**Next:** [`09-TECH-STACK.md`](./09-TECH-STACK.md)
