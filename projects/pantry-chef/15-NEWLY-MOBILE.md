# 15 — Native Mobile on Newly: what changes

Newly generates **native iOS and Android apps in React Native + Expo**, with a **Supabase
backend included** (Postgres, auth, storage), full source-code export to GitHub, and
one-click store submission.

The specs in files 01–12 were written assuming a responsive **web** app. Most of that
survives intact. This file covers what doesn't.

> This also answers blocking question #3 in `11-CLIENT-BRIEF.md`. The answer is **native
> mobile, both platforms**. Update the client conversation accordingly — it changes the
> deliverables and adds a store-submission phase.

---

## 1. What carries over unchanged

More than you'd expect. Don't rewrite any of this:

| Spec | Status |
|---|---|
| `01-PRD.md` — product, features, scope | ✅ Unchanged |
| `02-USER-FLOWS.md` — flows, empty and error states | ✅ Unchanged |
| `06-DATA-MODEL.md` — schema, RLS | ✅ **Unchanged — Newly uses Supabase already** |
| `08-AI-ENGINE.md` — prompts, allergen safety, caching | ✅ Unchanged |
| `12-SOUTH-AFRICA.md` — the entire localisation | ✅ Unchanged |
| `03-DESIGN-SYSTEM.md` — tokens, scale, principles | ⚠️ Values hold, syntax changes |
| `04-COMPONENTS.md` — component list and props | ⚠️ Concepts hold, primitives change |
| `05-PAGES.md` — screen content | ⚠️ Content holds, routing changes |
| `07-API.md` — endpoints | ⚠️ Becomes Edge Functions |
| `09-TECH-STACK.md` — Next.js, Vercel, Tailwind | ❌ Superseded by this file |

The data model surviving is the big one. It's the part that's expensive to get wrong and
it needed no changes at all.

---

## 2. The stack, corrected

| Layer | Was (spec) | Is (Newly) |
|---|---|---|
| Framework | Next.js 15 web | **React Native + Expo** |
| Styling | Tailwind CSS | **StyleSheet / NativeWind** |
| Components | shadcn/ui | **React Native primitives** |
| Navigation | URL routes | **React Navigation — tabs + stack** |
| Database | Supabase | **Supabase** ✅ same |
| Auth | Supabase Auth | **Supabase Auth** ✅ same, plus Apple Sign-In |
| Server code | Next.js route handlers | **Supabase Edge Functions** |
| Hosting | Vercel | **App Store + Google Play** |

**Apple Sign-In is not optional.** If the app offers Google sign-in, Apple's guidelines
require Sign in with Apple too. Newly supports it; make sure it's enabled or the app gets
rejected at review.

---

## 3. ⚠️ The API key problem is now worse, and here's the fix

On the web, the risk was the key ending up in a JavaScript bundle. On mobile it's sharper:
**a shipped app binary can be downloaded and decompiled by anyone.** Any string inside it —
including an API key — can be extracted. There is no "hidden" place in a mobile app. An
`.env` file bundled into the app is not a secret; it's a slightly inconvenient text file.

If the Anthropic key ships inside the app, assume it will be found and used, and billed to
the lecturer.

**The fix, which Newly already gives you the pieces for:**

```
App (React Native)
      │  authenticated request, user's session token
      ▼
Supabase Edge Function          ← ANTHROPIC_API_KEY lives HERE, as a Supabase secret
      │
      ▼
Anthropic API
```

The Edge Function:
1. Verifies the user's session and gets their user ID
2. Loads their allergens, diets, health goal, budget mode and cooking constraint **from
   the database** — never from the request
3. Calls Anthropic with the key held server-side
4. Runs the code-level allergen check
5. Writes the recipe and returns it

The key is set with `supabase secrets set ANTHROPIC_API_KEY=sk-ant-...` and never appears
in the app source at all.

**How to verify after building:** the app must make **zero** network requests to
`api.anthropic.com`. Every generation request should go to your Supabase Edge Function URL.
If you see the app talking to Anthropic directly, the key is in the binary — stop and fix
it before shipping anything to a store.

---

## 4. Screens, not routes

Same content, different navigation model. `05-PAGES.md` still describes what goes on each
screen; ignore the URLs.

