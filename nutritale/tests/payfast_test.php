<?php
// Tests payfast_request_is_from_payfast() — the ITN source-IP check added
// for CONTINUE.md §2.5 — with a stubbed DNS resolver, so no real network
// lookup and no dependency on what PayFast's hostnames currently resolve
// to.
//
//   php tests/payfast_test.php
//
// Also tests payfast_api_signature() (added for Step 12's subscription
// cancellation) against a value computed independently, not by calling
// the function under test on itself.
//
// Deliberately not testing payfast_signature(), payfast_confirm_with_payfast(),
// payfast_api_request(), payfast_cancel_subscription(), or
// payfast_notify.php itself here: the first two are unchanged by this
// step; the latter three make (or wrap something that makes) a real HTTP
// call, so there's nothing a unit test can usefully fake without just
// re-asserting the mock - see CONTINUE.md's Step 12 note for how the
// cancel flow was actually checked instead.

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

// --- payfast_api_signature() - the REST API's signature scheme, distinct
// from payfast_signature()'s checkout-flow one, added for Step 12 --------
$fixture = ['merchant-id' => '10000100', 'version' => 'v1', 'timestamp' => '2026-01-01T00:00:00+00:00'];

// Computed independently (not by calling payfast_api_signature() itself)
// by running the same algorithm PayFast's own SDK documents:
//   ksort(['merchant-id'=>..., 'passphrase'=>..., 'timestamp'=>..., 'version'=>...])
//   -> "merchant-id=10000100&passphrase=testpass&timestamp=2026-01-01T00%3A00%3A00%2B00%3A00&version=v1"
//   -> md5(...)
check(
    'matches an independently-computed reference signature',
    payfast_api_signature($fixture, 'testpass') === '2c2f31301266715b74ebe4f5987e92fa'
);
check(
    'key order in the input does not change the result (it gets ksorted)',
    payfast_api_signature(array_reverse($fixture, true), 'testpass') === payfast_api_signature($fixture, 'testpass')
);
check(
    'a different passphrase changes the signature',
    payfast_api_signature($fixture, 'a-different-passphrase') !== payfast_api_signature($fixture, 'testpass')
);
check(
    'a different data value changes the signature',
    payfast_api_signature(['merchant-id' => '99999999'] + $fixture, 'testpass') !== payfast_api_signature($fixture, 'testpass')
);
check(
    'an existing "signature" key in the input is excluded, not signed over',
    payfast_api_signature($fixture + ['signature' => 'whatever-was-here-before'], 'testpass') === payfast_api_signature($fixture, 'testpass')
);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
