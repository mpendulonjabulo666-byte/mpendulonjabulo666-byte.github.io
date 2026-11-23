# 07 — API Specification

Next.js App Router route handlers under `app/api/`. All authenticated routes read the
session server-side from the Supabase cookie.

---

## Conventions

**Base:** `/api`
**Auth:** Supabase session cookie, read server-side. No bearer tokens in v1.
**Content type:** `application/json`

### Error envelope

Every error, everywhere, the same shape:

```json
{
  "error": {
    "code": "RATE_LIMITED",
    "message": "You've hit today's limit of 20 recipes.",
    "details": { "resetsAt": "2026-08-01T00:00:00Z" }
  }
}
```

| Code | HTTP | Meaning |
|------|------|---------|
| `UNAUTHENTICATED` | 401 | No valid session |
| `FORBIDDEN` | 403 | Session valid, resource isn't theirs |
| `NOT_FOUND` | 404 | No such resource |
| `VALIDATION_ERROR` | 400 | Body failed schema validation |
| `INSUFFICIENT_INGREDIENTS` | 400 | Fewer than 3 usable ingredients |
| `RATE_LIMITED` | 429 | Daily generation cap reached |
| `GENERATION_FAILED` | 502 | Model call failed after retries |
| `NO_SAFE_RECIPE` | 422 | Every attempt failed the allergen check |
| `INTERNAL_ERROR` | 500 | Unhandled |

`message` is user-facing copy. Show it directly — that's why it's written like a sentence
and not like a log line.

---

## Why generation is server-only

The Anthropic API key must never reach the browser. Anything in client-side JavaScript is
public, and a leaked key can be used by anyone, billed to the client, until it's revoked.

So `/api/recipes/generate` runs on the server, where the key lives in an environment
variable. The browser calls your endpoint; your endpoint calls Anthropic. **There is no
version of this where the key belongs in the frontend** — not `NEXT_PUBLIC_`, not "just
for testing".

Same reasoning applies to the Supabase service-role key.

---

## Ingredients

### `GET /api/ingredients/search`

Public. Powers the autocomplete.

| Param | Type | Notes |
|-------|------|-------|
| `q` | string | Required, min 1 char |
| `limit` | int | Default 10, max 25 |

Searches `ingredients.search_vector` **and** `ingredient_aliases.alias`, then dedupes to
canonical ingredients.

```json
{
  "results": [
    { "id": "…", "name": "Tomato", "category": "produce", "emoji": "🍅", "matchedAlias": "tomatos" }
  ]
}
```

`matchedAlias` is returned so the UI can show *"tomatos → Tomato"* — small touch, makes
the normalisation feel intelligent instead of silently wrong.

### `GET /api/ingredients/staples`

Public. Returns the 12 quick-add suggestions.

---

## Pantry

### `GET /api/pantry`

```json
{
  "pantry": { "id": "…", "name": "My Pantry" },
  "items": [
    { "id": "…", "ingredient": { "id": "…", "name": "Tomato", "category": "produce", "emoji": "🍅" },
      "quantity": null, "addedAt": "2026-07-31T09:00:00Z" }
  ],
  "counts": { "total": 11, "byCategory": { "produce": 5, "protein": 3, "dairy": 2, "grains": 1 } }
}
```

### `POST /api/pantry/items`

```json
{ "ingredientId": "uuid" }
```
or, for something not in the catalogue:
```json
{ "customName": "biltong", "quantity": "a packet" }
```

- Duplicate `ingredientId` → 200 with the existing item. Not an error; the user just
  tapped twice.
- `customName` is logged as an alias candidate for later review.

### `DELETE /api/pantry/items/[id]`
→ `204`

### `DELETE /api/pantry/items`
Clears the pantry. → `204`. Requires `{ "confirm": true }` in the body.

---

## Recipe generation

### `POST /api/recipes/generate`

The one that matters.

**Request**
```json
{
  "excludeRecipeIds": ["uuid"],
  "cuisineHint": "mediterranean",
  "maxCookTimeMinutes": 45
}
```

All optional. Ingredients and preferences come from the database using the session — never
from the request body. A client that could send its own allergen list could send an empty
one.

**Response 200**
```json
{
  "recipe": {
    "id": "uuid",
    "title": "Charred Tomato & Chickpea Skillet",
    "description": "A one-pan supper that leans on what you already have.",
    "cuisine": "mediterranean",
    "cookTimeMinutes": 25,
    "prepTimeMinutes": 10,
    "servings": 2,
    "difficulty": "easy",
    "ingredients": {
      "have":    [{ "name": "Cherry tomatoes", "quantity": "300g", "ingredientId": "…" }],
      "missing": [{ "name": "Feta", "quantity": "80g", "ingredientId": "…" }]
    },
    "steps": [{ "text": "Heat the oil over medium-high…", "durationMinutes": 2 }],
    "nutrition": {
      "calories": 480, "proteinG": 22, "carbsG": 44, "fatG": 24, "fiberG": 11, "sodiumMg": 620
    },
    "matchPercent": 82
  },
  "meta": { "cached": false, "generationsToday": 4, "dailyLimit": 20 }
}
```

