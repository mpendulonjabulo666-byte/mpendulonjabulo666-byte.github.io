# 09 — Tech Stack & Repository Structure

---

## The stack

| Layer | Choice | Why this one |
|-------|--------|--------------|
| Framework | **Next.js 15** (App Router) | Server components keep the API key server-side by default. One deploy target for frontend and API. |
| Language | **TypeScript** (strict) | Catches the class of bug that ships to production at 2am. Non-negotiable on a paid project. |
| Styling | **Tailwind CSS** | Design tokens map directly to utilities. No naming things. |
| Components | **shadcn/ui** | Accessible primitives whose source you own, so restyling doesn't mean fighting a library. |
| Icons | **lucide-react** | Consistent, tree-shakeable, matches the design system. |
| Database | **Supabase** (Postgres) | Auth, database, and row-level security in one product. Generous free tier. |
| Auth | **Supabase Auth** | Email/password + Google out of the box. Don't build auth yourself. |
| AI | **Anthropic API** (`@anthropic-ai/sdk`) | `claude-opus-5`. See `08-AI-ENGINE.md`. |
| Validation | **Zod** | One schema serves request validation *and* the model's structured output. |
| Forms | **react-hook-form** + `@hookform/resolvers` | Pairs with Zod, minimal re-renders. |
| Data fetching | **TanStack Query** | Caching, optimistic updates, retries — the pantry needs all three. |
| Hosting | **Vercel** | Zero-config for Next.js, preview deploys per branch. |
| Errors | **Sentry** | You need to know about the 3am crash before the client does. |
| Analytics | **Vercel Analytics** or **Plausible** | Privacy-friendly, no cookie banner. |
| Testing | **Vitest** + **Playwright** | Unit for the allergen check, E2E for the critical path. |

**A note on the shape of this stack:** it's boring on purpose. Every piece is
well-documented, widely used, and easy to hire help for. On a client project, boring is a
feature.

---

## Repository structure

```
pantry-chef/
├── app/
│   ├── (marketing)/
│   │   ├── page.tsx                 # /
│   │   ├── layout.tsx               # marketing nav + footer
│   │   └── legal/
│   │       ├── privacy/page.tsx
│   │       └── terms/page.tsx
│   ├── (auth)/
│   │   ├── login/page.tsx
│   │   └── signup/page.tsx
│   ├── (app)/
│   │   ├── layout.tsx               # AppShell, requires a session
│   │   ├── onboarding/page.tsx
│   │   ├── pantry/page.tsx
│   │   ├── recipe/[id]/page.tsx
│   │   ├── saved/page.tsx
│   │   └── profile/page.tsx
│   ├── api/
│   │   ├── ingredients/search/route.ts
│   │   ├── ingredients/staples/route.ts
│   │   ├── pantry/route.ts
│   │   ├── pantry/items/route.ts
│   │   ├── pantry/items/[id]/route.ts
│   │   ├── recipes/generate/route.ts
│   │   ├── recipes/[id]/route.ts
│   │   ├── saved/route.ts
│   │   ├── saved/[recipeId]/route.ts
│   │   ├── profile/route.ts
│   │   └── account/route.ts
│   ├── layout.tsx
│   └── globals.css                  # design tokens live here
│
├── components/                      # see 04-COMPONENTS.md
│   ├── ui/  pantry/  recipe/  layout/  marketing/  shared/
│
├── lib/
│   ├── supabase/
│   │   ├── client.ts                # browser client (anon key)
│   │   ├── server.ts                # server client (cookie session)
│   │   └── admin.ts                 # service-role client — SERVER ONLY
│   ├── ai/
│   │   ├── client.ts                # Anthropic client
│   │   ├── prompts.ts               # system prompt + user message builder
│   │   ├── schemas.ts               # Zod schema for recipe output
│   │   └── generate.ts              # the orchestration in 07-API.md
│   ├── allergens/
│   │   ├── check.ts                 # the two-layer check
│   │   └── keywords.ts              # the over-eager keyword list
│   ├── ingredients/
│   │   ├── resolve.ts               # name/alias → canonical ingredient
│   │   └── categorise.ts
│   ├── cache/
│   │   └── recipe-cache.ts          # cache key + lookup
│   ├── rate-limit.ts
│   ├── validation/                  # Zod request schemas
│   └── utils.ts
│
├── types/
│   ├── database.ts                  # generated from Supabase — do not hand-edit
│   └── app.ts
│
├── supabase/
│   ├── migrations/
│   └── seed/
│       ├── ingredients.sql
│       └── aliases.sql
│
├── tests/
│   ├── unit/
│   └── e2e/
│
├── docs/                            # this folder
├── .env.local.example
├── CLAUDE.md
└── package.json
```

