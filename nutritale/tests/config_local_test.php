<?php
// Tests nt_parse_local_cfg() - how config.php reads secrets out of
// config.local.php on hosts where putenv()/getenv() don't work.
//
//   php tests/config_local_test.php

require_once __DIR__ . '/../config/config.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL  $label\n";
}

$pem = "-----BEGIN PRIVATE KEY-----\nMIGTAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBHkwdwIBAQQg\nabc123\n-----END PRIVATE KEY-----";
$src = "<?php\n"
    . "putenv('DB_HOST=sql123.example.net');\n"
    . "  putenv( 'DB_PASS=p\\'w0rd' );\n"
    . "putenv('APPLE_OAUTH_PRIVATE_KEY=" . $pem . "');\n"
    . "putenv('EMPTY_ONE=');\n"
    . "// putenv('COMMENTED=no') is not at the start of a line\n"
    . "putenv('AFTER_KEY=still-read');\n";

$cfg = nt_parse_local_cfg($src);

check('single-line value', ($cfg['DB_HOST'] ?? null) === 'sql123.example.net');
check('spaces inside putenv() and an escaped quote', ($cfg['DB_PASS'] ?? null) === "p'w0rd");
check('multi-line PEM key is read whole', ($cfg['APPLE_OAUTH_PRIVATE_KEY'] ?? null) === $pem);
check('empty value', array_key_exists('EMPTY_ONE', $cfg) && $cfg['EMPTY_ONE'] === '');
check('commented-out line is ignored', !array_key_exists('COMMENTED', $cfg));
check('a line after the multi-line key is still read', ($cfg['AFTER_KEY'] ?? null) === 'still-read');
check('nothing in, nothing out', nt_parse_local_cfg('<?php // no settings') === []);

echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
