# 03 — Design System

The point of this file: every screen should look like it was made by the same person on
the same day. Pick the values here once, put them in `globals.css`, and never hand-write a
hex code in a component again.

---

## 1. Design principles

1. **The fridge is the hero.** Ingredients are the content. Chrome gets out of the way.
2. **One obvious action per screen.** On the pantry screen, it's Generate. Everything else
   is quieter.
3. **Food deserves warmth.** Fresh greens and warm neutrals — not the cold blue-grey of a
   SaaS dashboard.
4. **Fast to scan.** People use this hungry and impatient. Big touch targets, high
   contrast, no dense paragraphs.
5. **Honest about uncertainty.** Nutrition estimates look like estimates, not lab results.

---

## 2. Colour

Semantic names only. A component asks for `--color-primary`, never for "green".

```css
:root {
  /* Brand — fresh, herbaceous green */
  --color-primary:          #2F7A4E;
  --color-primary-hover:    #276541;
  --color-primary-subtle:   #E8F3ED;
  --color-primary-fg:       #FFFFFF;

  /* Accent — warm citrus, used sparingly for highlights and streaks */
  --color-accent:           #E8833A;
  --color-accent-subtle:    #FDF0E5;

  /* Neutrals — warm-tinted, never pure grey */
  --color-bg:               #FBFAF7;
  --color-surface:          #FFFFFF;
  --color-surface-raised:   #FFFFFF;
  --color-border:           #E6E2DA;
  --color-border-strong:    #CFC9BE;

  /* Text */
  --color-fg:               #1C1B18;
  --color-fg-muted:         #6B665C;
  --color-fg-subtle:        #979185;

  /* Status */
  --color-success:          #2F7A4E;
  --color-warning:          #B4690E;
  --color-warning-subtle:   #FDF3E3;
  --color-danger:           #B3261E;
  --color-danger-subtle:    #FCEEED;
  --color-info:             #1E5F8C;

  /* Nutrition chart colours — distinct in both themes, colourblind-safe */
  --color-macro-protein:    #2F7A4E;
  --color-macro-carbs:      #E8833A;
  --color-macro-fat:        #7A5CB0;
}

@media (prefers-color-scheme: dark) {
  :root {
    --color-primary:        #5FBF89;
    --color-primary-hover:  #75CD9B;
    --color-primary-subtle: #16251D;
    --color-primary-fg:     #0B1410;

    --color-accent:         #F0A468;
    --color-accent-subtle:  #2A1D12;

    --color-bg:             #14130F;
    --color-surface:        #1D1B16;
    --color-surface-raised: #26241E;
    --color-border:         #34312A;
    --color-border-strong:  #4A463D;

    --color-fg:             #F5F3EE;
    --color-fg-muted:       #A9A398;
    --color-fg-subtle:      #7C766B;

    --color-warning:        #E5A44A;
    --color-warning-subtle: #2A2113;
    --color-danger:         #F0857D;
    --color-danger-subtle:  #2E1715;
  }
}
```

**Rules**
- Body text against background must clear **4.5:1**. Large text and UI borders, **3:1**.
- Never encode meaning in colour alone. "Missing ingredient" gets an icon *and* a label,
  not just orange text.
- The accent is for highlights and small delights. If a screen has more than one accent
  element, one of them is wrong.

---

## 3. Typography

Two families. More than two is a hobby, not a system.

```css
--font-display: 'Fraunces', Georgia, serif;      /* headings — warm, editorial */
--font-body:    'Inter', system-ui, sans-serif;  /* everything else */
```

If self-hosting fonts is a hassle in week one, ship with `Georgia` and `system-ui` and
swap later. The scale matters more than the typeface.

### Scale

| Token | Size / line-height | Weight | Used for |
|-------|--------------------|--------|----------|
| `--text-display` | 3rem / 1.1 | 600 | Landing hero only |
| `--text-h1` | 2rem / 1.2 | 600 | Page titles, recipe titles |
| `--text-h2` | 1.5rem / 1.3 | 600 | Section headings |
| `--text-h3` | 1.25rem / 1.4 | 600 | Card titles |
| `--text-body-lg` | 1.125rem / 1.6 | 400 | Recipe steps, landing body |
| `--text-body` | 1rem / 1.6 | 400 | Default |
| `--text-sm` | 0.875rem / 1.5 | 400 | Helper text, metadata |
| `--text-xs` | 0.75rem / 1.4 | 500 | Chips, badges, labels |

Headings use `--font-display`. Everything from `--text-body-lg` down uses `--font-body`.

