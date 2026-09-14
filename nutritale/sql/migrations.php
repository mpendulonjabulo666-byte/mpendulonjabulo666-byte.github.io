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
//     them succeed.
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
];
