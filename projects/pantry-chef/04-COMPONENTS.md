# 04 — Component Library

Every component listed here is one you build once and reuse. If a screen needs something
that isn't in this file, either it belongs here (add it) or it's a one-off (keep it local
to that page). What must not happen is three slightly different button implementations.

Base layer: **shadcn/ui**. It gives you accessible primitives you own the source of, so
you can restyle them to the design system instead of fighting a library's defaults.

---

## Directory layout

```
components/
  ui/           shadcn primitives (button, input, dialog, …) — restyled, rarely edited
  pantry/       ingredient chip, search, category group, quick-add
  recipe/       recipe card, nutrition bar, step list, ingredient checklist
  layout/       app shell, nav, footer, page header
  marketing/    hero, feature grid, how-it-works, CTA band
  shared/       empty state, loading, error boundary, disclaimer
```

---

## 1. Primitives (`components/ui/`)

Pull these from shadcn, then restyle to the tokens in `03-DESIGN-SYSTEM.md`.

`Button` · `Input` · `Label` · `Badge` · `Card` · `Dialog` · `Sheet` · `Skeleton` ·
`Toast` · `Tabs` · `Switch` · `Checkbox` · `RadioGroup` · `Avatar` · `Separator` ·
`Tooltip` · `DropdownMenu`

### Button variants

| Variant | Look | Use for |
|---------|------|---------|
| `primary` | Solid `--color-primary` | The one main action per screen |
| `secondary` | Surface + border | Supporting actions |
| `ghost` | Transparent, hover tint | Icon buttons, tertiary actions |
| `destructive` | Solid `--color-danger` | Delete account, clear pantry |
| `link` | Text only, underline on hover | Inline navigation |

Sizes: `sm` (32px) · `md` (40px, default) · `lg` (48px, the Generate button).

Every button supports `loading` — shows a spinner, disables itself, keeps its width so
the layout doesn't jump.

---

## 2. Pantry components

### `<IngredientChip />`

The most-used component in the app. Get it right.

```ts
interface IngredientChipProps {
  ingredient: {
    id: string;
    name: string;         // display name, e.g. "Cherry tomatoes"
    category: Category;
    emoji?: string;
  };
  quantity?: string;      // free text, e.g. "2 cups" — optional in v1
  onRemove?: (id: string) => void;
  variant?: 'default' | 'missing' | 'staple';
  size?: 'sm' | 'md';
}
```

- Pill shaped (`--radius-full`), surface background, 1px border.
- Optional emoji, then name, then an `X` button when `onRemove` is passed.
- **The `X` must have a ≥44px hit area** even though it looks ~20px. Use padding, not size.
- `variant="missing"` → accent-subtle background, `ShoppingBasket` icon.
- `variant="staple"` → muted, no remove button ("we assume you have salt").
- Animates in: scale `0.9 → 1` + fade over `--duration-normal`.
- `aria-label` on remove: `"Remove {name} from pantry"`.

### `<IngredientSearch />`

```ts
interface IngredientSearchProps {
  onSelect: (ingredient: Ingredient) => void;
  excludeIds?: string[];    // already in pantry — don't offer twice
  placeholder?: string;
  autoFocus?: boolean;
}
```

Behaviour:
- Debounced 200ms, queries the ingredient catalogue.
- Keyboard: `↑`/`↓` move, `Enter` selects, `Esc` closes.
- Results show emoji + name + category, grouped by category when > 5 results.
- No match → last row is **"Add '{query}' as a custom ingredient"**. This is what saves
  you when the catalogue is missing something regional.
- Combobox pattern with correct ARIA (`role="combobox"`, `aria-expanded`,
  `aria-activedescendant`). shadcn's `Command` gets you most of this.

### `<CategoryGroup />`

```ts
interface CategoryGroupProps {
  category: Category;       // 'produce' | 'protein' | 'dairy' | 'grains' | 'pantry' | 'spices' | 'other'
  ingredients: Ingredient[];
  onRemove: (id: string) => void;
  collapsible?: boolean;
}
```

Header row: emoji + category name + count. Chips wrap below. Collapsible once the group
has more than 8 items.

### `<QuickAddChips />`

```ts
interface QuickAddChipsProps {
  suggestions: Ingredient[];   // ~12 common staples
  onAdd: (ingredient: Ingredient) => void;
}
```

Shown in the empty pantry state and under the search when the pantry is small. This is the
single biggest lever on "did the user get to their first recipe" — make it prominent.

### `<GenerateButton />`

```ts
interface GenerateButtonProps {
  disabled: boolean;
  ingredientCount: number;
  minRequired?: number;      // default 3
  loading?: boolean;
  onClick: () => void;
}
```

- Full-width on mobile, sticky to the bottom of the viewport.
- Label: `"Find me a recipe"`. With `Sparkles` icon.
- Disabled → helper text below: `"Add {n} more ingredient{s} to get started."`
- One-time gentle pulse when it transitions from disabled to enabled.

---

## 3. Recipe components

