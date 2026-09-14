# NutriTale — Where things stand & how to keep going

Read this first when you open the project in VS Code. It's your personal
recap of what's set up and how to carry on building.

## What NutriTale is

A recipe & meal-planning web app (PHP + MySQL): browse/search recipes,
a pantry-based "what can I make?" matcher (with an optional AI-powered
version via Google Gemini), a weekly meal planner with shopping-list
export, nutrition goal tracking, premium recipes + a subscription tier,
an ingredient marketplace for vendors, and an admin panel.

## Your local setup (already done)

- **XAMPP** installed at `C:\xampp`, Apache + MySQL running from the
  XAMPP Control Panel.
- **This project's code** lives at:
  ```
  C:\xampp\htdocs\mpendulonjabulo666-byte.github.io\nutritale
  ```
  That whole `mpendulonjabulo666-byte.github.io` folder is a real git
  clone of your GitHub repo, checked out on branch
  `claude/app-continuation-s4hyzj` — so it's connected to GitHub, not a
  disconnected zip copy.
- **Database**: a MySQL database called `nutritale`, created by
  `install.php`, already has your account and seeded starter recipes.
- **App URL** (only works on this PC, since it's `localhost`):
  ```
  http://localhost/mpendulonjabulo666-byte.github.io/nutritale/index.php
  ```

## Every time you sit down to work

1. Open **XAMPP Control Panel** → click **Start** on Apache and MySQL
   (skip if already green/running).
2. Open **VS Code** → **File → Open Folder** →
   `C:\xampp\htdocs\mpendulonjabulo666-byte.github.io`.
3. Open a terminal inside VS Code (`` Ctrl+` ``) and pull any changes
   made elsewhere:
   ```
   git pull origin claude/app-continuation-s4hyzj
   ```
4. Visit the app URL above in your browser to check it's running.

## Continuing with Claude Code inside VS Code

Claude Code is the same assistant you've been talking to here, but
running as a CLI/extension on your own PC, inside VS Code, working
directly on these files.

1. Install **Node.js** if you don't have it: https://nodejs.org (LTS
   version, default install options).
2. In VS Code's terminal (`` Ctrl+` ``), run:
   ```
   npm install -g @anthropic-ai/claude-code
   ```
3. Still in that terminal, with the folder open at the repo root
   (`C:\xampp\htdocs\mpendulonjabulo666-byte.github.io`), run:
   ```
   claude
   ```
4. It'll prompt you to log in (opens a browser) — sign in with your
   Claude account once.
5. You're in. Just describe what you want changed or added, same as
   this chat — it can read/edit files, run commands, and commit/push to
   GitHub for you.

There's also an official **Claude Code VS Code extension** you can
install from the Extensions panel (search "Claude Code") if you'd
rather have it integrated into the sidebar instead of the terminal.

## A few things worth remembering

- **Testing on your phone**: `localhost` only means "this PC." To test
  from a phone on the same WiFi, you'd need your PC's local network IP
  (run `ipconfig` in Command Prompt, look for "IPv4 Address") and allow
  it through Windows Firewall. To have it reachable from *any* phone
  anywhere (not just home WiFi), it needs real hosting — see
  `DEPLOYMENT.md` in this folder for that (e.g. Railway is set up as an
  option already, via the `Dockerfile`).
- **Don't edit files through the GitHub website** while also editing
  locally — always `git pull` before you start and `git push` when
  you're done, so the two copies don't drift apart.
- **AI pantry suggestions** (`GEMINI_API_KEY` in `config/config.php`)
  are optional and currently blank — the free rule-based recipe matcher
  works without it.

## Starting something new?

If by "new project" you meant a *different* app entirely (not more
NutriTale features), just tell me directly what you have in mind and
I'll help you set that up separately — it doesn't need to touch this
folder at all.
