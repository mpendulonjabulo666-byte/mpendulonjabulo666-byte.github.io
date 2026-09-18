<?php
require_once __DIR__ . '/functions.php';

// The core "what's owed" rule, kept as a pure function (no DB) so it can be
// unit-tested directly - see tests/vendor_payouts_test.php - the same shape
// as ai_pantry_hash()/gemini_pantry_ideas()'s injectable-data pattern
// elsewhere in this app.
//
// A payout covers everything earned strictly after the latest *non-reversed*
// existing payout's period_end (pending or paid both still "spend" that
// range - only 'reversed' gives it back) up to now. That's enough to never
// double-count a sale across runs without a per-sale join table: periods
// are contiguous and non-overlapping as long as every new payout starts
// exactly where the last active one's period_end left off, which is what
// calculate_vendor_owed() below always does. A reversed payout is skipped
// entirely when finding that cutoff - exactly as if it had never been
// created - so the money it covered becomes owed again on the next run.
//
// $paidSales: list of ['amount' => float, 'created_at' => 'Y-m-d H:i:s']
// $existingPayouts: list of ['period_end' => 'Y-m-d H:i:s', 'status' => string]
function vendor_owed_amount(array $paidSales, array $existingPayouts): array
{
    $cutoff = null;
    foreach ($existingPayouts as $payout) {
        if ($payout['status'] === 'reversed') {
            continue;
        }
        if ($cutoff === null || $payout['period_end'] > $cutoff) {
            $cutoff = $payout['period_end'];
        }
    }

    // Strictly-after, not >=: a sale timestamped at the exact same instant
    // as a prior payout's period_end (theoretically possible, never seen
    // in practice at second precision) is left for the *next* run rather
    // than risking it being silently dropped by an off-by-one the other
    // direction would create.
    $owedSales = array_values(array_filter(
        $paidSales,
        fn(array $sale) => $cutoff === null || $sale['created_at'] > $cutoff
    ));

    if (!$owedSales) {
        return ['amount' => 0.0, 'period_start' => null, 'sales_count' => 0];
    }

    $amount = array_sum(array_column($owedSales, 'amount'));
    $periodStart = $cutoff ?? min(array_column($owedSales, 'created_at'));

    return [
        'amount' => round($amount, 2),
        'period_start' => $periodStart,
        'sales_count' => count($owedSales),
    ];
}

// Gathers a vendor's real sale rows from both sale tables (recipe_purchases
// uses vendor_id/vendor_amount, ingredient_orders uses seller_id/
// seller_amount - same "paid" status string in both, see vendor.php's own
// existing queries) and their payout history, normalises both into the
// plain-array shape vendor_owed_amount() expects, and returns what's owed
// right now for a new payout run.
function calculate_vendor_owed(PDO $pdo, int $vendorId): array
{
    $recipeSales = $pdo->prepare(
        "SELECT vendor_amount AS amount, created_at FROM recipe_purchases WHERE vendor_id = ? AND status = 'paid'"
    );
    $recipeSales->execute([$vendorId]);

    $ingredientSales = $pdo->prepare(
        "SELECT seller_amount AS amount, created_at FROM ingredient_orders WHERE seller_id = ? AND status = 'paid'"
    );
    $ingredientSales->execute([$vendorId]);

    $paidSales = array_map(
        fn(array $row) => ['amount' => (float)$row['amount'], 'created_at' => $row['created_at']],
        array_merge($recipeSales->fetchAll(), $ingredientSales->fetchAll())
    );

    $payoutStmt = $pdo->prepare('SELECT period_end, status FROM vendor_payouts WHERE vendor_id = ?');
    $payoutStmt->execute([$vendorId]);
    $existingPayouts = $payoutStmt->fetchAll();

    return vendor_owed_amount($paidSales, $existingPayouts);
}

// The one payout per vendor that's currently eligible for reversal - the
// most recently created row that isn't already reversed. Deliberately by
// id, not period_end: id is the unambiguous creation order regardless of
// any period_end edge case, and payouts are always created moving forward
// in time anyway. Reversing this one is always safe for the contiguous-
// period invariant; reversing anything further back would leave a gap
// (§2.6's punch-list already flags "no way to reverse a non-latest payout"
// as a deliberate v1 limit, not an oversight).
function latest_reversible_payout(PDO $pdo, int $vendorId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM vendor_payouts WHERE vendor_id = ? AND status IN ('pending', 'paid') ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$vendorId]);
    return $stmt->fetch() ?: null;
}

// vendor_id => id of that vendor's one reversible payout, for every vendor
// that has one - lets admin_payouts.php's combined, all-vendors history
// table decide per-row whether to show the Reverse action without an N+1
// query per row.
function latest_reversible_payout_ids(PDO $pdo): array
{
    return $pdo->query(
        "SELECT vendor_id, MAX(id) AS id FROM vendor_payouts WHERE status IN ('pending', 'paid') GROUP BY vendor_id"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Reverses a payout, re-validating the same "latest and not already
// reversed" rule server-side rather than trusting that admin_payouts.php
// only ever rendered the button where it should have - a button being
// hidden in one browser tab doesn't stop a POST crafted (or replayed from
// an already-stale page) in another.
function reverse_vendor_payout(PDO $pdo, int $payoutId, int $adminId, string $reason): array
{
    if (trim($reason) === '') {
        return ['ok' => false, 'error' => 'A reason is required to reverse a payout.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM vendor_payouts WHERE id = ?');
    $stmt->execute([$payoutId]);
    $payout = $stmt->fetch();

    if (!$payout) {
        return ['ok' => false, 'error' => 'Payout not found.'];
    }
    if ($payout['status'] === 'reversed') {
        return ['ok' => false, 'error' => 'That payout has already been reversed.'];
    }

    $latest = latest_reversible_payout($pdo, (int)$payout['vendor_id']);
    if (!$latest || (int)$latest['id'] !== $payoutId) {
        return ['ok' => false, 'error' => 'Only the most recent payout for a vendor can be reversed - a newer one exists.'];
    }

    $pdo->prepare(
        "UPDATE vendor_payouts SET status = 'reversed', reversed_at = NOW(), reversed_by_admin_id = ?, reversal_reason = ? WHERE id = ?"
    )->execute([$adminId, trim($reason), $payoutId]);

    return ['ok' => true, 'error' => null];
}

// Every vendor with something currently owed - backs admin_payouts.php's
// list. is_vendor isn't required here deliberately: someone could earn a
// sale, then turn selling off in their profile, and still be owed the
// money from before they did.
function vendors_with_owed_balance(PDO $pdo): array
{
    $vendorIds = $pdo->query(
        "SELECT DISTINCT vendor_id AS id FROM recipe_purchases WHERE status = 'paid'
         UNION
         SELECT DISTINCT seller_id AS id FROM ingredient_orders WHERE status = 'paid'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $result = [];
    foreach ($vendorIds as $vendorId) {
        $owed = calculate_vendor_owed($pdo, (int)$vendorId);
        if ($owed['amount'] > 0) {
            $result[(int)$vendorId] = $owed;
        }
    }
    return $result;
}
