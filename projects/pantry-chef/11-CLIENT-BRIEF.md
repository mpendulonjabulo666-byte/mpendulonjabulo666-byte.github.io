# 11 — Client Brief

This file is for **you**, not the client. It's what you need to find out, agree, and write
down before you start building — plus the conversations that are awkward to have later and
cheap to have now.

---

## Blocking questions

**Do not write code until these are answered.** Each one changes something structural.

### 1. Who owns a pantry — a person, or a household?
If two people share a kitchen and want a shared list, `pantries.user_id` becomes
`owner_id` plus a `pantry_members` join table, and every RLS policy changes. Cheap now,
expensive in week five.
**Recommendation:** one pantry per user in v1. Households are a v2 feature.

### 2. Do we keep every generated recipe, or only saved ones?
Keeping everything makes the cache better and costs storage. Deleting unsaved recipes
after 30 days is cheaper and simpler. This also touches the privacy policy.
**Recommendation:** keep everything for now — the cache benefit is real and storage is
cheap at this scale. Revisit at 100k recipes.

### 3. Web app, or native iOS/Android?
The spec is a responsive web app, installable to the home screen. If the client is
picturing App Store listings, that's a fundamentally different project — different
timeline, different skills, app store review, two more platforms to maintain.
**Ask directly. Do not assume.** This is the single most common source of a blown-up
freelance project.

### 4. Who pays for the AI usage, and what happens at scale?
Roughly $0.07 per uncached recipe (see `08-AI-ENGINE.md` § 7). At 10,000 generations a
month that's around $490. Is that the client's account and card, or yours? If the app is
free, who absorbs that?
**Recommendation:** the API key lives on the client's own Anthropic account, billed to
them, from day one. Never run a client's production AI costs through your personal
account.

---

## Important but not blocking

5. **What's it actually called?** Everything currently says `PantryChef`. Domain?
   Logo? Brand colours to work from, or is the design system's palette fine?
6. **Who writes the legal pages?** You should not write their privacy policy or terms.
   Say so plainly and offer to wire up whatever they provide.
7. **Where are the users?** Region affects the ingredient catalogue (aubergine vs
   eggplant, coriander vs cilantro), units (metric vs imperial default), and whether GDPR
   applies.
8. **Nutrition accuracy expectations.** They must understand these are AI estimates. Get
   an explicit acknowledgement, in writing, that this is not a medical or dietary tool.
9. **Recipe photos.** Real photography sells the landing page more than anything else on
   it. Do they have images, will they license stock, or do we use gradient placeholders?
10. **Monetisation plans.** Not needed for v1 but it shapes the data model if a paid tier
    is coming within six months.
11. **Who maintains it after launch?** Hand over, or ongoing retainer? Agree before you
    finish, not after.
12. **Analytics and privacy.** Cookie banner or not? Plausible avoids one; Google
    Analytics doesn't.

---

## The scope conversation

Print `01-PRD.md` § 6 (out of scope) and walk through it together. Get an explicit "yes,
that's v1" before you begin.

The sentence that saves projects:

> "Everything on this list is a great idea and none of it is in v1. When we finish v1
> and it's live, we'll look at this list together and pick what's next."

That's not a refusal, it's a sequence. Clients accept a sequence far more readily than a
"no".

Then, in the contract: **any addition to scope is a change request with its own estimate
and its own price.** Written down before work starts, this is a normal business process.
Raised for the first time in week six, it's a confrontation.

---

## The timeline conversation

The roadmap says ~8–9 weeks part-time. If the client expected three weeks, address it
immediately and concretely — not with "it's complicated", but with the phase table from
`10-ROADMAP.md`. Six lines of "here's what happens in each week" does more than any
amount of explanation.

Two things worth stating plainly:

- **The AI part is the fast part.** The recipe generation is roughly a week. The pantry,
  auth, database, safety checks, and the landing page are the other seven.
- **Ingredient normalisation is the hidden cost.** They'll assume the app "just knows"
  that tomatos means tomato. Show them `06-DATA-MODEL.md` § aliases.

Then quote with a buffer. You will lose time to something.

---

## The safety conversation

Have this one deliberately. It protects the client and it protects you.

> "If someone with a peanut allergy uses this app and it gives them a recipe with peanuts,
> that's serious. So I've built two independent layers of protection: the AI is instructed
> to exclude their allergens, and separately the app checks every generated recipe against
> their allergen list in code and throws away anything that fails. On top of that, every
> recipe shows a disclaimer telling people to check labels themselves.
>
> I want to be clear that no automated system is perfect, which is why the disclaimer is
> there and why it stays visible. If that's not acceptable, we should talk about it now."

Get their agreement to the disclaimers in writing. If they ask you to remove them —
because they "look untrustworthy" — decline, explain why once, and put the exchange in
email.

---

## Deliverables checklist

What "done" means, agreed before you start:

- [ ] Deployed web application on their domain
- [ ] Source code in a repository they own
- [ ] Supabase project transferred to their account
- [ ] Anthropic API key on their account, configured
- [ ] Environment variables documented
- [ ] This `docs/` folder, kept current
- [ ] A README that explains how to run it locally
- [ ] One walkthrough session, recorded
- [ ] Agreed support window (e.g. 30 days of bug fixes included)

That last line matters. Without it, "bug fix" and "new feature" blur together and you work
for free indefinitely.

---

## Pricing notes

Not advice on what to charge — that depends on your market and experience. But structure
the deal so you aren't carrying risk:

- **Milestone payments**, tied to `10-ROADMAP.md` M1–M6. Never one payment at the end.
- **Deposit up front**, typically 25–33%. A client unwilling to pay a deposit is telling
  you something.
- **Third-party costs are theirs**, billed to their accounts directly: Anthropic, Supabase
  above the free tier, Vercel above the free tier, domain, stock photography.
- **Change requests are quoted separately.**
- **Define the support window.**

---

## Red flags

If several of these appear, reconsider the engagement:

- Won't answer the blocking questions but wants a start date.
- "Just make it like [huge funded app], but simpler."
- Wants the AI costs on your account.
- Pushes back on the safety disclaimers.
- Wants native apps but budgeted for a website.
- No deposit.
- Keeps adding to scope during the quote conversation.
- Won't put anything in writing.

None of these is automatically disqualifying. All of them are worth a direct conversation
before you commit weeks of your life.

---

## Kickoff agenda (60 minutes)

1. **(10 min)** Walk through `00-START-HERE.md` § 1 — the three problems. Sets realistic
   expectations about where the work actually is.
2. **(15 min)** The four blocking questions.
3. **(10 min)** Scope: read `01-PRD.md` § 5 and § 6 together.
4. **(10 min)** Timeline and milestones from `10-ROADMAP.md`.
5. **(10 min)** The safety conversation.
6. **(5 min)** Costs, accounts, and who pays for what.

Send `01-PRD.md` and `10-ROADMAP.md` beforehand. A client who has read the scope document
asks much better questions.

---

**Back to:** [`README.md`](./README.md)
