<?php
// Rebuilds the logo assets from the original book artwork. Run from the app
// root:   php scripts/make_logo.php
//
// Two jobs:
//
// 1. Knock the white background out of the source artwork. A plain "make
//    every white pixel transparent" would eat the book's own cream pages,
//    so this flood-fills inward from the border instead: only white that is
//    connected to the edge is background. Pixels on the resulting boundary
//    get partial alpha scaled by how close to white they are, which is what
//    stops the cut-out showing a hard white fringe against a dark header.
//
// 2. Produce the app-list icons from that cut-out with no wordmark in them.
//    The name belongs beside the icon, set by the OS, not inside the
//    artwork - the previous icons had "NutriTale" painted in and it was
//    unreadable at launcher size on top of being a duplicate.
//
// iOS composites a transparent icon onto black, so the app icons keep a
// solid plate. It is the brand cream rather than white so the book's pages
// still separate from it.

const SRC = 'assets/img/logo/source-book.png';   // original artwork, white bg
const OUT_DIR = 'assets/img/logo';

// A pixel counts as background if every channel is at least this bright and
// the channels are close enough together to be a neutral (so the book's
// warm cream, which is bright but clearly tinted, is never swallowed).
const BG_MIN = 232;
const BG_NEUTRAL_SPREAD = 14;

function is_bgish(int $r, int $g, int $b): bool
{
    return $r >= BG_MIN && $g >= BG_MIN && $b >= BG_MIN
        && (max($r, $g, $b) - min($r, $g, $b)) <= BG_NEUTRAL_SPREAD;
}

// Flood fill from every border pixel; returns a bool map of background.
function background_mask($im, int $w, int $h): array
{
    $bg = array_fill(0, $w * $h, false);
    $seen = array_fill(0, $w * $h, false);
    $queue = [];

    $push = function (int $x, int $y) use (&$queue, &$seen, $im, $w, $h) {
        if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) return;
        $i = $y * $w + $x;
        if ($seen[$i]) return;
        $seen[$i] = true;
        $rgb = imagecolorat($im, $x, $y);
        if (is_bgish(($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF)) {
            $queue[] = $i;
        }
    };

    for ($x = 0; $x < $w; $x++) { $push($x, 0); $push($x, $h - 1); }
    for ($y = 0; $y < $h; $y++) { $push(0, $y); $push($w - 1, $y); }

    while ($queue) {
        $i = array_pop($queue);
        $bg[$i] = true;
        $x = $i % $w; $y = intdiv($i, $w);
        $push($x + 1, $y); $push($x - 1, $y); $push($x, $y + 1); $push($x, $y - 1);
    }
    return $bg;
}

function cut_out(string $path)
{
    $src = imagecreatefrompng($path) ?: imagecreatefromjpeg($path);
    $w = imagesx($src); $h = imagesy($src);
    $bg = background_mask($src, $w, $h);

    $out = imagecreatetruecolor($w, $h);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $i = $y * $w + $x;
            $rgb = imagecolorat($src, $x, $y);
            $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;

            if ($bg[$i]) {
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 255, 255, 255, 127));
                continue;
            }
            // Edge feather: a kept pixel touching the background is part of
            // the artwork's antialiased rim, which was blended toward white.
            // Fade it by how white it is so the cut edge stays soft instead
            // of leaving a bright outline.
            $touches = ($x > 0 && $bg[$i - 1]) || ($x < $w - 1 && $bg[$i + 1])
                || ($y > 0 && $bg[$i - $w]) || ($y < $h - 1 && $bg[$i + $w]);
            $alpha = 0;
            if ($touches) {
                $lum = min(255, (int)round(0.299 * $r + 0.587 * $g + 0.114 * $b));
                if ($lum > 200) {
                    $alpha = (int)round(min(110, ($lum - 200) / 55 * 110));
                }
            }
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $alpha));
        }
    }
    imagedestroy($src);
    return $out;
}

// Trims fully transparent margins so the mark fills its box.
function trim_alpha($im)
{
    $w = imagesx($im); $h = imagesy($im);
    $x0 = $w; $y0 = $h; $x1 = -1; $y1 = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if ((imagecolorat($im, $x, $y) >> 24) < 120) {
                if ($x < $x0) $x0 = $x;
                if ($x > $x1) $x1 = $x;
                if ($y < $y0) $y0 = $y;
                if ($y > $y1) $y1 = $y;
            }
        }
    }
    if ($x1 < 0) return $im;
    $tw = $x1 - $x0 + 1; $th = $y1 - $y0 + 1;
    $out = imagecreatetruecolor($tw, $th);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopy($out, $im, 0, 0, $x0, $y0, $tw, $th);
    return $out;
}

