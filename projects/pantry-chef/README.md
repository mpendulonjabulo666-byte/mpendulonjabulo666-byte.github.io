# PantryChef — Project Documentation

> **Working codename.** The client owns the real name. Search-and-replace `PantryChef`
> everywhere once they decide. Don't buy a domain or design a logo until that's settled.

**The product in one sentence:** you tell it what food you already have, and it gives you
a healthy recipe you can actually cook with those ingredients right now.

**Market: South Africa.** Not as an afterthought — the ingredient catalogue, the health
goals, the languages, and the performance budget are all built around it. See
[`12-SOUTH-AFRICA.md`](./12-SOUTH-AFRICA.md).

---

## What these files are

This folder is a **specification**, not code. It's the thing you read before you build,
and the thing you hand to Claude (or another developer) so that everything they build
comes out consistent instead of eleven different-looking screens glued together.

Nothing in here is decoration. Every file answers a question that *will* come up during
the build, and answering it now costs an hour, while answering it in week three costs a
rewrite.

## Read them in this order

| # | File | What it answers |
|---|------|-----------------|
| 00 | [`00-START-HERE.md`](./00-START-HERE.md) | What do I actually need to understand before I start? |
| 01 | [`01-PRD.md`](./01-PRD.md) | What are we building, for whom, and what's *not* in v1? |
| 02 | [`02-USER-FLOWS.md`](./02-USER-FLOWS.md) | What does a person do, step by step, screen to screen? |
| 03 | [`03-DESIGN-SYSTEM.md`](./03-DESIGN-SYSTEM.md) | What colours, type, spacing, and motion does everything use? |
| 04 | [`04-COMPONENTS.md`](./04-COMPONENTS.md) | What are the reusable UI pieces and what props do they take? |
| 05 | [`05-PAGES.md`](./05-PAGES.md) | What's on each screen, section by section? |
| 06 | [`06-DATA-MODEL.md`](./06-DATA-MODEL.md) | What tables exist and how do they relate? |
| 07 | [`07-API.md`](./07-API.md) | What endpoints exist, what goes in, what comes out? |
| 08 | [`08-AI-ENGINE.md`](./08-AI-ENGINE.md) | How does the recipe actually get generated, safely and cheaply? |
| 09 | [`09-TECH-STACK.md`](./09-TECH-STACK.md) | What libraries, what folder structure, what env vars? |
| 10 | [`10-ROADMAP.md`](./10-ROADMAP.md) | What gets built in what order, and by when? |
| 11 | [`11-CLIENT-BRIEF.md`](./11-CLIENT-BRIEF.md) | What must I ask the client before writing a line of code? |
| 12 | [`12-SOUTH-AFRICA.md`](./12-SOUTH-AFRICA.md) | **How does all of the above change for South Africa?** |
| 13 | [`13-AI-BUILDER-WORKFLOW.md`](./13-AI-BUILDER-WORKFLOW.md) | Building this in an AI app builder — prompt order, and what to verify by hand |
| 14 | [`14-PROMPT-SEQUENCE.md`](./14-PROMPT-SEQUENCE.md) | **The actual copy-paste prompts, in order, 1 through 8** |
| — | [`CLAUDE.md`](./CLAUDE.md) | Context file to drop into the build repo so Claude follows all of the above. |

> ⚠️ **`12-SOUTH-AFRICA.md` overrides everything else.** Files 01–11 describe the product
> in general terms. File 12 adapts it to the actual market: South African ingredients,
> local-language aliases, budget mode, load-shedding mode, POPIA instead of GDPR, and much
> tighter performance budgets because mobile data costs money here. Where the two
> disagree, file 12 is correct.

**If you only read three:** `00-START-HERE.md` for the shape of the problem,
`12-SOUTH-AFRICA.md` for what makes this product worth building, and
`11-CLIENT-BRIEF.md` so you don't build the wrong thing.

## If you're building in an AI app builder

The client is using **Newly**. Read [`13-AI-BUILDER-WORKFLOW.md`](./13-AI-BUILDER-WORKFLOW.md)
before you start — it has the prompt order (design system first, landing page last) and,
more importantly, the three things to verify by hand before building any feature.

Short version: builders are excellent at the half of this app you can see, and unreliable
at the half you can't. Let it build the screens fast; personally verify the allergen
check, the API key handling, and the database access rules.

## How to use these with Claude Code

Once you create the build repo:

1. Copy `CLAUDE.md` into the root of the new repo. Claude reads it automatically on every
   session, so all the conventions below apply without you re-explaining them.
2. Copy this whole `pantry-chef/` folder into the repo as `docs/`.
3. Start a task by pointing at the relevant spec:
   > "Build the pantry screen. Follow `docs/05-PAGES.md` § Pantry and
   > `docs/03-DESIGN-SYSTEM.md` for tokens. Use existing components from
   > `docs/04-COMPONENTS.md` — don't invent new ones."

That third step is the whole trick. A vague prompt gets you a vague app. A prompt that
points at a written spec gets you the app in the spec.

## Status

| Thing | State |
|-------|-------|
| Specification | Written, awaiting client sign-off |
| Client questions | Unanswered — see `11-CLIENT-BRIEF.md` |
| Name | Placeholder |
| Code | Not started |
| Design mockups | Not started (the design system is the substitute for now) |

**Do not start coding until `11-CLIENT-BRIEF.md` § Blocking questions is answered.** Two
of those answers change the data model.
