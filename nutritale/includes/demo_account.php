<?php
// The login Apple (and Google Play) reviewers use. Store review rule 2.1:
// "If your app includes account-based features, provide either an active
// demo account or fully-featured demo mode."
//
// Created from Admin > Users > "App review demo account". Each press makes
// a fresh random password and shows it to the admin once - it is never
// stored anywhere readable, only as a hash like every other password.
// The account is Premium so the reviewer can see every feature, and gets
// a few pantry items so the pantry matcher has something to show.

const DEMO_ACCOUNT_NAME = 'App Review';
const DEMO_PANTRY_ITEMS = ['eggs', 'onion', 'tomato', 'rice', 'chicken breast', 'garlic'];

function demo_account_email(string $host): string
{
    $host = strtolower(preg_replace('/:\d+$/', '', $host));
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) {
        $host = 'nutritale.freedev.app';
    }
    return 'appreview@' . $host;
}

function demo_account_password(): string
{
    // Letters and numbers only (easy to type on a phone), always passing
    // register.php's rule of 8+ characters with a letter and a number.
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $password = 'Review';
    for ($i = 0; $i < 6; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $password . random_int(10, 99);
}

/** @return array{email: string, password: string} */
function demo_account_upsert(PDO $pdo, string $email): array
{
    $password = demo_account_password();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();

    if ($id) {
        $pdo->prepare(
            'UPDATE users SET name = ?, password_hash = ?, is_admin = 0, is_premium_member = 1,
                failed_attempts = 0, locked_until = NULL, deleted_at = NULL,
                onboarded_at = COALESCE(onboarded_at, NOW()), tour_seen_at = COALESCE(tour_seen_at, NOW())
             WHERE id = ?'
        )->execute([DEMO_ACCOUNT_NAME, $hash, $id]);
    } else {
        $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, is_admin, is_premium_member, onboarded_at, tour_seen_at)
             VALUES (?, ?, ?, 0, 1, NOW(), NOW())'
        )->execute([DEMO_ACCOUNT_NAME, $email, $hash]);
        $id = (int)$pdo->lastInsertId();
    }

    $add = $pdo->prepare('INSERT IGNORE INTO user_pantry_items (user_id, ingredient_name) VALUES (?, ?)');
    foreach (DEMO_PANTRY_ITEMS as $item) {
        $add->execute([$id, $item]);
    }

    return ['email' => $email, 'password' => $password];
}