function blank(int $w, int $h)
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    return $im;
}

function rounded_rect($im, int $w, int $r, int $colour): void
{
    imagefilledrectangle($im, $r, 0, $w - $r, $w, $colour);
    imagefilledrectangle($im, 0, $r, $w, $w - $r, $colour);
    foreach ([[$r,$r], [$w-$r,$r], [$r,$w-$r], [$w-$r,$w-$r]] as [$cx, $cy]) {
        imagefilledellipse($im, $cx, $cy, $r*2, $r*2, $colour);
    }
}

// Square app icon on a solid plate, no text.
//
// $frac is how much of the tile's width the artwork spans, and it differs
// by purpose. A normal ("any") icon is shown as drawn, so the book can run
// nearly edge to edge and stay legible at launcher size. A "maskable" icon
// is cropped by the launcher to whatever shape it likes - the guaranteed
// area is only the middle 80% circle - so that variant pulls the artwork in
// and skips the rounded corners, since the OS supplies the shape.
function app_icon($mark, int $size, string $path, float $frac = 0.98, bool $rounded = true): void
{
    $SS = 2; $s = $size * $SS;
    $im = blank($s, $s);
    imagealphablending($im, true);
    $plate = imagecolorallocate($im, 244, 241, 230); // brand cream
    if ($rounded) {
        rounded_rect($im, $s, (int)round($s * 0.22), $plate);
    } else {
        imagefilledrectangle($im, 0, 0, $s, $s, $plate);
    }
    $mw = imagesx($mark); $mh = imagesy($mark);
    $scale = min($s * $frac / $mw, $s * $frac / $mh);
    $dw = (int)round($mw * $scale); $dh = (int)round($mh * $scale);
    imagecopyresampled($im, $mark, (int)(($s-$dw)/2), (int)(($s-$dh)/2), 0, 0, $dw, $dh, $mw, $mh);

    $out = blank($size, $size);
    imagealphablending($out, false);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $s, $s);
    imagepng($out, $path, 9);
    echo "wrote $path ({$size}px)\n";
}

// Transparent square version, for the favicon and anywhere the page shows through.
function mark_png($mark, int $size, string $path): void
{
    $SS = 2; $s = $size * $SS;
    $im = blank($s, $s);
    imagealphablending($im, true);
    $mw = imagesx($mark); $mh = imagesy($mark);
    $scale = min($s / $mw, $s / $mh);
    $dw = (int)round($mw * $scale); $dh = (int)round($mh * $scale);
    imagecopyresampled($im, $mark, (int)(($s-$dw)/2), (int)(($s-$dh)/2), 0, 0, $dw, $dh, $mw, $mh);
    $out = blank($size, $size);
    imagealphablending($out, false);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $s, $s);
    imagepng($out, $path, 9);
    echo "wrote $path ({$size}px)\n";
}

$root = dirname(__DIR__);
$srcPath = $root . '/' . SRC;
if (!is_file($srcPath)) {
    fwrite(STDERR, "missing source artwork: " . SRC . "\n");
    exit(1);
}

echo "cutting background out of " . SRC . " ...\n";
$mark = trim_alpha(cut_out($srcPath));
echo "cut-out: " . imagesx($mark) . 'x' . imagesy($mark) . "\n";

// The header logo: the artwork itself, transparent, at a sane file size.
$wide = blank(600, (int)round(600 * imagesy($mark) / imagesx($mark)));
imagealphablending($wide, false);
imagecopyresampled($wide, $mark, 0, 0, 0, 0, imagesx($wide), imagesy($wide), imagesx($mark), imagesy($mark));
imagepng($wide, $root . '/' . OUT_DIR . '/book-mark.png', 9);
echo "wrote book-mark.png (" . imagesx($wide) . 'x' . imagesy($wide) . ")\n";

// Shown as drawn (home screen on iOS, "any" on Android).
app_icon($mark, 512, $root . '/' . OUT_DIR . '/app-icon.png');
app_icon($mark, 192, $root . '/' . OUT_DIR . '/app-icon-192.png');
app_icon($mark, 180, $root . '/' . OUT_DIR . '/apple-touch-icon.png');
// Cropped to a launcher shape: artwork inside the 80% safe zone, plate
// full-bleed because the launcher rounds it itself.
app_icon($mark, 512, $root . '/' . OUT_DIR . '/app-icon-maskable.png', 0.70, false);
mark_png($mark, 64, $root . '/' . OUT_DIR . '/favicon-64.png');
mark_png($mark, 32, $root . '/' . OUT_DIR . '/favicon-32.png');
echo "done\n";