```
Auth stack (signed out)
  Welcome  →  Sign up  →  Sign in

Onboarding stack (first run)
  Allergens  →  Diet  →  Health goal

Main app — bottom tab bar
  🥕 Pantry     (home tab)
  🔖 Saved
  👤 Profile

Modal / pushed screens
  Recipe detail        (pushed from Pantry or Saved)
  Generating           (full-screen modal)
```

Three tabs, matching the three web routes. The bottom tab bar replaces the web nav, and it
suits this product well — the pantry is genuinely the home screen.

**The landing page has no home in the app.** It becomes two separate things:
- **App Store / Play Store listing** — screenshots, description, keywords
- **Optionally a marketing website**, which is a separate build and separate scope. Flag
  that to the client rather than absorbing it silently.

---

## 5. Design tokens in React Native

The values in `03-DESIGN-SYSTEM.md` are all still correct. The syntax isn't — React Native
has no CSS variables. Use a theme object:

```js
export const colors = {
  light: {
    primary: '#2F7A4E', primaryHover: '#276541', primarySubtle: '#E8F3ED',
    primaryFg: '#FFFFFF', accent: '#E8833A', accentSubtle: '#FDF0E5',
    bg: '#FBFAF7', surface: '#FFFFFF',
    border: '#E6E2DA', borderStrong: '#CFC9BE',
    fg: '#1C1B18', fgMuted: '#6B665C', fgSubtle: '#979185',
    warning: '#B4690E', warningSubtle: '#FDF3E3',
    danger: '#B3261E', dangerSubtle: '#FCEEED',
  },
  dark: {
    primary: '#5FBF89', primaryHover: '#75CD9B', primarySubtle: '#16251D',
    primaryFg: '#0B1410', accent: '#F0A468', accentSubtle: '#2A1D12',
    bg: '#14130F', surface: '#1D1B16', surfaceRaised: '#26241E',
    border: '#34312A', borderStrong: '#4A463D',
    fg: '#F5F3EE', fgMuted: '#A9A398', fgSubtle: '#7C766B',
    warning: '#E5A44A', warningSubtle: '#2A2113',
    danger: '#F0857D', dangerSubtle: '#2E1715',
  },
};

export const spacing = { 1: 4, 2: 8, 3: 12, 4: 16, 5: 24, 6: 32, 8: 48, 10: 64 };
export const radius  = { sm: 6, md: 10, lg: 16, xl: 24, full: 9999 };
```

Other translations:

| Web | React Native |
|---|---|
| `prefers-color-scheme` | `useColorScheme()` |
| `prefers-reduced-motion` | `AccessibilityInfo.isReduceMotionEnabled()` |
| Focus ring | Less relevant — but `accessibilityLabel` on everything becomes **more** important |
| 44×44px targets | **44pt iOS / 48dp Android** — same rule, use `hitSlop` for small icons |
| `<img alt="">` | `accessibilityLabel` |
| `aria-live` | `AccessibilityInfo.announceForAccessibility()` |

`hitSlop` is the mobile answer to the ingredient-chip remove button — it expands the touch
area without changing the visual size, which is exactly what `04-COMPONENTS.md` asks for.

---

## 6. What native gives you for free

Some things get *easier*, and are worth using:

- **Offline saved recipes** — much simpler natively than a service worker. Cache saved
  recipes locally; they open with no signal. Genuinely valuable in a South African kitchen.
- **No install friction** — the PWA workaround in `12-SOUTH-AFRICA.md` § 7 is unnecessary.
- **Haptics** — a small tap when an ingredient is added feels good. Use sparingly.
- **Native share** — share a recipe to WhatsApp, which matters a lot in this market.
- **Push notifications** — out of scope for v1, but a real v2 lever ("dinner idea?" at 5pm).

**Still true:** data cost. Keep images small and cache aggressively. App download size
matters too — many users are on capped data and will abandon a 60MB download. Target under
30MB if you can.

---

## 7. App store requirements — a new phase

This didn't exist in the web plan. Budget **1–2 weeks** on top of the roadmap in
`10-ROADMAP.md`, mostly waiting.

**Accounts (the client pays, in their name, not yours):**
- Apple Developer Program — **$99/year**
- Google Play Developer — **$25 one-time**

