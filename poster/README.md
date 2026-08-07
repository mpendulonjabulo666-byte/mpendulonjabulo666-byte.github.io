# Posters

Two A4 posters (210 × 297 mm), each supplied three ways.

| | Print | Social | Editable source |
| --- | --- | --- | --- |
| Cash loans | `cash-loans-poster.pdf` | `cash-loans-poster.png` | `cash-loans-poster.html` |
| Flore Scents | `florescence-poster.pdf` | `florescence-poster.png` | `florescence-poster.html` |

(The Flore Scents files keep the `florescence-*` name from an earlier draft of
the shop name — only the wordmark printed on the poster changed.)

The PNGs are 2479 × 3508 px at 300 dpi, rasterised from the PDFs, so print and
social carry the same artwork. The HTML files are self-contained — fonts and
artwork are embedded, so they open and print correctly offline.

## Filling in the placeholders

Open the HTML in a browser and search for the text below. Placeholders are shown
in the accent colour with a dashed underline, so they are easy to spot on the
rendered page.

**Cash loans**

- `NCRCP XXXX` — National Credit Regulator registration number

Name and area placeholders were removed in the photo-led redesign — this
poster now carries only the WhatsApp number, same as the Flore Scents one.

**Flore Scents**

Nothing left to fill in. By request, this poster carries only the WhatsApp
number — no name, no location, no unverified claims (there was a "smell before
you buy" tester line on an earlier draft; it was dropped because that hasn't
been confirmed as an actual policy).

## Re-exporting after an edit

Open the edited HTML in Chrome, press Ctrl+P, and print to PDF with margins set
to None and background graphics turned on. Export that PDF at 300 dpi for the
PNG.

## Notes on the Cash loans artwork

Redesigned around real photography, the same way the Flore Scents poster was:
a handshake hero (trust, warm light, unbranded) and a tile of genuine South
African Rand notes, both under the same green-tinted grade overlay
(`mix-blend-mode: multiply` on `.hero-wrap`/`.photo`) so the two photos read
as one shelf. Palette is two-tone by design — near-black ink and one refined
money-green (`#2E6B45`) — deliberately fewer colours than Flore Scents, to
keep a financial poster reading calm rather than decorative. Lora (serif,
italic) is used once, for a short trust statement, paired with Archivo for
everything functional (headline, table, numbers).

The **"What to bring" list (ID, payslip, bank statements) was removed and the
"How it works" step that asked for those documents was rewritten** — she
lends on trust rather than requiring paperwork, so listing document
requirements was no longer accurate. In its place is a two-step process and a
short quote explaining why there's no checklist. The **NCR fine print at the
bottom still mentions "affordability assessment and approval"** — that's a
regulatory disclosure, not a paperwork checklist, and I left it as-is since
it's a legal requirement rather than a design choice. Worth having her (or
whoever holds the NCR registration) confirm that line still applies under a
trust-based process before this goes to print.

### Photo sources

Both from Unsplash, free licence, no attribution required.

| Where | Source |
| --- | --- |
| Hero, handshake against a brick wall | `images.unsplash.com/photo-1745847768380-2caeadbb3b71` |
| Tile, South African Rand notes (R50/R100) | `images.unsplash.com/photo-1579940906447-34fd001e4289` |

One candidate hero — a handshake photo — was rejected after a full-resolution
check showed a gun on the table beneath the hands (a common "shady deal"
stock-photo trope). Worth remembering: always check stock photography at full
size before use, not just the thumbnail.

## Notes on the Flore Scents artwork

The palette follows a named design direction, **"Gold Hour"** — espresso
ground (`#15100D`), one antique gold (`#B4884A`), one ember accent
(`#9A4326`), warm cream text (`#EFE6D8`). The mood board that direction comes
from is a separate Claude-published page, not part of this repo.

Every photo (hero and all three tiles) carries the same soft warm-gold
gradient overlay (`mix-blend-mode: overlay` on a `.photo`/`.hero-wrap`
wrapper). That is what keeps four unrelated stock photos — one of them shot on
a neutral grey background — reading as one consistent shelf instead of four
random images.

The Arabic line under the wordmark reads عطور عربية, "Arabic perfumes".

The background tile is an eight-point star drawn in SVG. The four photographs
are embedded in the HTML at 300 dpi, so the file prints correctly on its own.
All four show unbranded, full-size (100ml-class) bottles or an ingredient
shot — matching the shop's actual 100–120 ml bottles, not sample-size vials.

### Photo sources

All four are from Unsplash, whose licence permits commercial use without
attribution or permission. Sources are recorded here anyway.

| Where | Source |
| --- | --- |
| Hero, two bottles with black caps on marble | `images.unsplash.com/photo-1594125311687-3b1b3eafa9f4` |
| Tile 1, Oud & Amber — amber-filled bottle | `images.unsplash.com/photo-1638295916768-459f6cf440bc` |
| Tile 2, Rose & Floral — rose petals | `images.unsplash.com/photo-1585768750637-ada36319a484` |
| Tile 3, Musk & Fresh — clear bottle, silver cap | `images.unsplash.com/photo-1720423514789-15a33e59fc81` |

Photographs showing another perfume house's branded product were deliberately
excluded — a poster for this shop should not advertise someone else's bottle.
For the same reason, photographs of the actual stock would be better than any
of these, and can be swapped in by replacing the `src` of the relevant `<img>`.
The four scent/note names (Oud & Amber, Rose & Floral, Musk & Fresh) and their
one-line descriptions were written generically to match the photos — not
confirmed against her actual scent list, and worth checking before printing.

An earlier version of this poster used drawn SVG bottles, then small attar
vials; both are in the git history if ever wanted back.
