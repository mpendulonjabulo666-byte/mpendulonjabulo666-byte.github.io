<?php
// In-app account deletion - Apple App Store rule 5.1.1(v) ("if your app
// supports account creation, you must also offer account deletion within
// the app") and POPIA's right to have personal information deleted.
//
// Two outcomes, picked by whether the account has money attached to it:
//
//   'deleted'    - no payment history: the users row is removed and the
//                  database's ON DELETE CASCADE rules remove everything
//                  that hangs off it (pantry, allergens, plans, ...).
//   'anonymised' - the account bought, sold or subscribed: those payment
//                  rows belong to the other party too (a vendor's sales,
//                  their payouts) and must be kept as business records,
//                  and the CASCADE rules would wipe them. So every piece
//                  of personal data is removed instead, the name/email/
//                  password are replaced with values nobody can use, and
//                  deleted_at is set so the account can never sign in.
//
// Recipes the person wrote: deleted, unless someone paid for one - then
// it is hidden (is_published = 0) so the buyer's purchase record and
// access survive.
//
// An active Premium subscription is cancelled at PayFast FIRST. If PayFast
// does not confirm, nothing is deleted - otherwise the card would keep
// being charged for an account that no longer exists.

function account_has_payment_history(PDO $pdo, int $userId): bool
{
    $checks = [
        "SELECT 1 FROM recipe_purchases WHERE (buyer_id = ? OR vendor_id = ?) AND status = 'paid' LIMIT 1",
        "SELECT 1 FROM ingredient_orders WHERE (buyer_id = ? OR seller_id = ?) AND status = 'paid' LIMIT 1",
    ];
    foreach ($checks as $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    }
    $stmt = $pdo->prepare("SELECT 1 FROM premium_subscriptions WHERE user_id = ? AND (pf_token IS NOT NULL OR status IN ('active','past_due','cancelled','expired')) LIMIT 1");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM vendor_payouts WHERE vendor_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * @param callable $cancelSubscription fn(string $pfToken): array{ok: bool, error?: string}
 *                                     - payfast_cancel_subscription in the app, a stub in tests.
 * @param callable|null $deleteAvatar  fn(?string $path): void - avatar_delete in the app.
 * @return array{ok: bool, mode?: string, error?: string}
 */
function account_delete(PDO $pdo, int $userId, callable $cancelSubscription, ?callable $deleteAvatar = null): array
{
    $stmt = $pdo->prepare('SELECT id, email, is_admin, avatar_path FROM users WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) {
        return ['ok' => false, 'error' => 'Account not found.'];
    }

    // The last admin deleting themselves would leave nobody able to run
    // the admin portal, with no way back in except editing the database.
    if (!empty($account['is_admin'])) {
        $admins = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1 AND deleted_at IS NULL')->fetchColumn();
        if ($admins <= 1) {
            return ['ok' => false, 'error' => 'You are the only admin. Make someone else an admin first, then delete this account.'];
        }
    }

    $subStmt = $pdo->prepare("SELECT id, pf_token FROM premium_subscriptions WHERE user_id = ? AND status = 'active'");
    $subStmt->execute([$userId]);
    foreach ($subStmt->fetchAll(PDO::FETCH_ASSOC) as $sub) {
        if (!empty($sub['pf_token'])) {
            $result = $cancelSubscription($sub['pf_token']);
            if (empty($result['ok'])) {
                return ['ok' => false, 'error' => 'Your Premium subscription could not be cancelled at PayFast (' . ($result['error'] ?? 'no reason given') . '), so nothing was deleted. Please try again in a few minutes.'];
            }
        }
        $pdo->prepare("UPDATE premium_subscriptions SET status = 'cancelled' WHERE id = ?")->execute([$sub['id']]);
    }

    $keepRecords = account_has_payment_history($pdo, $userId);

    $pdo->beginTransaction();
    try {
        // Their recipes: hide the ones someone paid for, delete the rest.
        $pdo->prepare(
            "UPDATE recipes SET is_published = 0 WHERE created_by = ?
             AND id IN (SELECT recipe_id FROM recipe_purchases WHERE status = 'paid')"
        )->execute([$userId]);
        $pdo->prepare(
            "DELETE FROM recipes WHERE created_by = ?
             AND id NOT IN (SELECT recipe_id FROM recipe_purchases WHERE status = 'paid')"
        )->execute([$userId]);

        // Unsold marketplace listings go; sold ones stay with their order.
        $pdo->prepare("DELETE FROM ingredient_listings WHERE seller_id = ? AND status = 'available'")->execute([$userId]);

        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$account['email']]);

        if ($keepRecords) {
            foreach ([
                'favorites', 'meal_plan_items', 'shopping_list_checks', 'user_diet_preferences',
                'user_allergens', 'recipe_ratings', 'user_goals', 'password_resets',
                'user_pantry_items', 'ai_generations', 'recipe_views',
            ] as $table) {
                $pdo->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$userId]);
            }
            $pdo->prepare('DELETE FROM recipe_reports WHERE reporter_user_id = ?')->execute([$userId]);

            $pdo->prepare(
                "UPDATE users SET name = 'Deleted user', email = ?, password_hash = ?,
                    oauth_provider = NULL, oauth_id = NULL, avatar_path = NULL,
                    is_admin = 0, is_vendor = 0, is_premium_member = 0, email_notifications = 0,
                    failed_attempts = 0, locked_until = NULL, deleted_at = NOW()
                 WHERE id = ?"
            )->execute([
                'deleted-' . $userId . '-' . bin2hex(random_bytes(6)) . '@deleted.invalid',
                password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                $userId,
            ]);
            $mode = 'anonymised';
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $mode = 'deleted';
        }

        // Sign the account out on every device, not just this one. Session
        // data is stored as PHP's serialised "user_id|i:42;" text.
        $pdo->prepare('DELETE FROM nutri_sessions WHERE data LIKE ?')
            ->execute(['%user_id|i:' . $userId . ';%']);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('account_delete(' . $userId . ') failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong and nothing was deleted. Please try again.'];
    }

    // The photo file is removed only once the database change is final.
    if ($deleteAvatar && !empty($account['avatar_path'])) {
        $deleteAvatar($account['avatar_path']);
    }

    return ['ok' => true, 'mode' => $mode];
}
