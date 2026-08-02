# 06 — Data Model

Postgres via Supabase. SQL below is close to runnable — treat it as the migration
starting point.

> **Blocked on two client answers** (see `11-CLIENT-BRIEF.md`): shared household pantries,
> and whether unsaved generated recipes are retained. Both are called out inline.

---

## Entity overview

```
auth.users  (Supabase-managed)
     │ 1:1
     ▼
  profiles ──────────────┐
     │ 1:1               │ 1:N
     ▼                   ▼
  pantries          saved_recipes ──── N:1 ──── recipes
     │ 1:N                                          │ 1:N
     ▼                                              ▼
pantry_items ──── N:1 ──── ingredients      recipe_ingredients
                                │ 1:N              │
                                ▼                  │
                        ingredient_aliases ────────┘
```

---

## Enums

```sql
create type health_goal as enum (
  'balanced', 'high_protein', 'low_carb', 'heart_healthy', 'weight_loss',
  'blood_sugar_friendly'          -- South African market; see 12-SOUTH-AFRICA.md
);

create type cooking_constraint as enum (
  'none', 'no_electricity', 'single_plate', 'no_cook'   -- load-shedding mode
);

create type diet_tag as enum (
  'omnivore', 'vegetarian', 'vegan', 'pescatarian',
  'halal', 'kosher', 'gluten_free', 'dairy_free'
);

create type allergen as enum (
  'peanuts', 'tree_nuts', 'milk', 'eggs', 'fish', 'shellfish', 'soy',
  'wheat', 'sesame', 'mustard', 'celery', 'sulphites', 'lupin', 'molluscs'
);

create type ingredient_category as enum (
  'produce', 'protein', 'dairy', 'grains', 'pantry', 'spices', 'other'
);

create type unit_system as enum ('metric', 'imperial');
```

Enums over free-text strings: the database rejects typos, and every consumer gets the same
vocabulary.

---

## `profiles`

One row per user. Created by a trigger on signup.

```sql
create table profiles (
  id              uuid primary key references auth.users(id) on delete cascade,
  display_name    text,
  avatar_url      text,
  allergens       allergen[]   not null default '{}',
  diets           diet_tag[]   not null default '{omnivore}',
  health_goal     health_goal  not null default 'balanced',
  units           unit_system  not null default 'metric',

  -- South African market — see 12-SOUTH-AFRICA.md
  budget_mode         boolean            not null default false,
  cooking_constraint  cooking_constraint not null default 'none',

  -- POPIA: explicit consent for health-related data, captured at onboarding
  health_data_consent_at timestamptz,

  onboarded_at    timestamptz,
  created_at      timestamptz  not null default now(),
  updated_at      timestamptz  not null default now()
);

alter table profiles enable row level security;

create policy "own profile read"   on profiles for select using (auth.uid() = id);
create policy "own profile update" on profiles for update using (auth.uid() = id);
```

`allergens` and `diets` are arrays rather than join tables. They're small, fixed, and
always read together — a join table here is ceremony without benefit.

---

## `ingredients` — the canonical catalogue

The backbone of ingredient normalisation. Seed with ~500 common items before launch.

```sql
create table ingredients (
  id            uuid primary key default gen_random_uuid(),
  name          text not null unique,        -- canonical: "tomato"
  plural_name   text,                        -- "tomatoes"
  category      ingredient_category not null,
  emoji         text,
  is_staple     boolean not null default false,   -- salt, pepper, oil, water
  allergens     allergen[] not null default '{}', -- what this ingredient CONTAINS

  -- South African market — see 12-SOUTH-AFRICA.md
  high_sodium      boolean not null default false, -- stock cubes, aromat, packet soup
  is_budget_staple boolean not null default false, -- dried beans, samp, pilchards, eggs

  search_vector tsvector generated always as (
                  to_tsvector('simple', coalesce(name,'') || ' ' || coalesce(plural_name,''))
                ) stored,
  created_at    timestamptz not null default now()
);

create index ingredients_search_idx   on ingredients using gin(search_vector);
create index ingredients_category_idx on ingredients(category);
```

