# NutriTale repo — how to work here

This repo holds the NutriTale web app in `nutritale/` (PHP 8, MySQL, no framework).
Read `nutritale/CURRENT_ARCHITECTURE.md`, `nutritale/AI_ARCHITECTURE.md` and `nutritale/TASKS.md` before any change.

## Hard rules (every agent, every job)

1. **A push to `claude/app-continuation-s4hyzj` deploys to the live site by FTP.** Never push to that branch. Work on `claude/agent-work`. Only the owner merges into the deploy branch.
2. Never read, print, edit or commit `nutritale/config/config.local.php`, API keys, passwords or `.github/workflows/`.
3. One job = one feature = one commit. No URL change and no visual change unless the job says so.
4. Every job adds or extends a test in `nutritale/tests/`. Run tests with `cd nutritale && php tests/<name>_test.php`. A job is not done while any test fails.
5. Allergen safety is checked by code (`includes/allergens.php`), never by a model alone.
6. No mood, weight, symptom, cycle or intake logging until the owner has approved the consent wording (`AI_ARCHITECTURE.md` rule 6).
7. Nothing fake: no invented recipes, prices, hours, stores or test results. If data is missing, stop and report.
8. If a job is unclear or blocked, stop and report to the orchestrator. Do not guess.

## Team setup (planner + workers)

The main session is the **orchestrator**. It plans, hands out one job at a time, and checks every result. It does not write feature code itself.

| Agent | Model | Job |
|---|---|---|
| `teacher` | sonnet | Turns the owner's rough request into clear jobs and briefs. Asks when a fact is missing. |
| `nt-researcher` | haiku | Read-only. Finds where the logic lives and reports files and line numbers. |
| `nt-builder` | haiku | Makes the change for one job and commits it. |
| `nt-designer` | sonnet | Makes the change for jobs marked UI (layout, CSS, motion, phone feel), only when the job allows a visual change. Commits it. |
| `marketer` | sonnet | Writes the marketing for jobs marked marketing. Drafts only; the owner sends. |
| `nt-tester` | haiku | Writes or extends the test, runs all tests, reports the raw output. |
| `nt-reviewer` | opus | Read-only. Checks the diff against the job and the hard rules. Says PASS or FAIL. |

## The loop for each job in `.claude/AGENT_JOBS.md`

0. When the owner asks for something new, `teacher` writes the jobs and briefs first.
1. Orchestrator picks the next job whose status is `todo` and that is not `blocked` or `owner`.
2. `nt-researcher` reports the current code for that job.
3. Orchestrator writes a short brief: goal, files, done-when, what not to touch.
4. `nt-builder` makes the change, or `nt-designer` if the job is marked UI.
5. `nt-tester` adds the test and runs every test.
6. `nt-reviewer` reviews the diff. FAIL sends it back to step 4 with the reasons (max 3 rounds, then stop and report to the owner).
7. Orchestrator re-runs the tests itself, sets the job to `done` in `.claude/AGENT_JOBS.md`, and ticks the matching line in `nutritale/TASKS.md`.

Every agent reads `.claude/KNOWLEDGE.md` and `.claude/QUALITY_BAR.md`. The reviewer fails any job that breaks the quality bar.

Jobs that touch different files may run in parallel. Jobs that touch the same file run one after the other.
