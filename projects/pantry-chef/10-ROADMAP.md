# 10 — Build Roadmap

Sequenced so that each phase produces something demonstrable. A client who sees progress
every week stays calm; a client who sees nothing for a month starts asking uncomfortable
questions regardless of how much work you've done.

Estimates assume **one part-time developer, roughly 15–20 hours a week**. Adjust and be
honest about it — see `11-CLIENT-BRIEF.md` § Timeline.

---

## Phase 0 — Foundations (~1 week)

Nothing visible ships. This is unavoidable and worth explaining to the client up front.

- [ ] Repo, Next.js + TypeScript + Tailwind, `strict` on
- [ ] shadcn/ui installed, restyled to `03-DESIGN-SYSTEM.md`
- [ ] Design tokens in `globals.css`, wired into `tailwind.config`
- [ ] Supabase project; migrations for every table in `06-DATA-MODEL.md`
- [ ] RLS policies on every user-owned table
- [ ] Ingredient catalogue seeded (~500) with allergen annotations
- [ ] Alias table seeded (~1,500)
- [ ] Environment variables set locally and on Vercel
- [ ] Deployed to Vercel with a working preview URL
- [ ] `CLAUDE.md` in the repo root

**Demonstrable:** a deployed URL showing a styled placeholder. Sounds thin; it proves the
whole pipeline works.

---

## Phase 1 — Auth & onboarding (~1 week)

- [ ] `/signup`, `/login` with email/password and Google
- [ ] Session handling, protected route group
- [ ] Trigger creating a `profiles` row on signup
- [ ] Three-step onboarding wizard
- [ ] Preferences persisted
- [ ] `<Disclaimer>` component with all three variants
- [ ] Redirect logic: onboarded users skip the wizard

**Demonstrable:** the client can create an account and set their preferences.

---

## Phase 2 — The pantry (~1.5 weeks)

The screen the product lives on. Don't rush it.

- [ ] `GET /api/ingredients/search` with alias matching
- [ ] `<IngredientSearch>` with full keyboard support
- [ ] `<IngredientChip>` with a genuinely 44px remove target
- [ ] `<CategoryGroup>`
- [ ] `<QuickAddChips>`
- [ ] Pantry CRUD endpoints
- [ ] Optimistic add/remove via TanStack Query
- [ ] Empty state
- [ ] Custom ingredient path ("Add 'biltong'")
- [ ] `<GenerateButton>` with the disabled state and helper text

**Demonstrable:** the client can build a pantry on their phone. This is the first moment
it feels like a real product.

---

## Phase 3 — The engine (~1.5 weeks)

- [ ] Anthropic client, server-side only
- [ ] System prompt, user message builder
- [ ] Zod recipe schema + `messages.parse()` with `output_config.format`
- [ ] **Two-layer allergen check with unit tests** ← do this before anything downstream
- [ ] Retry-on-violation, max 3 attempts
- [ ] Ingredient normalisation of model output; have/missing split
- [ ] Recipe persistence
- [ ] `generation_events` logging on every outcome
- [ ] Cache key + lookup
- [ ] Prompt caching on the system prompt, verified via `cache_read_input_tokens`
- [ ] Rate limiting
- [ ] `POST /api/recipes/generate` end to end

**Demonstrable:** a real recipe, from a real pantry, in the terminal or a raw JSON view.

**This is the highest-risk phase.** If something slips, it's this. Build the allergen check
and its tests *first* — everything else depends on being able to trust the output.

---

## Phase 4 — Recipe UI (~1 week)

- [ ] `<LoadingRecipe>` with cycling copy
- [ ] `/recipe/[id]` full layout
- [ ] `<IngredientChecklist>`, `<StepList>`, `<NutritionPanel>`
- [ ] `<ServingsScaler>` with live rescaling
- [ ] `<MissingIngredientsCallout>`
- [ ] Save / unsave
- [ ] Regenerate with exclusions
- [ ] Disclaimers in place