---

## Environment variables

```bash
# .env.local.example — commit this. Never commit .env.local itself.

# Supabase — the anon key is public by design, protected by RLS
NEXT_PUBLIC_SUPABASE_URL=https://xxxx.supabase.co
NEXT_PUBLIC_SUPABASE_ANON_KEY=eyJ...

# SERVER ONLY. Bypasses row-level security entirely.
SUPABASE_SERVICE_ROLE_KEY=eyJ...

# SERVER ONLY. Never prefix with NEXT_PUBLIC_.
ANTHROPIC_API_KEY=sk-ant-...

# Optional
SENTRY_DSN=
UPSTASH_REDIS_REST_URL=
UPSTASH_REDIS_REST_TOKEN=

NEXT_PUBLIC_APP_URL=http://localhost:3000
```

**The `NEXT_PUBLIC_` rule, stated once so it's unmissable:** anything with that prefix is
compiled into the JavaScript bundle and visible to every visitor. `ANTHROPIC_API_KEY` and
`SUPABASE_SERVICE_ROLE_KEY` must never have it. Not while debugging, not temporarily.

Add `.env.local` to `.gitignore` on the first commit, before there's anything in it to
leak.

---

## Getting set up

```bash
npx create-next-app@latest pantry-chef --typescript --tailwind --app --eslint
cd pantry-chef

npx shadcn@latest init
npx shadcn@latest add button input label card dialog sheet badge skeleton \
  toast tabs switch checkbox radio-group avatar separator tooltip \
  dropdown-menu command

npm install @supabase/supabase-js @supabase/ssr \
            @anthropic-ai/sdk zod react-hook-form @hookform/resolvers \
            @tanstack/react-query lucide-react

npm install -D vitest @playwright/test @types/node

cp .env.local.example .env.local   # then fill it in
npm run dev
```

---

## Conventions

**Files**
- Components: `PascalCase.tsx`
- Everything else: `kebab-case.ts`
- Route handlers: always `route.ts` (Next.js requirement)

**Components**
- Server components by default. `'use client'` only where you need state, effects, or
  event handlers — and put it as low in the tree as possible.
- Props interfaces are named `<Component>Props` and exported.
- No default exports except for pages and route handlers (Next.js requires those).

**TypeScript**
- `strict: true`. No exceptions.
- No `any`. If you genuinely can't type it, `unknown` plus a narrowing check.
- Database types are generated: `npx supabase gen types typescript --local > types/database.ts`.

**Styling**
- Tailwind utilities only. No CSS modules, no styled-components.
- Design tokens are CSS variables in `globals.css`, referenced through Tailwind config.
- No arbitrary values (`w-[347px]`) unless there's a comment explaining why.

**Git**
- Branch names: `feat/pantry-search`, `fix/allergen-check`, `chore/deps`.
- Conventional commits: `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`.
- One logical change per PR. A 40-file PR gets rubber-stamped, which defeats the point.

---

## Testing priorities

Not everything needs a test. These do:

| Priority | What | Why |
|----------|------|-----|
| **Critical** | Allergen check (`lib/allergens/check.ts`) | The one place a bug hurts a person. Unit test it exhaustively, including the keyword fallback. |
| **High** | Ingredient resolution | Wrong here means wrong recipes everywhere. |
| **High** | Cache key generation | Must be order-independent. Easy to break, silent when broken. |
| **High** | E2E: signup → onboard → add ingredients → generate → save | The path that must never break. |
| **Medium** | API request validation | Zod does the work; test the edges. |
| **Low** | Component rendering | Diminishing returns for a solo build. |

---

## Performance targets

| Metric | Target |
|--------|--------|
| Lighthouse Performance (landing) | ≥ 90 |
| Lighthouse Accessibility (all pages) | ≥ 95 |
| Largest Contentful Paint | < 2.5s |
| Cumulative Layout Shift | < 0.1 |
| Ingredient search response | < 200ms |
| Recipe generation (uncached) | < 12s median |
| Recipe generation (cached) | < 500ms |

The accessibility number is the one to hold firmest. It's also the easiest to hit if the
design system is followed, and painful to retrofit if it isn't.

---

**Next:** [`10-ROADMAP.md`](./10-ROADMAP.md)
