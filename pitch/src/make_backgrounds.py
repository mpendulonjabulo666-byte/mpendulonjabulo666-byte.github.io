"""Generate organic warm background images for the Cortex Synapse deck.

Soft radial blobs over a base tone plus fine paper grain -> an 'organic'
texture that never competes with the text sitting on top of it.
"""
from PIL import Image, ImageDraw, ImageFilter

W, H = 2666, 1500  # 16:9 at ~200 dpi for a 13.33 x 7.5in slide

ESPRESSO = (42, 19, 15)
MAROON = (86, 33, 25)
CREAM = (250, 242, 226)
SAND = (240, 226, 199)
AMBER = (224, 138, 46)
TERRA = (192, 86, 33)
SAGE = (150, 168, 128)


def compose(base, blobs, grain=0.045, vignette=0.0):
    """blobs: (cx, cy, rx, ry, colour, alpha) in fractions of W/H."""
    img = Image.new("RGB", (W, H), base).convert("RGBA")
    for cx, cy, rx, ry, colour, alpha in blobs:
        layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        d = ImageDraw.Draw(layer)
        d.ellipse(
            [W * (cx - rx), H * (cy - ry), W * (cx + rx), H * (cy + ry)],
            fill=colour + (alpha,),
        )
        layer = layer.filter(ImageFilter.GaussianBlur(W * 0.11))
        img = Image.alpha_composite(img, layer)
    img = img.convert("RGB")

    if vignette:
        mask = Image.new("L", (W, H), 0)
        ImageDraw.Draw(mask).ellipse(
            [-W * 0.18, -H * 0.30, W * 1.18, H * 1.30], fill=255
        )
        mask = mask.filter(ImageFilter.GaussianBlur(W * 0.11))
        darkened = Image.blend(img, Image.new("RGB", (W, H), (0, 0, 0)), vignette)
        img = Image.composite(img, darkened, mask)

    # fine grain so the flat fills read as paper rather than a gradient mesh
    noise = Image.effect_noise((W, H), 10).convert("L").filter(ImageFilter.GaussianBlur(0.4))
    return Image.blend(img, Image.merge("RGB", (noise, noise, noise)), grain)


def save(img, name):
    img.save(f"assets/{name}", quality=92, optimize=True)
    print("wrote", name)


# --- Dark espresso: cover, product proof, team/close -------------------------
save(
    compose(
        ESPRESSO,
        [
            (0.10, 0.16, 0.62, 0.85, MAROON, 210),
            (0.86, 0.80, 0.55, 0.75, TERRA, 90),
            (0.30, 0.98, 0.50, 0.45, AMBER, 46),
            (0.97, 0.06, 0.34, 0.40, AMBER, 40),
        ],
        vignette=0.42,
    ),
    "bg-dark.jpg",
)

# --- Dark, hotter: the problem slide ----------------------------------------
save(
    compose(
        (34, 15, 12),
        [
            (0.18, 0.36, 0.68, 0.92, MAROON, 200),
            (0.02, 0.10, 0.42, 0.52, TERRA, 120),
            (0.92, 0.92, 0.50, 0.58, (150, 55, 34), 110),
            (0.60, 0.02, 0.36, 0.30, AMBER, 34),
        ],
        vignette=0.48,
    ),
    "bg-problem.jpg",
)

# --- Cream organic: content slides ------------------------------------------
save(
    compose(
        CREAM,
        [
            (0.96, 0.06, 0.50, 0.62, SAND, 95),
            (0.00, 0.98, 0.48, 0.56, SAND, 80),
            (0.78, 0.98, 0.36, 0.36, AMBER, 26),
            (0.08, 0.02, 0.32, 0.32, TERRA, 16),
        ],
        grain=0.038,
    ),
    "bg-cream.jpg",
)

# --- Cream with a warmer amber wash: market / revenue -----------------------
save(
    compose(
        (252, 245, 232),
        [
            (0.02, 0.06, 0.50, 0.60, SAND, 100),
            (0.98, 0.94, 0.50, 0.60, SAND, 88),
            (0.24, 1.00, 0.38, 0.34, AMBER, 30),
            (0.92, 0.02, 0.30, 0.28, TERRA, 18),
        ],
        grain=0.038,
    ),
    "bg-cream-warm.jpg",
)

# --- Cream with a sage lean: impact slide -----------------------------------
save(
    compose(
        (249, 246, 235),
        [
            (0.96, 0.06, 0.50, 0.62, SAGE, 52),
            (0.00, 0.98, 0.48, 0.56, SAND, 88),
            (0.76, 1.00, 0.34, 0.32, SAGE, 40),
        ],
        grain=0.038,
    ),
    "bg-cream-sage.jpg",
)
