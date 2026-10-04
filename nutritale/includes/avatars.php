<?php
// Profile photo uploads. This is the only place in the app that accepts a
// file from a user, so the rules are deliberately strict and layered:
//
// 1. The type is decided by reading the file's own bytes (exif_imagetype),
//    never by its name or the browser-supplied MIME type - both are
//    attacker-controlled.
// 2. The stored name is random and its extension comes from the detected
//    type, so a "photo.php" can never land on disk as something executable.
// 3. Where GD is available the image is re-encoded rather than copied,
//    which drops EXIF and anything hidden after the image data (the classic
//    polyglot upload). Where it isn't, the validated original is stored -
//    still only ever as a known image type under a generated name.
// 4. avatars/.htaccess turns off script handling for the directory, so even
//    a file that somehow got through could not be executed by Apache.
//
// Nothing here trusts $_FILES beyond using it as a starting point.

const AVATAR_DIR = 'assets/img/avatars';
const AVATAR_MAX_BYTES = 5 * 1024 * 1024;
const AVATAR_MAX_EDGE = 512;

// Detected image type => the extension we store it under.
const AVATAR_TYPES = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_WEBP => 'webp',
];

function avatar_dir_path(): string
{
    return dirname(__DIR__) . '/' . AVATAR_DIR;
}

// Creates the upload directory on first use, together with the .htaccess
// that stops Apache treating anything inside it as a script.
function avatar_ensure_dir(): bool
{
    $dir = avatar_dir_path();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, implode("\n", [
            '# Uploaded images only - never executed.',
            // "php_flag engine off" deliberately NOT here: it only works
            // under mod_php. On hosts that run PHP via FastCGI/CGI
            // instead (InfinityFree included), Apache can't parse an
            // unrecognized php_flag directive and 500s every request to
            // this whole directory - including the images themselves.
            // The FilesMatch block below blocks php execution without
            // depending on which SAPI is running PHP.
            'SetHandler default-handler',
            'Options -ExecCGI -Indexes',
            '<FilesMatch "\.(?i:php|phtml|phar|cgi|pl|py|sh|htaccess)$">',
            '    Require all denied',
            '</FilesMatch>',
            '',
        ]));
    }
    return true;
}

// Validates and stores one uploaded avatar. Returns [ok, path|error].
// $file is a single entry from $_FILES.
function avatar_store(array $file, int $userId): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        return [false, 'Choose a photo first.'];
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return [false, 'That photo is too large. Pick one under 5MB.'];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return [false, 'That photo did not upload properly. Try again.'];
    }

    $tmp = $file['tmp_name'] ?? '';
    // The one check that makes the rest meaningful: without it a local path
    // could be passed off as an upload.
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        return [false, 'That photo did not upload properly. Try again.'];
    }
    if (filesize($tmp) > AVATAR_MAX_BYTES) {
        return [false, 'That photo is too large. Pick one under 5MB.'];
    }

    $type = @exif_imagetype($tmp);
    if ($type === false || !isset(AVATAR_TYPES[$type])) {
        return [false, 'That file is not a JPEG, PNG or WebP image.'];
    }
    // Rejects a file whose header says "image" but has no usable dimensions.
    $size = @getimagesize($tmp);
    if (!is_array($size) || ($size[0] ?? 0) < 1 || ($size[1] ?? 0) < 1) {
        return [false, 'That image could not be read. Try a different photo.'];
    }

    if (!avatar_ensure_dir()) {
        return [false, 'The server could not save the photo right now.'];
    }

    $ext = AVATAR_TYPES[$type];
    $name = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = avatar_dir_path() . '/' . $name;

    if (!avatar_write($tmp, $dest, $type)) {
        return [false, 'The server could not save the photo right now.'];
    }
    @chmod($dest, 0644);

    return [true, AVATAR_DIR . '/' . $name];
}

// Re-encodes through GD when it is loaded (dropping EXIF and any trailing
// payload), otherwise moves the already-validated upload as-is.
function avatar_write(string $tmp, string $dest, int $type): bool
{
    if (!extension_loaded('gd')) {
        return move_uploaded_file($tmp, $dest);
    }

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default => false,
    };
    if (!$src) {
        // GD could not decode what exif_imagetype() claimed - treat the file
        // as untrustworthy rather than falling back to storing it raw.
        return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, AVATAR_MAX_EDGE / max($w, $h));
    $tw = max(1, (int)round($w * $scale));
    $th = max(1, (int)round($h * $scale));

    $out = imagecreatetruecolor($tw, $th);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    }
    imagecopyresampled($out, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($out, $dest, 85),
        IMAGETYPE_PNG => imagepng($out, $dest, 6),
        IMAGETYPE_WEBP => function_exists('imagewebp') && imagewebp($out, $dest, 85),
        default => false,
    };

    imagedestroy($src);
    imagedestroy($out);
    return (bool)$ok;
}

// Deletes a previously stored avatar. Only ever removes a file that sits
// inside the avatars directory and matches the names avatar_store() makes,
// so a tampered column value can't point the unlink somewhere else.
function avatar_delete(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }
    if (!preg_match('#^' . preg_quote(AVATAR_DIR, '#') . '/u\d+_[0-9a-f]{16}\.(jpg|png|webp)$#', $path)) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}
