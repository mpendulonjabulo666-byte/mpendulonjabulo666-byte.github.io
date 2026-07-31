# PantryChef — Project Context

Copy this file to the **root** of the build repo. Claude Code reads it automatically at
the start of every session, so these rules apply without being restated each time.

---

## What this is

A web app where a user lists the ingredients they have and gets a healthy recipe built
from them, respecting their allergens, diet, and health goal.

Full specification is in `docs/`. Read the relevant file before implementing a feature —
don't infer the design from surrounding code.

| Need | File |
|------|------|
| Product scope, what's in and out of v1 | `docs/01-PRD.md` |
| Screen-to-screen flows, empty and error states | `docs/02-USER-FLOWS.md` |
| Colours, type, spacing, motion, accessibility | `docs/03-DESIGN-SYSTEM.md` |
| Component APIs and props | `docs/04-COMPONENTS.md` |
| What goes on each page | `docs/05-PAGES.md` |
| Database schema | `docs/06-DATA-MODEL.md` |
| Endpoints and error codes | `docs/07-API.md` |
| Recipe generation, prompts, allergen safety, cost | `docs/08-AI-ENGINE.md` |
| Stack, folder structure, conventions | `docs/09-TECH-STACK.md` |

---

## Stack

Next.js 15 (App Router) · TypeScript strict · Tailwind · shadcn/ui · Supabase (Postgres +
Auth) · Anthropic API (`claude-opus-5`) · Zod · TanStack Query · Vercel.

---

## Rules that are not negotiable

### Security

- `ANTHROPIC_API_KEY` and `SUPABASE_SERVICE_ROLE_KEY` are **server-only**. Never prefixed
  with `NEXT_PUBLIC_`, never imported into a client component, not even temporarily while
  debugging.
- Every user-owned table has RLS enabled with a policy. A Supabase table without RLS is
  readable by anyone holding the anon key, which ships in the browser bundle.
- Generation reads the user's allergens, diets, and goal **from the database using the
  session** — never from the request body.
- Every request body is validated with Zod before touching the database.

### Allergen safety

This is the highest-stakes code in the project.

- Allergen exclusion is enforced **twice**: in the prompt, and again in code against the
  user's allergen list after generation.
- A recipe that fails the code check is discarded server-side and regenerated. It is never
  shown to the user — not greyed out, not with a warning, not briefly.
- After 3 failed attempts, return `422 NO_SAFE_RECIPE`.
- `<Disclaimer variant="nutrition" />` renders wherever nutrition is shown.
  `<Disclaimer variant="allergen" />` renders near allergen controls. Never remove these.
- `lib/allergens/check.ts` requires unit tests covering the catalogue path and the keyword
  fallback. Do not modify it without updating them.

### Design system

- Use design tokens. No raw hex values, no arbitrary Tailwind values (`w-[347px]`) without
  a comment explaining the exception.
- Interactive targets are ≥44×44px. Ingredient chip remove buttons especially — use
  padding to grow the hit area, not the visual size.
- Every screen works at 375px and 1440px, in light and dark mode.
- Visible focus states. Never `outline: none` without a replacement.
- Colour is never the only carrier of meaning.

### Components

- Check `docs/04-COMPONENTS.md` before creating anything new. If something similar exists,
  extend it rather than forking it.
- Server components by default. `'use client'` only when state, effects, or event handlers
  require it, and placed as low in the tree as possible.
- Props interfaces named `<Component>Props` and exported.

### Cost

- Recipe generation costs real money per call. Check the cache before generating.
- Cache keys must be order-independent — sort ingredient IDs before hashing.
- The system prompt carries `cache_control: { type: 'ephemeral' }` and must stay
  byte-identical between requests. Nothing volatile in it: no dates, no user IDs, no
  pantry contents. Those go in the user message.
- Every generation attempt writes a `generation_events` row, including cache hits,
  allergen rejections, and errors.

### Anthropic API specifics

- Model: `claude-opus-5`. Don't substitute a different model without asking — it's a
  budget decision that belongs to the client.
- Thinking is **on by default** on Opus 5, and `max_tokens` caps thinking *plus* the
  response. Use `max_tokens: 8000` for generation so the JSON can't truncate.
- Use `client.messages.parse()` with `output_config: { format: zodOutputFormat(Schema) }`
  — not the deprecated top-level `output_format`.
- Check `response.stop_reason` before reading content. A refusal returns HTTP 200 with no
  usable output; reading `parsed_output` directly will crash.
- Never call the Anthropic API from a client component.

---

## Conventions

- Components `PascalCase.tsx`; everything else `kebab-case.ts`; route handlers `route.ts`.
- `strict: true`. No `any` — use `unknown` plus narrowing if you genuinely can't type it.
- Database types are generated, not hand-written:
  `npx supabase gen types typescript --local > types/database.ts`
- Branches: `feat/`, `fix/`, `chore/`. Conventional commits.
- Tailwind utilities only — no CSS modules, no styled-components.

---

## Definition of done

A feature is finished when:

- [ ] It matches the spec in `docs/`
- [ ] Loading, empty, and error states exist
- [ ] It works at 375px and 1440px
- [ ] It works in light and dark mode
- [ ] It's keyboard navigable with visible focus
- [ ] Touch targets are ≥44px
- [ ] TypeScript compiles with no errors and no `any`
- [ ] Anything safety-critical has tests

---

## Out of scope for v1

Don't build these, don't scaffold for them, don't add "just in case" columns:

photo ingredient recognition · barcode scanning · meal planning · shopping lists · grocery
integrations · social features · user-submitted recipes · offline mode · native apps ·
multi-language · voice input · expiry tracking · cost-per-meal

Full list with reasoning: `docs/01-PRD.md` § 6.

---

## How to ask for work on this project

Point at the spec. Vague prompts produce vague apps.

**Good:**
> Build the pantry screen. Follow `docs/05-PAGES.md` § `/pantry` and use the components in
> `docs/04-COMPONENTS.md`. Tokens from `docs/03-DESIGN-SYSTEM.md`. Don't invent new
> components.

**Bad:**
> Make the pantry page look nice.