**Both stores require a privacy declaration.** Apple calls it a privacy "nutrition label";
Google calls it the Data Safety form. You must declare that the app collects **health
data** — allergens and health goals qualify. Declare it honestly. This lines up with the
POPIA work in `12-SOUTH-AFRICA.md` § 8, so it's the same story told twice.

**A published privacy policy URL is mandatory** on both stores. It cannot be a placeholder.
This makes the "who writes the legal pages" question in `11-CLIENT-BRIEF.md` genuinely
blocking now — you cannot ship without it.

**Health-adjacent apps get extra scrutiny at review.** Make sure the disclaimers from
`04-COMPONENTS.md` are visible in your screenshots, and that nothing in the store
description claims medical benefit. "Healthy recipes from what you have" is fine.
"Manage your diabetes" is not, and will be rejected.

**Also needed:** app icon, splash screen, screenshots at several device sizes, an age
rating questionnaire, and a support URL.

**Review takes days, and rejection on the first submission is normal.** Do not promise the
lecturer a launch date that assumes first-time approval.

---

## 8. Revised roadmap

Amending `10-ROADMAP.md`:

| Phase | Change |
|---|---|
| 0 — Foundations | Newly does most of this in minutes. Verify RLS yourself. |
| 1 — Auth | Add **Sign in with Apple** or the app gets rejected |
| 2 — Pantry | Unchanged |
| 3 — Engine | **Now an Edge Function.** Highest-risk phase, unchanged in importance |
| 4 — Recipe UI | Unchanged |
| 5 — Saved & profile | Add offline caching for saved recipes |
| 6 — ~~Landing page~~ | **Becomes store listing + screenshots.** Marketing site is separate scope |
| 7 — Polish | Test on a real Android *and* a real iPhone |
| **8 — Store submission** | **NEW: 1–2 weeks.** Accounts, privacy forms, review, resubmission |

Net effect: Newly compresses phases 0–2 dramatically, and adds a phase at the end that no
tool can compress, because it's Apple and Google reading your submission.

---

## 9. Revised Prompt 1 — use this instead of the web version

```
Set up the foundation for a native mobile app called PantryChef before we build
any screens. Do not build screens yet — I only want the theme and standing rules.

WHAT THE APP IS (context only, don't build it yet)
A South African healthy-eating app. Users list ingredients they already have at
home, and the app generates a healthy recipe from them, respecting their
allergies, diet, and health goal.

Create a theme file with these exact values.

Light mode colours:
primary #2F7A4E, primaryHover #276541, primarySubtle #E8F3ED, primaryFg #FFFFFF,
accent #E8833A, accentSubtle #FDF0E5, bg #FBFAF7, surface #FFFFFF,
border #E6E2DA, borderStrong #CFC9BE, fg #1C1B18, fgMuted #6B665C,
fgSubtle #979185, warning #B4690E, warningSubtle #FDF3E3, danger #B3261E,
dangerSubtle #FCEEED

Dark mode colours:
primary #5FBF89, primaryHover #75CD9B, primarySubtle #16251D, primaryFg #0B1410,
accent #F0A468, accentSubtle #2A1D12, bg #14130F, surface #1D1B16,
surfaceRaised #26241E, border #34312A, borderStrong #4A463D, fg #F5F3EE,
fgMuted #A9A398, fgSubtle #7C766B, warning #E5A44A, warningSubtle #2A2113,
danger #F0857D, dangerSubtle #2E1715

Switch between them with useColorScheme().

Typography: a serif for headings, a clean sans for body.
Sizes: display 48, h1 32, h2 24, h3 20, bodyLarge 18, body 16, small 14, xs 12.
Headings weight 600.

Spacing scale: 4, 8, 12, 16, 24, 32, 48, 64. Never values between these.
Border radius: 6 chips, 10 buttons and inputs, 16 cards, 24 modals, 9999 pills.

NAVIGATION
A bottom tab bar with three tabs: Pantry (home), Saved, Profile.
An auth stack for signed-out users, and an onboarding stack for first run.
Recipe detail is pushed on top. Set up the shell now with empty placeholder
screens.

STANDING RULES — apply to everything we build from here
1. Use the theme values above. Never a hardcoded colour in a component.
2. Every tappable element must have a touch target of at least 44pt on iOS and
   48dp on Android. For small icons, use hitSlop to expand the touch area
   without changing how it looks.
3. Everything must work in both light and dark mode.
4. Every interactive element needs an accessibilityLabel.
5. Never use colour alone to convey meaning — pair it with an icon or text.
6. Respect the reduce-motion accessibility setting.
7. Keep the app small. Many of our users are in South Africa on mid-range
   Android phones paying for mobile data. Don't add a heavy library for a small
   job, and keep images optimised.
8. Use South African English and metric units throughout.

Set this up now and show me the theme file and the navigation structure.
Do not build any screens yet.
```

