<?php
// Tests payfast_request_is_from_payfast() — the ITN source-IP check added
// for CONTINUE.md §2.5 — with a stubbed DNS resolver, so no real network
// lookup and no dependency on what PayFast's hostnames currently resolve
// to.
//
//   php tests/payfast_test.php
//
// Deliberately not testing payfast_signature(), payfast_confirm_with_payfast(),
// or payfast_notify.php itself here: the first two are unchanged by this
// step, and the notify script is a sequence of DB writes driven by a real
// PayFast POST, verified against the live database instead (see
// CONTINUE.md's Step 7 note) the same way this project's other DB-facing
// code has been throughout.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/payfast.php';

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

// A resolver stub: $answers maps hostname => its "DNS result" (an array of
// IPs, or false for "didn't resolve" — gethostbynamel()'s own contract).
function stub_resolver(array $answers): callable
{
    return fn(string $host) => $answers[$host] ?? false;
}

// --- The request's IP is one of the resolved addresses -------------------
$resolver = stub_resolver([
    'www.payfast.co.za' => ['197.97.145.144'],
    'sandbox.payfast.co.za' => ['197.97.145.145'],
    'w1w.payfast.co.za' => false,
    'w2w.payfast.co.za' => false,
]);
check('a genuine PayFast IP is accepted', payfast_request_is_from_payfast('197.97.145.144', $resolver));
check('the sandbox host\'s IP is accepted too', payfast_request_is_from_payfast('197.97.145.145', $resolver));
check('an unrelated IP is rejected', !payfast_request_is_from_payfast('10.0.0.1', $resolver));
check('an empty remote address is rejected, not treated as a wildcard', !payfast_request_is_from_payfast('', $resolver));

// --- Duplicate IPs across hostnames don't change the outcome -------------
$dupeResolver = stub_resolver([
    'www.payfast.co.za' => ['197.97.145.144'],
    'sandbox.payfast.co.za' => ['197.97.145.144'],
    'w1w.payfast.co.za' => ['197.97.145.144'],
    'w2w.payfast.co.za' => ['197.97.145.144'],
]);
check('one IP shared by every hostname still matches', payfast_request_is_from_payfast('197.97.145.144', $dupeResolver));

// --- Total DNS failure fails OPEN, not closed - see the function's own
// comment for why (a local resolver outage shouldn't silently drop every
// real payment notification; the signature + VALID-confirmation checks
// are the primary defence, this is on top of them) ------------------------
$noResolver = stub_resolver([]); // every hostname misses
check('total DNS failure fails open (allows the request through)', payfast_request_is_from_payfast('10.0.0.1', $noResolver));

// --- A partial failure (some hostnames resolve, some don't) still
// enforces normally against whatever did resolve --------------------------
$partialResolver = stub_resolver([
    'www.payfast.co.za' => false,
    'sandbox.payfast.co.za' => false,
    'w1w.payfast.co.za' => ['41.185.26.42'],
    'w2w.payfast.co.za' => false,
]);
check('a partial resolution still accepts an IP that did resolve', payfast_request_is_from_payfast('41.185.26.42', $partialResolver));
check('a partial resolution still rejects an unrelated IP (not a full fail-open)', !payfast_request_is_from_payfast('10.0.0.1', $partialResolver));

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