**Recipe steps get `--text-body-lg`.** People read them at arm's length with wet hands.

---

## 4. Spacing

A 4px base scale. Never invent a value between these.

```
--space-1: 4px     --space-5: 24px
--space-2: 8px     --space-6: 32px
--space-3: 12px    --space-8: 48px
--space-4: 16px    --space-10: 64px
                   --space-12: 96px
```

Section padding: `--space-6` mobile, `--space-10` desktop.
Card padding: `--space-5`.
Gap between related items: `--space-3`. Between unrelated groups: `--space-6`.

---

## 5. Radius, shadow, borders

```css
--radius-sm:   6px;    /* chips, badges */
--radius-md:   10px;   /* buttons, inputs */
--radius-lg:   16px;   /* cards */
--radius-xl:   24px;   /* modals, hero panels */
--radius-full: 9999px; /* avatars, pills */

--shadow-sm: 0 1px 2px rgb(28 27 24 / 0.05);
--shadow-md: 0 4px 12px rgb(28 27 24 / 0.08);
--shadow-lg: 0 12px 32px rgb(28 27 24 / 0.12);
```

Dark mode: shadows are nearly invisible on dark surfaces. Use `--color-border` and
`--color-surface-raised` for elevation there instead of increasing shadow opacity.

Borders are `1px solid var(--color-border)`. One weight everywhere.

---

## 6. Motion

```css
--ease-out:   cubic-bezier(0.16, 1, 0.3, 1);
--ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);

--duration-fast:   120ms;  /* hover, focus */
--duration-normal: 200ms;  /* chips appearing, cards */
--duration-slow:   320ms;  /* modals, page transitions */
```

Where motion is allowed:
- Ingredient chip appears when added — scale `0.9 → 1` + fade, `--duration-normal`.
- Generate button gets a gentle pulse once it becomes enabled. Once, not looping.
- Loading state cycles copy with a crossfade.
- Recipe card lifts 2px on hover, desktop only.

Where it's banned: anything auto-playing, anything longer than 400ms, anything that
delays the user reading a recipe.

**Always respect the user's setting:**

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

## 7. Layout & breakpoints

```
sm   640px   large phone
md   768px   tablet
lg  1024px   laptop
xl  1280px   desktop
```

Max content width: `1140px`, centred. Reading-heavy content (recipe steps, legal pages)
caps at `680px` — long lines are hard to follow.

**Mobile-first, genuinely.** Most people use this standing in a kitchen. Build the
375px-wide version first and let it grow.

---

## 8. Accessibility requirements

Not a nice-to-have. Non-negotiable baseline:

- Every interactive element reachable and operable by keyboard.
- Visible focus ring: `2px solid var(--color-primary)` with `2px` offset. Never
  `outline: none` without a replacement.
- Touch targets ≥ **44×44px**. Ingredient chips especially — their remove button is the
  most-tapped small target in the app.
- All images have `alt`. Decorative ones get `alt=""`.
- Form inputs have real `<label>`s. Placeholders are not labels.
- Loading states announce via `aria-live="polite"`.
- Error messages are tied to their input with `aria-describedby`.
- Colour is never the only signal.
- The page must survive 200% browser zoom without horizontal scroll.

---

## 9. Iconography

One set: **Lucide** (`lucide-react`). Default size 20px, stroke 1.75.

Semantic mapping — keep it consistent or the UI stops being learnable:

| Icon | Means |
|------|-------|
| `Plus` | Add ingredient |
| `X` | Remove |
| `Search` | Search |
| `Sparkles` | Generate recipe |
| `Bookmark` / `BookmarkCheck` | Save / saved |
| `Clock` | Cook time |
| `Users` | Servings |
| `Flame` | Calories |
| `AlertTriangle` | Allergen warning |
| `ShoppingBasket` | Missing ingredients |
| `RefreshCw` | Regenerate |

---

## 10. Voice & tone

Warm, direct, never cutesy. You're a capable friend who cooks, not a brand mascot.

| Instead of | Write |
|------------|-------|
| "Oops! Something went wrong 😅" | "Something went wrong on our end. Try again?" |
| "Utilise your available ingredients" | "Use what you've got" |
| "Recipe generation in progress…" | "Reading your pantry…" |
| "0 items in pantry" | "Let's fill your kitchen" |
| "Submit" | "Find me a recipe" |

Buttons are verbs. Errors say what happened and what to do next. No exclamation marks in
error states — nothing about an error is exciting.

---

**Next:** [`04-COMPONENTS.md`](./04-COMPONENTS.md)