**Demonstrable:** the full loop — pantry → generate → recipe → save. **This is the demo
that gets the project approved.** Aim for it by end of week 6.

---

## Phase 5 — Saved & profile (~0.5 weeks)

- [ ] `/saved` with grid, search, filter chips
- [ ] Allergen-conflict badge on saved recipes
- [ ] `/profile` — all sections
- [ ] Usage display
- [ ] Theme and units preferences
- [ ] Delete account with typed confirmation

---

## Phase 6 — Landing page (~1 week)

- [ ] All seven sections from `05-PAGES.md`
- [ ] Hero animation (or a good static screenshot — ship the screenshot if time is tight)
- [ ] Three real sample recipes with real photography
- [ ] FAQ accordion
- [ ] SEO: titles, meta, Open Graph, JSON-LD
- [ ] Legal pages (content from the client)
- [ ] Lighthouse ≥ 90 performance, ≥ 95 accessibility

---

## Phase 7 — Polish & launch (~1 week)

- [ ] Every empty and error state from `02-USER-FLOWS.md`
- [ ] Full keyboard pass
- [ ] Screen reader pass on the critical path
- [ ] Dark mode verified on every screen
- [ ] 375px and 1440px verified on every screen
- [ ] Sentry live
- [ ] Analytics live
- [ ] E2E test of the critical path
- [ ] 50-recipe quality evaluation (`08-AI-ENGINE.md` § 10)
- [ ] Cost per generation measured against the estimate
- [ ] Spend alarm configured
- [ ] Custom domain, SSL
- [ ] Client walkthrough and handover

---

## Timeline summary

| Phase | Duration | Cumulative |
|-------|----------|-----------|
| 0 — Foundations | 1 wk | 1 |
| 1 — Auth & onboarding | 1 wk | 2 |
| 2 — Pantry | 1.5 wk | 3.5 |
| 3 — Engine | 1.5 wk | 5 |
| 4 — Recipe UI | 1 wk | 6 |
| 5 — Saved & profile | 0.5 wk | 6.5 |
| 6 — Landing | 1 wk | 7.5 |
| 7 — Polish & launch | 1 wk | **8.5** |

**~8–9 weeks part-time.** Add 15–20% buffer before quoting — you will lose time to
something, and it's better to be early than apologetic.

If the client needs it faster, the honest levers are: cut the landing page to a single
hero-plus-CTA (saves ~0.5 wk), cut `/saved` filters, or cut dark mode (saves ~0.5 wk).
**Do not cut** the allergen check, the disclaimers, or the accessibility pass. Say so
plainly if asked.

---

## Milestones for the client

Agree these in writing. They're the natural payment checkpoints.

| Milestone | When | What they see |
|-----------|------|---------------|
| M1 — Foundations | End wk 1 | Deployed URL, database live |
| M2 — Accounts | End wk 2 | They can sign up and set preferences |
| M3 — Pantry | End wk 3.5 | They can build a pantry on their phone |
| M4 — **First recipe** | End wk 6 | The full loop works |
| M5 — Complete app | End wk 7.5 | Every screen done |
| M6 — Launch | End wk 8.5 | Live on their domain |

M4 is the one that matters. Everything before it is scaffolding; everything after is
finishing. Set expectations accordingly.

---

## What v2 looks like

Keep this list visible so "can we just add…" has somewhere to go that isn't v1:

**Likely next:** shopping list from missing ingredients · meal planning · expiry tracking
and use-it-first nudges · recipe photos from an image model · print/share view

**Bigger bets:** fridge photo recognition · barcode scanning · grocery delivery
integration · household shared pantries · native apps

**Monetisation, whenever the client is ready:** free tier with the daily cap already
built · paid tier for unlimited generation and meal planning · affiliate grocery links

---

**Next:** [`11-CLIENT-BRIEF.md`](./11-CLIENT-BRIEF.md)