---

## 10. Revised Prompt 6 — generation via Edge Function

Replace the security section of prompt 6 in `14-PROMPT-SEQUENCE.md` with this. Everything
else in that prompt — the system prompt, the allergen check, the caching — stays exactly
as written.

```
Build recipe generation as a Supabase Edge Function. Read this carefully.

SECURITY — this is not optional and I will be checking it
The Anthropic API key must NEVER be inside the mobile app. A React Native app
binary can be downloaded and decompiled, and any string inside it can be
extracted — an .env file bundled into the app is not a secret.

Architecture:
  App -> Supabase Edge Function -> Anthropic API

Store the key as a Supabase secret, not in the app source, not in app config,
not in an .env file that gets bundled.

The Edge Function must:
1. Verify the user's Supabase session and get their user ID from it.
2. Load their allergens, diets, health goal, budget_mode and cooking_constraint
   FROM THE DATABASE using that user ID. Never accept these from the request
   body — a modified app could send an empty allergen list.
3. Call Anthropic with the key held server-side.
4. Run the allergen check described below.
5. Save the recipe and return it to the app.

The app itself must make ZERO requests to api.anthropic.com. If you cannot build
it this way, stop and tell me rather than putting the key in the app.
```

Then continue with the rest of prompt 6 from `14-PROMPT-SEQUENCE.md` unchanged.

---

## 11. Revised Prompt 8 — store listing instead of a landing page

There's no landing page in a mobile app. Prompt 8 becomes app store preparation:

```
Prepare this app for App Store and Google Play submission.

App name: PantryChef
Subtitle: Healthy recipes from what you already have

Description, roughly:
"Open your fridge, not a recipe book. Tell PantryChef what you've got at home and
get a healthy meal you can cook tonight — no shopping trip needed.

Built for South African kitchens. We know mielie meal, morogo, samp and amasi,
and we build recipes around the food you actually eat, not food from somewhere
else.

- Add what's in your kitchen in seconds
- Get recipes that respect your allergies and how you eat
- Cook on a budget with affordable ingredients
- Load shedding mode for when the power's out
- Save the ones you love

PantryChef gives general suggestions, not medical or dietary advice. Nutrition
values are estimates. Always check ingredient labels for allergens yourself."

Also generate: an app icon, a splash screen, and screenshots at the required
device sizes showing the pantry screen, a recipe, and the saved list.

Set up the privacy declarations for both stores. Declare honestly that the app
collects health-related information (allergens and dietary goals) used only to
personalise recipes and not shared with third parties.
```

**Before submitting:** the privacy policy URL must be live and real. Both stores reject
placeholders, and this is the thing most likely to hold up a first submission.

---

## 12. What to tell the lecturer

The platform change is worth raising directly, because it cuts both ways:

> "Newly builds real native iOS and Android apps rather than a website, which is better
> for this product — it works offline, installs properly, and can go on the app stores.
> Two things follow from that. First, we'll need Apple and Google developer accounts in
> your name — $99 a year and $25 once. Second, store review takes a few days and
> first-time rejection is common, so I'd add two weeks at the end rather than promise a
> hard launch date.
>
> One thing I'm handling carefully: the AI key can't live inside a mobile app, because
> app files can be opened up and read. It'll sit on the server side in Supabase instead."

That last paragraph is worth saying out loud. It's the kind of thing that separates a
project that works from a project that works until someone looks at it.

---

**Back to:** [`README.md`](./README.md)