**`allergens` on this table is load-bearing.** It's how the code-level allergen check
works: look up every ingredient a recipe uses, union their allergens, compare to the
user's list. Seed it carefully — `peanut butter → {peanuts}`, `soy sauce → {soy, wheat}`
(most soy sauce contains wheat, and that surprises people).

### `ingredient_aliases`

```sql
create table ingredient_aliases (
  id            uuid primary key default gen_random_uuid(),
  ingredient_id uuid not null references ingredients(id) on delete cascade,
  alias         text not null unique,
  created_at    timestamptz not null default now()
);

create index ingredient_aliases_alias_idx on ingredient_aliases(lower(alias));
```

Seed with plurals, common misspellings, and regional names:
`tomatos → tomato` · `roma tomato → tomato` · `aubergine → eggplant` ·
`coriander → cilantro` · `spring onion → scallion` · `mince → ground beef`.

Grow this table from real user input. Anything typed into "add as custom ingredient" is a
candidate alias — review them weekly and promote the good ones. This is the cheapest
quality improvement available to the product.

---

## `pantries` and `pantry_items`

> **⚠️ Blocked:** if households can share a pantry, `user_id` becomes `owner_id` plus a
> `pantry_members` join table. Everything else here holds either way. Confirm before
> migrating.

```sql
create table pantries (
  id         uuid primary key default gen_random_uuid(),
  user_id    uuid not null references profiles(id) on delete cascade,
  name       text not null default 'My Pantry',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (user_id, name)
);

create table pantry_items (
  id            uuid primary key default gen_random_uuid(),
  pantry_id     uuid not null references pantries(id) on delete cascade,
  ingredient_id uuid references ingredients(id) on delete set null,
  custom_name   text,          -- when the user added something not in the catalogue
  quantity      text,          -- free text: "2 cups", "a handful". Deliberately not numeric.
  added_at      timestamptz not null default now(),

  constraint has_an_ingredient check (ingredient_id is not null or custom_name is not null),
  unique nulls not distinct (pantry_id, ingredient_id)
);

create index pantry_items_pantry_idx on pantry_items(pantry_id);

alter table pantries     enable row level security;
alter table pantry_items enable row level security;

create policy "own pantry" on pantries
  for all using (auth.uid() = user_id);

create policy "own pantry items" on pantry_items
  for all using (
    exists (select 1 from pantries p where p.id = pantry_id and p.user_id = auth.uid())
  );
```

**Why `quantity` is free text:** people say "a handful of spinach", not "47g". Forcing
structured quantities adds friction to the highest-friction step in the app, for a benefit
the recipe generator doesn't need. The LLM handles vague amounts fine.

---

## `recipes`

> **⚠️ Blocked:** if unsaved recipes aren't retained, add a nightly job deleting rows with
> no `saved_recipes` reference older than 30 days. Keeping everything makes the cache
> better and costs storage; deleting is cheaper and simpler.

```sql
create table recipes (
  id                 uuid primary key default gen_random_uuid(),
  title              text not null,
  description        text,
  cuisine            text,
  cook_time_minutes  int  not null,
  prep_time_minutes  int,
  servings           int  not null default 2,
  difficulty         text check (difficulty in ('easy','medium','hard')),
  steps              jsonb not null,     -- [{ text, duration_minutes? }]
  nutrition          jsonb not null,     -- { calories, protein_g, carbs_g, fat_g, fiber_g, sodium_mg }
  image_url          text,

  -- generation metadata
  generated_by       uuid references profiles(id) on delete set null,
  model              text not null,
  health_goal        health_goal not null,
  diets              diet_tag[]  not null default '{}',
  excluded_allergens allergen[]  not null default '{}',
  cache_key          text not null,      -- see 08-AI-ENGINE.md § Caching
  created_at         timestamptz not null default now()
);

create index recipes_cache_key_idx on recipes(cache_key);
create index recipes_created_idx   on recipes(created_at desc);
```