### `<RecipeCard />`

```ts
interface RecipeCardProps {
  recipe: {
    id: string;
    title: string;
    imageUrl?: string;
    cookTimeMinutes: number;
    servings: number;
    calories?: number;
    matchPercent?: number;      // % of ingredients the user already has
  };
  saved?: boolean;
  onSave?: (id: string) => void;
  href: string;
}
```

Image (or a gradient placeholder with the cuisine emoji), title, then a metadata row:
`Clock` time · `Users` servings · `Flame` kcal. Bookmark button top-right, absolutely
positioned, ≥44px target. Match percent as a small badge when present.

### `<NutritionPanel />`

```ts
interface NutritionPanelProps {
  nutrition: {
    calories: number;
    proteinG: number;
    carbsG: number;
    fatG: number;
    fiberG?: number;
    sodiumMg?: number;
  };
  servings: number;
  perServing?: boolean;    // default true
}
```

- Calories large and first.
- Protein / carbs / fat as a single horizontal stacked bar using the macro colours from
  the design system, with a legend. Not a pie chart — stacked bars read better at small
  sizes and are easier to make accessible.
- Each macro also shows its gram value as text. Never colour-only.
- **Always renders `<Disclaimer variant="nutrition" />` beneath it.** Not optional.

### `<IngredientChecklist />`

```ts
interface IngredientChecklistProps {
  have: RecipeIngredient[];
  missing: RecipeIngredient[];
  servingsMultiplier: number;   // quantities scale with this
}
```

Two sections: **"You have"** (check icon, normal) and **"You'll need"** (basket icon,
accent-subtle background). Quantities recompute live when servings change. Each row is
tappable to strike through — useful while actually cooking.

### `<StepList />`

```ts
interface StepListProps {
  steps: { text: string; durationMinutes?: number }[];
}
```

- Numbered, large text (`--text-body-lg`), generous line height.
- Tap a step to mark it done — it dims. State is local only; nothing to persist.
- Steps with a duration get a small `Clock` badge.

### `<ServingsScaler />`

```ts
interface ServingsScalerProps {
  value: number;
  baseServings: number;
  min?: number;   // 1
  max?: number;   // 12
  onChange: (servings: number) => void;
}
```

Minus / value / plus. Both buttons ≥44px. Changing it rescales the ingredient quantities
and the nutrition panel — no API call, it's arithmetic.

### `<MissingIngredientsCallout />`

```ts
interface MissingIngredientsCalloutProps {
  ingredients: RecipeIngredient[];
  onAddAllToPantry?: () => void;
}
```

Accent-subtle panel: *"You'll need 2 more things"* + chips. Optional button to add them
all to the pantry (for when the user has just bought them).

---

## 4. Layout components

### `<AppShell />`
Authenticated wrapper. Bottom tab bar on mobile (Pantry · Saved · Profile), sidebar on
desktop. Handles safe-area insets so the bottom bar doesn't sit under the iPhone home
indicator.

### `<PageHeader />`
```ts
interface PageHeaderProps {
  title: string;
  subtitle?: string;
  action?: React.ReactNode;
}
```

### `<MarketingNav />` / `<MarketingFooter />`
Public pages only. Logo, links, "Sign in", "Get started free".

---

## 5. Shared components

### `<EmptyState />`
```ts
interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  description: string;
  action?: { label: string; onClick: () => void };
}
```
Used by: empty pantry, no saved recipes, no search results.

### `<LoadingRecipe />`
The 6–12 second wait. Not a bare spinner — a spinner for ten seconds feels broken.

- Skeleton of the recipe layout.
- Copy that cycles every 3s: *"Reading your pantry…"* → *"Finding something good…"* →
  *"Checking it's safe for you…"* → *"Almost there…"*
- `aria-live="polite"` so screen readers get the updates.

### `<Disclaimer />`
```ts
interface DisclaimerProps {
  variant: 'nutrition' | 'allergen' | 'medical';
}
```

Fixed copy, so it can't drift between screens:

| Variant | Copy |
|---------|------|
| `nutrition` | "Nutrition values are AI-generated estimates. Don't rely on them for medical decisions." |
| `allergen` | "We filter for the allergens you've told us about, but always check ingredient labels yourself." |
| `medical` | "PantryChef gives general suggestions, not medical or dietary advice." |

Small text, muted, `AlertTriangle` icon. **Present, not hidden.**

### `<ErrorBoundary />`
Catches render errors, shows a recoverable message, logs to the error service. Wraps each
route.

---

## 6. Rules for adding a component

1. Does something similar already exist? Extend it rather than forking it.
2. Does it use only design tokens? No raw hex, no arbitrary pixel values.
3. Keyboard accessible? Focus visible?
4. Does it have a loading state, an empty state, and an error state where relevant?
5. Are all interactive targets ≥44px?
6. Does it work at 375px wide *and* 1440px?
7. Does it look right in dark mode?

If any answer is no, it isn't finished.

---

**Next:** [`05-PAGES.md`](./05-PAGES.md)
