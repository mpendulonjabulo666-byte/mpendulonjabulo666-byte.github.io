# Posters

Two A4 posters (210 × 297 mm), each supplied three ways.

| | Print | Social | Editable source |
| --- | --- | --- | --- |
| Cash loans | `cash-loans-poster.pdf` | `cash-loans-poster.png` | `cash-loans-poster.html` |
| Florescence | `florescence-poster.pdf` | `florescence-poster.png` | `florescence-poster.html` |

The PNGs are 2479 × 3508 px at 300 dpi, rasterised from the PDFs, so print and
social carry the same artwork. The HTML files are self-contained — fonts and
artwork are embedded, so they open and print correctly offline.

## Filling in the placeholders

Open the HTML in a browser and search for the text below. Placeholders are shown
in the accent colour with a dashed underline, so they are easy to spot on the
rendered page.

**Cash loans**

- `0XX XXX XXXX` — done, now 060 930 2017
- `Your name here` — name or trading name
- `Your area here` — the area she lends in
- `NCRCP XXXX` — National Credit Regulator registration number

**Florescence**

- `Your name here` and `Your area here`
- `R___ per bottle` — the starting price
- Check the sizes listed under "Sizes"; they are a common range, not her actual
  stock list.

## Re-exporting after an edit

Open the edited HTML in Chrome, press Ctrl+P, and print to PDF with margins set
to None and background graphics turned on. Export that PDF at 300 dpi for the
PNG.

## Notes on the Florescence artwork

The Arabic line under the wordmark reads عطور عربية, "Arabic perfumes".

The background tile is an eight-point star drawn in SVG. The four photographs
are embedded in the HTML at 300 dpi, so the file prints correctly on its own.

### Photo sources

All four are from Unsplash, whose licence permits commercial use without
attribution or permission. Sources are recorded here anyway.

| Where | Source |
| --- | --- |
| Hero, attar bottle in sunlight | `images.unsplash.com/photo-1738414808975-201966230c59` |
| Tile 1, attar roll-on | `images.unsplash.com/photo-1646149757906-e6e9e9a7c77f` |
| Tile 2, incense smoke | `images.unsplash.com/photo-1770217535698-dd125e5d36c5` |
| Tile 3, rose petals | `images.unsplash.com/photo-1585768750637-ada36319a484` |

Photographs showing another perfume house's branded product were deliberately
excluded — a poster for this shop should not advertise someone else's bottle.
For the same reason, photographs of the actual stock would be better than any
of these, and can be swapped in by replacing the `src` of the relevant `<img>`.

An earlier version of this poster used drawn SVG bottles instead of
photographs. It is in the git history if it is ever wanted back.