`steps` and `nutrition` are `jsonb` because they're always read as a whole object and never
queried field-by-field. Normalising them would cost joins and buy nothing.

`excluded_allergens` records what the recipe was generated *to avoid* — essential for
auditing if a violation is ever reported.

### `recipe_ingredients`

```sql
create table recipe_ingredients (
  id            uuid primary key default gen_random_uuid(),
  recipe_id     uuid not null references recipes(id) on delete cascade,
  ingredient_id uuid references ingredients(id) on delete set null,
  raw_name      text not null,       -- exactly what the model returned
  quantity      text,                -- "200g", "2 tbsp"
  is_optional   boolean not null default false,
  sort_order    int not null default 0
);

create index recipe_ingredients_recipe_idx on recipe_ingredients(recipe_id);
```

`raw_name` is always stored. `ingredient_id` is filled in when normalisation finds a
match — when it doesn't, you keep the raw string and log it as an alias candidate. Never
throw away what the model actually said.

---

## `saved_recipes`

```sql
create table saved_recipes (
  id        uuid primary key default gen_random_uuid(),
  user_id   uuid not null references profiles(id) on delete cascade,
  recipe_id uuid not null references recipes(id) on delete cascade,
  notes     text,
  saved_at  timestamptz not null default now(),
  unique (user_id, recipe_id)
);

create index saved_recipes_user_idx on saved_recipes(user_id, saved_at desc);

alter table saved_recipes enable row level security;
create policy "own saved recipes" on saved_recipes for all using (auth.uid() = user_id);
```

---

## `generation_events` — cost and abuse control

```sql
create table generation_events (
  id             uuid primary key default gen_random_uuid(),
  user_id        uuid not null references profiles(id) on delete cascade,
  recipe_id      uuid references recipes(id) on delete set null,
  cache_hit      boolean not null default false,
  input_tokens   int,
  output_tokens  int,
  latency_ms     int,
  model          text,
  status         text not null check (status in ('success','allergen_reject','error','rate_limited')),
  error_message  text,
  created_at     timestamptz not null default now()
);

create index generation_events_user_time_idx on generation_events(user_id, created_at desc);
```

This table is how you answer "why is the AI bill £400 this month" and "how often does the
allergen check actually fire". Both questions *will* be asked. Log every attempt,
including cache hits and rejections.

Daily rate limit:

```sql
select count(*) from generation_events
where user_id = $1
  and cache_hit = false
  and created_at > date_trunc('day', now());
```

---

## Row Level Security — the rule

Every user-owned table gets RLS enabled and a policy. `ingredients` and
`ingredient_aliases` are public read, admin write. `recipes` are readable by anyone who
has the id (they're not secret) but only writable by the service role.

**Never ship a table with RLS disabled.** In Supabase, a table without RLS is readable by
anyone holding the anon key — which is in your client-side JavaScript, visible to
everyone.

---

## Seed data required before launch

| Table | Rows | Source |
|-------|------|--------|
| `ingredients` | ~500 | **South African catalogue** — see `12-SOUTH-AFRICA.md` § 3. Allergen-, sodium-, and budget-annotated. |
| `ingredient_aliases` | ~1,500 | Plurals, misspellings, **and local-language names** — see `12-SOUTH-AFRICA.md` § 4. |
| Quick-add staples | 12 | Subset flagged for the empty state. Use SA staples: maize meal, eggs, onions, tomatoes, cabbage, dried beans, chicken, rice, potatoes, butternut, tinned pilchards, amasi. |

Seeding the catalogue is a real task — budget a day, and get the client to review the
allergen annotations specifically. That column is the safety net.

**Do not seed a generic international catalogue.** An app that doesn't recognise "mielie
meal", "morogo", or "amasi" fails its market in the first thirty seconds. The alias table
is where this product either feels local or feels imported.

---

**Next:** [`07-API.md`](./07-API.md)
