<?php
// Tests vendor_owed_amount() — the pure "what's owed" calculation added for
// CONTINUE.md §2.6 / Step 8 — against fixture arrays, no database.
//
//   php tests/vendor_payouts_test.php
//
// Deliberately not testing calculate_vendor_owed() or
// vendors_with_owed_balance() here: both are thin DB-fetching wrappers
// around this function with no logic of their own to get wrong — see
// CONTINUE.md's Step 8 note for how those were verified instead, against
// the live database with a synthetic vendor and real sale rows.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/vendor_payouts.php';

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

function sale(float $amount, string $createdAt): array
{
    return ['amount' => $amount, 'created_at' => $createdAt];
}

// --- No sales at all -------------------------------------------------------

$result = vendor_owed_amount([], []);
check('zero sales -> zero owed', $result['amount'] === 0.0);
check('zero sales -> no period start', $result['period_start'] === null);
check('zero sales -> zero sales_count', $result['sales_count'] === 0);

// --- Sales, no prior payout --------------------------------------------------

$sales = [
    sale(100.00, '2026-09-01 10:00:00'),
    sale(50.50, '2026-09-05 12:00:00'),
];
$result = vendor_owed_amount($sales, []);
check('no prior payout: sums every sale', $result['amount'] === 150.50);
check('no prior payout: sales_count is 2', $result['sales_count'] === 2);
check('no prior payout: period starts at the earliest sale', $result['period_start'] === '2026-09-01 10:00:00');

// --- A sale already covered by a prior payout must not be double-counted ---

$existingPayouts = [['period_end' => '2026-09-05 12:00:00']];
$result = vendor_owed_amount($sales, $existingPayouts);
check('a sale at/before the last payout period_end is excluded', $result['amount'] === 0.0);
check('fully-paid-out vendor has zero sales_count', $result['sales_count'] === 0);

// --- Only sales strictly after the last payout count -------------------------

$sales = [
    sale(100.00, '2026-09-01 10:00:00'), // already paid out
    sale(75.25, '2026-09-10 09:00:00'),  // new, after the cutoff
];
$existingPayouts = [['period_end' => '2026-09-05 12:00:00']];
$result = vendor_owed_amount($sales, $existingPayouts);
check('only the sale after the cutoff is owed', $result['amount'] === 75.25);
check('owed sales_count excludes the already-paid sale', $result['sales_count'] === 1);
check('period_start picks up exactly where the last payout left off', $result['period_start'] === '2026-09-05 12:00:00');

// --- Multiple payout runs never double-count across the whole history ------

$sales = [
    sale(100.00, '2026-09-01 10:00:00'),
    sale(50.00, '2026-09-10 10:00:00'),
    sale(25.00, '2026-09-20 10:00:00'),
];
$firstRun = vendor_owed_amount($sales, []);
check('run 1: owes all three sales', $firstRun['amount'] === 175.00);

// Simulate recording that first payout, then a second run with no new sales.
$afterFirstPayout = [['period_end' => '2026-09-20 10:00:01']]; // "now" at the time of that payout
$secondRun = vendor_owed_amount($sales, $afterFirstPayout);
check('run 2 with no new sales: owes nothing (not the same sales again)', $secondRun['amount'] === 0.0);

// A new sale arrives after the first payout - only it should be owed now.
$sales[] = sale(10.00, '2026-09-25 10:00:00');
$thirdRun = vendor_owed_amount($sales, $afterFirstPayout);
check('run 3: owes only the sale made after the last payout', $thirdRun['amount'] === 10.00);
check('run 3: sales_count is 1, not 4', $thirdRun['sales_count'] === 1);

// --- The latest of several existing payouts is what matters, not the first -

$sales = [sale(30.00, '2026-09-15 00:00:00')];
$outOfOrderPayouts = [
    ['period_end' => '2026-09-20 00:00:00'], // later payout listed first
    ['period_end' => '2026-09-10 00:00:00'], // earlier payout listed second
];
$result = vendor_owed_amount($sales, $outOfOrderPayouts);
check('uses the latest period_end regardless of array order', $result['amount'] === 0.0);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
