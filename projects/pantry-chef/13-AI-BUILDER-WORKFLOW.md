# 13 — Building This With an AI Builder

The client is building on **Newly**, an AI-assisted development tool. This file is about
how to use the rest of these specs inside a prompt-to-app builder rather than a hand-written
codebase.

> **Scope note, honestly stated:** I don't have reliable knowledge of Newly's specific
> feature set, so nothing here claims to describe it. Everything below applies to the
> *category* of AI app builders. Check the tool-specific details — export, database, and
> secret handling — against Newly's own documentation before relying on them.

---

## 1. What these tools are genuinely good at

Real strengths, and they matter:

- **Visual shell.** Landing page, layout, navigation, cards, forms. Fast and often good.
- **CRUD screens.** The pantry list, the saved list, profile settings.
- **Auth flows.** Sign-up, sign-in, password reset — usually wired to a provider in one step.
- **Iteration speed.** "Make the chips bigger, move the button to the bottom" in seconds.
- **Getting to something demonstrable.** For a lecturer who wants to *see* it, this is
  the fastest possible path.

If the goal is a working, good-looking app in front of someone quickly, this is the right
tool and you should use it.

## 2. What they're weak at — and it's the risky half of this project

Look at what this app actually contains:

| Part of the app | Builder handles it? |
|---|---|
| Pantry UI, chips, search | ✅ Well |
| Landing page | ✅ Well |
| Auth screens | ✅ Well |
| Saved recipes list | ✅ Well |
| **Two-layer allergen check** | ⚠️ Will generate *something*. Verify every line. |
| **Row Level Security policies** | ⚠️ Frequently missed entirely |
| **Keeping the API key server-side** | ⚠️ The single biggest risk |
| **Order-independent cache key** | ⚠️ Easy to generate subtly wrong |
| **POPIA deletion cascade** | ⚠️ Will look done without being done |
| **Ingredient alias normalisation** | ⚠️ Needs your seed data, not invented data |

The pattern: builders are excellent at what you can *see*, and unreliable at what you
can't. Everything in the second list is invisible when it's broken. An allergen check that
silently returns `true` looks identical to one that works — right up until it doesn't.

**So: let the builder do the visible half fast, and personally verify the invisible half.**
That's the whole strategy.

---

## 3. The three things to check before anything else

Do these in the first session, before building features. If any one is wrong, nothing else
matters.

### ① Is the Anthropic API key server-side?

Open the app in a browser, view source, and search the JavaScript bundle for `sk-ant`.
Also check the network tab: is there a request going **directly from the browser to
`api.anthropic.com`**?

If either is true, **stop**. The key is public, anyone can use it, and it's billed to the
client. Recipe generation must go: browser → your backend → Anthropic. See `07-API.md`
§ Why generation is server-only.

Builders default to client-side calls because it's simpler. This is the failure to actively
prevent, and it's worth restating in every prompt that touches generation.

### ② Is Row Level Security on?

In the database, check every user-owned table: `profiles`, `pantries`, `pantry_items`,
`saved_recipes`, `generation_events`.

A Supabase table without RLS is readable by **anyone holding the anon key** — and that key
ships inside the browser bundle by design. No RLS means one user can read another user's
pantry, allergens, and saved recipes.

Test it properly: create two accounts, and from account A try to fetch account B's data.
If it comes back, RLS isn't working.

### ③ Can you get the code out?

Find the export or GitHub-sync option and use it on day one, not at handover.

Reasons this matters: `11-CLIENT-BRIEF.md` lists "source code in a repository they own" as
a deliverable; you'll likely need to hand-write the allergen check and the generation
endpoint regardless; and a client locked inside a tool with no export has bought something
they don't own.

---

## 4. How to prompt: slice the spec, don't dump it

The failure mode is pasting a whole spec file and asking for the app. You get a plausible
mush that matches nothing.

**One screen or one feature per prompt.** Point at the specific section. Same principle as
`CLAUDE.md` — a prompt that references a written spec produces the app in the spec.

### Order matters

Build in this sequence. Each step depends on the one before it.

**Step 1 — Design system first, before any screen.** This is the highest-leverage prompt
you'll write. Skip it and every screen looks different, which is the exact problem these
docs exist to prevent.

> Set up the design system for this app before we build any screens.
>
> [paste the CSS variables block from `03-DESIGN-SYSTEM.md` § 2]
>
> Typography: Fraunces for headings, Inter for body. Spacing on a 4px scale. Border radius
> 6/10/16/24px. Support light and dark mode using the tokens above.
>
> Rules for everything we build after this: use these tokens only, never raw hex values.
> All interactive elements must be at least 44×44px. Every screen must work at 375px wide
> and in dark mode.

**Step 2 — Database schema.** Paste the SQL from `06-DATA-MODEL.md` directly if the tool
accepts it. Then explicitly:

> Enable Row Level Security on profiles, pantries, pantry_items, saved_recipes and
> generation_events. Each policy must restrict access to rows belonging to the
> authenticated user. Show me the policies you created.

That last sentence — *show me* — is worth adding to any prompt about something invisible.

**Step 3 — Seed the ingredients.** Do not let the builder invent this list. Give it the
South African catalogue and aliases from `12-SOUTH-AFRICA.md` §§ 3–4. Invented ingredient
data is the fastest way to an app that doesn't recognise "mielie meal".

