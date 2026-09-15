<?php
// Ordered schema changes, applied at most once each and tracked by key in
// schema_migrations. setup.php runs every entry here not already
// recorded, in array order, on every install/upgrade.
//
// sql/schema.sql only ever CREATEs a table that might not exist yet - see
// the note at its top. Anything else (ALTER on an existing table, a
// backfill, a rename) belongs here instead, because "already applied" has
// to be tracked explicitly rather than expressed in the SQL itself.
//
// Rules for adding to this file:
//   - Append new entries at the end. Never reorder or remove one that has
//     shipped - a deployed site's schema_migrations remembers it by key,
//     and removing the key here would make setup.php run it again.
//   - Never edit a migration's SQL after it has shipped. A later change
//     is a new entry, even if it touches the same table.
//   - A key is a permanent record, not a description - date-prefix it
//     (YYYY_MM_DD) so the order is legible, but don't rename it later.
//   - A value may hold more than one ';'-separated statement; each is run
//     in order and the whole entry is marked applied only once all of
//     them succeed. It may instead be a callable taking the PDO connection,
//     for seeding structured data with real parameter binding.
return [
    // CONTINUE.md step 5: lets the pantry page cache an AI result for an
    // identical request (see ai_pantry_hash() in includes/ai_pantry.php)
    // and count generation attempts per user per day, so a premium or
    // admin account - which pantry.php's free-trial counter never
    // touches - still has a hard ceiling on Gemini spend.
    '2026_09_14_ai_generations' => "
        CREATE TABLE ai_generations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            pantry_hash CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
            result_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_hash (user_id, pantry_hash, created_at),
            INDEX idx_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // CONTINUE.md step 6: canonical ingredient identities + synonyms for
    // includes/ingredient_matching.php, replacing pantry.php's old
    // substring matcher. Only ingredients that need an actual synonym or
    // an irregular spelling get a row here - a regular plural or a word
    // nobody needs to rename already resolves through that file's
    // tokenizer/depluralizer with no seed data at all. See its header
    // comment for the full reasoning.
    '2026_09_14_ingredient_taxonomy' => function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE ingredients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            canonical_name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE ingredient_aliases (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ingredient_id INT UNSIGNED NOT NULL,
            alias VARCHAR(100) NOT NULL UNIQUE,
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // alias => 'mince' deserves a note: the only ground-meat ingredient
        // this app ships with is "Ground turkey", and a real pantry row in
        // this app's own data is literally "meat mince" (CONTINUE.md
        // §2.2). Mapping the generic term to the one specific meat we have
        // is a compromise, not a fact - mince is usually beef, not turkey.
        // Revisit (probably by giving "mince" its own canonical ingredient)
        // if a second ground-meat recipe with a different meat ships.
        $seed = [
            'tomato' => ['passata'],
            'pepper' => ['capsicum', 'capsicums'],
            'chickpea' => ['garbanzo', 'chick pea'],
            'yogurt' => ['yoghurt'],
            'turkey' => ['mince'],
            'stock' => ['broth', 'bouillon'],
            'soy' => ['soya'],
            'oat' => ['oatmeal'],
            'zucchini' => ['courgette', 'courgettes', 'baby marrow', 'baby marrows'],
            'chili' => ['chilli'],
        ];
        $insertIngredient = $pdo->prepare('INSERT INTO ingredients (canonical_name) VALUES (?)');
        $insertAlias = $pdo->prepare('INSERT INTO ingredient_aliases (ingredient_id, alias) VALUES (?, ?)');
        foreach ($seed as $canonical => $aliases) {
            $insertIngredient->execute([$canonical]);
            $id = (int)$pdo->lastInsertId();
            foreach ($aliases as $alias) {
                $insertAlias->execute([$id, $alias]);
            }
        }
    },

    // CONTINUE.md step 7: without this, is_premium_member flips on at
    // first payment and stays on forever until an explicit CANCELLED ITN
    // arrives - a renewal that silently fails to charge (or a notify_url
    // outage at the wrong moment) leaves someone premium for free with no
    // mechanism to ever notice. NULL for every row that exists before this
    // migration runs - deliberately not backfilled, since we don't know
    // any pre-migration subscription's real paid-through date and a wrong
    // guess could downgrade someone who's still legitimately paying. See
    // premium_enforce_expiry() in includes/functions.php, which treats
    // NULL as "not tracked yet, don't touch" rather than "expired."
    '2026_09_15_premium_period_end' => 'ALTER TABLE premium_subscriptions ADD COLUMN current_period_end DATETIME NULL AFTER status',
];
