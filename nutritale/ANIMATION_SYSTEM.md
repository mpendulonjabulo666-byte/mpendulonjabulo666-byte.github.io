# NutriTale — Animation System (2026-11-10)

## Principles
- Only `transform` and `opacity` are animated (smooth on low-end Android).
- CSS first; one small script (`assets/js/nutritale-ui.js`, no libraries).
- Nothing animates off-screen: scroll reveals are unobserved once shown.
- `prefers-reduced-motion` switches every animation off.
- Video only where a cinematic asset genuinely helps (landing hero). Never for UI feedback.
- No database, PHP logic or framework changes.

## Timing tokens
| Use | Duration | Easing |
|---|---|---|
| Press / tap feedback | 150 ms | ease |
| Hover lift, toasts, loader fade | 200–250 ms | ease |
| Scroll reveal | 500 ms, 60 ms stagger (max 5) | cubic-bezier(.2,.7,.2,1) |
| Photo zoom | 600 ms | cubic-bezier(.2,.7,.2,1) |
| Heart pop | 380 ms | cubic-bezier(.3,1.4,.5,1) |

## What is built
| Piece | Where | Status |
|---|---|---|
| Main loader (leaf mark, glass panel) | `NutriUI.showPageLoader()` | live |
| AI loader (three stages shown in turn) | `pantry.php` "Get AI ideas" form | live |
| Button loader | `data-nt-loading` on a form | live on pantry |
| Recipe skeleton cards | `NutriUI.showSkeleton()` | ready, not used yet (pages render on the server, so there is nothing to wait for) |
| Pantry scan beam | CSS on the live barcode camera view | live |
| Success / error / info toasts | `NutriUI.toast()` | live (offline / online notices) |
| Scroll reveal | cards below the fold, app + landing | live |
| Recipe photo zoom + card lift | `.recipe-card` | live |
| Favourite heart pop | `.fav-btn` | live |
| Tab bar active pill glide, page transitions | existing View Transitions code | already in the app |

## Not built (and why)
- "Ingredients identified one by one" scan: the scanner reads barcodes, not photos of food. Showing fake recognition would misrepresent what the app does.
- Cooking-step progress and weekly planner progress: need new saved state, which is a feature, not an animation.
- Cinematic hero video: needs a generated asset (below).

## Cinematic assets — prompts for Higgsfield (or any image/video model)
Not generated: the Higgsfield account has 0.02 credits; one image costs 0.25.

1. **Landing hero still (16:9, WebP, under 200 KB)**
   "Top-down premium food photography on a warm cream stone surface: ripe tomatoes, basil, halved avocado, lemon, garlic, olive oil in a small glass bowl, wooden spoon. Soft natural window light from the left, gentle shadows, shallow depth of field, forest green and cream palette, generous empty space on the left for a headline. Photorealistic, editorial, no text, no hands."
2. **Landing hero loop (5 s, seamless, muted MP4 under 1.5 MB, poster = still above)**
   Image-to-video from asset 1: "Very slow push-in with a slight clockwise drift, steam and light barely moving, ingredients stay perfectly still and keep their shape, no morphing, no flicker, seamless loop."
3. **Pantry banner still (3:1)**
   "Open pantry shelf with glass jars of rice, lentils and spices, fresh herbs in front, warm morning light, shallow depth of field, cream and sage tones, photorealistic, no labels or text."
4. **Empty-state illustration (1:1, transparent)**
   "Single sage-green ceramic bowl with a sprig of basil, soft studio shadow, minimal, photorealistic, transparent background."

Integration when assets exist: `<video autoplay muted loop playsinline preload="none" poster="hero.webp">`, loaded only on screens wider than 700 px and only when reduced motion is off; phones get the still.
