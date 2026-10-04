<?php
// Regenerates the NutriTale app icons and favicons from one geometry
// definition, so the mark never drifts between sizes. Run from the app root:
//     php scripts/make_logo.php
//
// The mark is an open book with a leaf rising from the spine - the same idea
// as the old artwork, redrawn as flat geometry that still reads at 32px, with
// no wordmark baked in (the name is HTML text beside it, see
// brand_wordmark_html()) and no white box behind it.
//
// Everything is drawn at 4x and resampled down, which is what gives the
// curves clean edges - GD has no polygon antialiasing of its own.

const SS = 4; // supersample factor

// --- geometry, in a 64x64 space -------------------------------------------
// Cubic bezier flattened to points, so filled polygons can take curves.
function bez(array $p0, array $p1, array $p2, array $p3, int $steps = 24): array
{
    $out = [];
    for ($i = 0; $i <= $steps; $i++) {
        $t = $i / $steps;
        $u = 1 - $t;
        $out[] = [
            $u * $u * $u * $p0[0] + 3 * $u * $u * $t * $p1[0] + 3 * $u * $t * $t * $p2[0] + $t * $t * $t * $p3[0],
            $u * $u * $u * $p0[1] + 3 * $u * $u * $t * $p1[1] + 3 * $u * $t * $t * $p2[1] + $t * $t * $t * $p3[1],
        ];
    }
    return $out;
}

function leaf_points(): array
{
    // One pointed leaf, tip up, centred on x=32. Its base runs past the top
    // of the pages so leaf, stem and book read as a single silhouette rather
    // than three shapes that dissolve into blobs at favicon size.
    return array_merge(
        bez([32, 8], [41, 16], [42, 27], [32, 38]),
        bez([32, 38], [22, 27], [23, 16], [32, 8])
    );
}

function page_points(bool $left): array
{
    // An open page: fans out from the spine, top edge dipping, bottom edge
    // curving back up, so the pair reads as a book rather than two slabs.
    // The inner edge sits close to centre - a wide gap splits the book in
    // two at small sizes.
    $pts = array_merge(
        bez([31, 34], [23, 29], [13, 30], [4, 35]),   // top edge, spine -> outer
        bez([4, 35], [4, 43], [4, 48], [4, 52]),      // outer edge down
        bez([4, 52], [14, 47], [23, 46], [31, 51])    // bottom edge, outer -> spine
    );
    $pts[] = [31, 34];
    if (!$left) {
        foreach ($pts as $i => $p) {
            $pts[$i] = [64 - $p[0], $p[1]];
        }
    }
    return $pts;
}

function flat(array $pts, float $scale, float $dx = 0, float $dy = 0): array
{
    $out = [];
    foreach ($pts as $p) {
        $out[] = (int)round($p[0] * $scale + $dx);
        $out[] = (int)round($p[1] * $scale + $dy);
    }
    return $out;
}

// --- drawing ---------------------------------------------------------------
function draw_mark($im, float $scale, float $dx, float $dy, int $ink, int $accent): void
{
    imagefilledpolygon($im, flat(page_points(true), $scale, $dx, $dy), $ink);
    imagefilledpolygon($im, flat(page_points(false), $scale, $dx, $dy), $ink);
    imagefilledpolygon($im, flat(leaf_points(), $scale, $dx, $dy), $accent);
    // Midrib and stem are one continuous ink line from inside the leaf down
    // through the spine. Drawn as a single bar, and deliberately heavy: a
    // hairline here is the first thing to disappear at 32px.
    $w = max(2, (int)round(2.6 * $scale));
    imagefilledrectangle(
        $im,
        (int)round(32 * $scale + $dx - $w / 2), (int)round(15 * $scale + $dy),
        (int)round(32 * $scale + $dx + $w / 2), (int)round(50 * $scale + $dy),
        $ink
    );
}

function rounded_rect($im, int $w, int $r, int $colour): void
{
    imagefilledrectangle($im, $r, 0, $w - $r, $w, $colour);
    imagefilledrectangle($im, 0, $r, $w, $w - $r, $colour);
    foreach ([[$r, $r], [$w - $r, $r], [$r, $w - $r], [$w - $r, $w - $r]] as [$cx, $cy]) {
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $colour);
    }
}

function new_canvas(int $size)
{
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    return $im;
}

function downsample($big, int $size)
{
    $out = new_canvas($size);
    imagealphablending($out, false);
    imagecopyresampled($out, $big, 0, 0, 0, 0, $size, $size, imagesx($big), imagesy($big));
    imagesavealpha($out, true);
    imagedestroy($big);
    return $out;
}

// An app icon: brand-green rounded square, mark knocked out in white. iOS
// composites transparency onto black, so home-screen icons get a real
// background - just never a white one.
function app_icon(int $size, string $path): void
{
    $s = $size * SS;
    $im = new_canvas($s);
    $green = imagecolorallocate($im, 31, 125, 73);
    $white = imagecolorallocate($im, 255, 255, 255);
    $leaf = imagecolorallocate($im, 154, 219, 169);
    rounded_rect($im, $s, (int)round($s * 0.22), $green);
    // Mark inset to ~70% so it keeps clear of the corner radius and of
    // Android's maskable safe zone.
    $scale = $s * 0.70 / 64;
    draw_mark($im, $scale, ($s - 64 * $scale) / 2, ($s - 64 * $scale) / 2, $white, $leaf);
    imagepng(downsample($im, $size), $path, 9);
    echo "wrote $path ({$size}px)\n";
}

// A bare mark on transparency, for the favicon and anywhere the page
// background should show through.
function mark_png(int $size, string $path): void
{
    $s = $size * SS;
    $im = new_canvas($s);
    $ink = imagecolorallocate($im, 31, 125, 73);
    $leaf = imagecolorallocate($im, 47, 174, 102);
    $scale = $s * 0.92 / 64;
    draw_mark($im, $scale, ($s - 64 * $scale) / 2, ($s - 64 * $scale) / 2, $ink, $leaf);
    imagepng(downsample($im, $size), $path, 9);
    echo "wrote $path ({$size}px)\n";
}

$dir = dirname(__DIR__) . '/assets/img/logo';
app_icon(512, "$dir/app-icon.png");
app_icon(192, "$dir/app-icon-192.png");
app_icon(180, "$dir/apple-touch-icon.png");
mark_png(64, "$dir/favicon-64.png");
mark_png(32, "$dir/favicon-32.png");
mark_png(512, "$dir/mark.png");
echo "done\n";
