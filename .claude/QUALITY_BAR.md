# Quality bar - nothing ships below this

The reviewer fails any job that breaks a line here. "It works" is not enough.

## Every job
- It does what the brief asked, and the "done when" checks were really run. No invented test output.
- Nothing fake: no placeholder text, lorem ipsum, made-up reviews, prices, hours, numbers, logos or people.
- No dead ends: every button and link does something, every form shows what happened, every error tells the user what to do next.
- Works on a small, older Android phone on a slow connection. Then on a wide screen.
- No secrets in the code. No pushing to a branch that deploys.

## Interfaces (websites, apps, games)
- It looks made for this product. Signs of a template, each a fail unless the brief asked for it: purple-to-blue gradient hero, glowing blobs, glass cards on everything, three identical "feature" cards with an icon on top, emoji used as icons, centred everything, a pill badge above every heading, the same rounded corner and shadow on every element.
- One clear main action per screen. The eye knows where to go first.
- Real content: the product's own screens, photos and words. Stock art only when the brief allows it.
- Type: at most two font families, a clear size scale, body text big enough to read on a phone, lines not too long.
- Colour: a small palette used with purpose. Text passes contrast against its background.
- Spacing is consistent and things line up.
- Motion has a reason (it shows where something came from or what changed), is short, and stops for users who ask for reduced motion. See the 20 principles in KNOWLEDGE.md.
- Tap targets are big enough to hit with a thumb. Inputs do not zoom the page.
- Empty, loading and error states are designed, not left blank.
- Images are sized and compressed. The page is usable before everything has loaded.
- Games: the first ten seconds are playable with no instructions, controls answer at once, and the game runs smoothly on a cheap phone.

## Words (pages, posts, messages, app text)
- Say the specific thing. Fail: "Welcome to...", "Unlock the power of", "seamless", "elevate", "revolutionary", "game-changing", "in today's fast-paced world", "your one-stop shop", "Ready to transform...?".
- Short sentences in the reader's own words. No empty openers, no summary that repeats the heading.
- Every claim is true and can be shown in the product.
- Buttons say what happens: "Order on WhatsApp", not "Get started".
- Spelling matches the audience (South African English for local work).

## Code
- The smallest change that does the job. No unused code, no commented-out code, no comments that restate the line.
- Names say what things are. Errors are handled where they can happen, not hidden.
- Follows the project's existing patterns. No new framework or dependency unless the brief says so.
- There is a test for the new behaviour, and all tests pass.