**Step 4 — Auth and onboarding.** Reference `05-PAGES.md` § onboarding and
`12-SOUTH-AFRICA.md` § 8 for the POPIA consent checkbox.

**Step 5 — Pantry screen.** The core screen; give it a full prompt of its own from
`05-PAGES.md` § `/pantry` and `04-COMPONENTS.md`.

**Step 6 — Generation.** See § 5 below. Treat this one differently.

**Step 7 — Recipe detail, saved, profile.**

**Step 8 — Landing page.** Last, deliberately. It's the most fun and the least important
to get right early, and doing it first burns time you'll need elsewhere.

---

## 5. The generation feature — handle this one differently

This is where I'd stop trusting the builder and check every line myself.

Prompt it explicitly with the constraints, rather than describing the feature and hoping:

> Build the recipe generation endpoint. Critical requirements:
>
> 1. This runs on the **server only**. The Anthropic API key must never appear in
>    client-side code or in any browser request. The browser calls our own endpoint; our
>    server calls Anthropic.
> 2. Read the user's allergens, diets, health goal, budget_mode and cooking_constraint
>    **from the database using the session** — never from the request body.
> 3. Model is `claude-opus-5` with `max_tokens: 8000`.
> 4. Use structured output with a strict JSON schema — do not parse prose.
> 5. Check `stop_reason` before reading the response content.
> 6. After generating, run a **separate code-level check** that every ingredient in the
>    recipe is free of the user's allergens. If it fails, discard the recipe and
>    regenerate. Maximum 3 attempts, then return an error. The user must never see a
>    recipe that failed this check.
>
> Show me the allergen check function when you're done.

Then read the allergen check yourself, line by line, and test it manually:

1. Set an account's allergens to `peanuts`.
2. Put peanut butter, bread, and bananas in the pantry.
3. Generate ten times.
4. **Zero recipes may contain peanuts.**

Then repeat for milk, and for wheat. If you do one piece of manual testing on this entire
project, do this.

If the builder can't produce a version you trust, hand-write that function. It's about
forty lines, it's specified in `08-AI-ENGINE.md` § 4, and it's the one place in the app
where a bug hurts a person rather than annoying them.

---

## 6. Where the builder will drift from the spec

Watch for these. All of them are quiet.

| Drift | Why it happens | Catch it by |
|---|---|---|
| Generic ingredients (kale, quinoa) | Trained on international recipe data | Searching the seed data for `mielie` |
| Disclaimers dropped | They look like clutter to a layout optimiser | Checking every nutrition panel |
| Touch targets under 44px | Small chips look neater | Testing on an actual phone |
| Dark mode broken on later screens | Only the early screens got tested | Toggling on every screen |
| Bundle bloat | Adds a chart library for one bar | Checking bundle size against `12-SOUTH-AFRICA.md` § 7 |
| No empty states | Only the happy path was described | Emptying the pantry and looking |
| Allergen check reduced to a prompt instruction | It's simpler, and it looks fine | Reading the code |

The last one is the dangerous one. If you ask for "an app that respects allergies", you
will very likely get a single line in a prompt and nothing else. **Two layers is a thing
you have to ask for by name, then verify.**

---

## 7. When to leave the builder

Not "if" — plan for it. Use the builder for velocity, then take the code out for the
parts that need care.

Move to hand-written code when:

- The allergen check needs to be right (immediately)
- The generation endpoint needs correct secret handling
- RLS policies need to be verified and tested
- Bundle size needs to come down to the `12-SOUTH-AFRICA.md` § 7 targets
- POPIA deletion needs to genuinely cascade
- You need real tests around any of the above

The rest — screens, styling, layout, copy — can happily stay builder-generated and
iterated. That's a legitimate, efficient way to work. It's only a problem when the
invisible half never gets a second look.

---

## 8. Revised definition of done

Everything in `CLAUDE.md` § Definition of done, plus:

- [ ] No `sk-ant` string anywhere in the browser bundle
- [ ] No browser request to `api.anthropic.com` in the network tab
- [ ] RLS verified by trying to read another account's data and failing
- [ ] Allergen check tested manually with peanuts, milk, and wheat — 10 generations each,
      zero violations
- [ ] Cache key verified order-independent (same ingredients added in a different order
      produce the same key)
- [ ] Account deletion verified to remove every row across every table
- [ ] Ingredient search returns results for: mielie meal, morogo, amasi, samp, pilchards,
      brinjal
- [ ] Disclaimers present on every screen showing nutrition or allergens
- [ ] Tested on a real entry-level Android phone over mobile data
- [ ] Code exported to a repository the client owns

---

## 9. What to tell the lecturer

Worth being straightforward about the method, because it's a strength rather than
something to hide:

> "I'm using an AI development tool to build the interface quickly, which means you'll see
> working screens much sooner. The parts that carry real risk — the allergen safety
> system, the API key handling, the database access rules, and POPIA compliance — I'm
> writing and verifying myself, because those are the parts where a tool that produces
> something plausible isn't good enough."

That sentence demonstrates exactly the judgement an academic assessor is looking for:
knowing which parts of a system can be automated and which parts require you to be
accountable for them.

---

**Back to:** [`README.md`](./README.md)