**Server-side sequence**

1. Authenticate. No session → `401`.
2. Load profile (allergens, diets, goal) and pantry items.
3. Count usable ingredients (excluding staples). Under 3 → `400 INSUFFICIENT_INGREDIENTS`.
4. Check today's non-cached generation count. Over the cap → `429 RATE_LIMITED`.
5. Build the cache key (see `08-AI-ENGINE.md`). Cache hit and no `excludeRecipeIds`
   collision → return the stored recipe, log `cache_hit: true`, done.
6. Call the model with a strict output schema.
7. **Run the allergen check in code.** Fail → discard, retry (max 3 total attempts). All
   attempts fail → `422 NO_SAFE_RECIPE`.
8. Normalise the returned ingredient names against the catalogue; split into have/missing.
9. Persist `recipes` + `recipe_ingredients`; log the `generation_events` row.
10. Return.

**Timeouts:** the model call gets 30s. Wrap the whole handler at 45s. Next.js route
handlers on Vercel have their own limit — set `export const maxDuration = 60` on this route.

### `GET /api/recipes/[id]`
Returns a single recipe. Public by id — recipes aren't secret, and shareable links are a
cheap future feature.

---

## Saved recipes

### `GET /api/saved`
Query: `limit` (default 20), `cursor`, `filter` (`quick` | `high_protein` | `vegetarian`).

```json
{
  "recipes": [{ "…RecipeCard fields…": "…", "savedAt": "…", "hasAllergenConflict": false }],
  "nextCursor": "…"
}
```

`hasAllergenConflict` is computed at read time against the user's *current* allergens —
that's what makes the warning badge on `/saved` work after someone edits their profile.

### `POST /api/saved`
`{ "recipeId": "uuid" }` → `201`. Already saved → `200`, idempotent.

### `DELETE /api/saved/[recipeId]`
→ `204`

---

## Profile

### `GET /api/profile`
```json
{
  "profile": {
    "id": "…", "displayName": "Mpendulo", "allergens": ["peanuts"],
    "diets": ["omnivore"], "healthGoal": "high_protein", "units": "metric",
    "onboardedAt": "…"
  },
  "usage": { "generationsToday": 4, "dailyLimit": 20, "resetsAt": "…" }
}
```

### `PATCH /api/profile`
Partial update of `displayName`, `allergens`, `diets`, `healthGoal`, `units`.
Validated against the enums — an unknown allergen string is a `400`, not a silent write.

### `POST /api/profile/onboarding-complete`
Sets `onboarded_at`. → `200`

### `DELETE /api/account`
Requires `{ "confirmEmail": "user@example.com" }` matching the session email. Cascades
through every owned table. → `204`

---

## Rate limiting

| Scope | Limit |
|-------|-------|
| Generation, per user | 20/day (cache hits don't count) |
| Generation, per IP | 40/day (blunt instrument against signup farming) |
| Ingredient search | 60/min per IP |
| Everything else | 120/min per user |

Enforced with Upstash Redis, or a Postgres count against `generation_events` if you'd
rather not add a service in v1. Postgres is fine at this scale and one less thing to
operate.

---

## Validation

Every request body is parsed with **Zod** at the top of the handler. If it doesn't parse,
return `400 VALIDATION_ERROR` before touching the database.

```ts
const GenerateSchema = z.object({
  excludeRecipeIds:   z.array(z.string().uuid()).max(20).optional(),
  cuisineHint:        z.string().max(40).optional(),
  maxCookTimeMinutes: z.number().int().min(5).max(240).optional(),
});
```

Type-check inputs at the boundary and the rest of the handler can trust its data.

---

## Security checklist

- [ ] `ANTHROPIC_API_KEY` and `SUPABASE_SERVICE_ROLE_KEY` are server-only. No
      `NEXT_PUBLIC_` prefix, ever.
- [ ] Every user-owned table has RLS enabled with a policy.
- [ ] Preferences for generation are read from the DB, never trusted from the request.
- [ ] Every body validated with Zod.
- [ ] Rate limits on generation and search.
- [ ] No secrets in error messages returned to the client.
- [ ] `DELETE /api/account` requires typed confirmation.
- [ ] CORS: same-origin only. No wildcard.

---

**Next:** [`08-AI-ENGINE.md`](./08-AI-ENGINE.md)
