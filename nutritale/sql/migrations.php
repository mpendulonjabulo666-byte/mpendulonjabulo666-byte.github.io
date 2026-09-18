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

    // Admin suite build: backs the new "Report this recipe" link on
    // recipe.php and admin_reports.php's review queue. UNIQUE on
    // (recipe_id, reporter_user_id) so re-submitting the same report just
    // tells the reporter they've already flagged it, rather than piling
    // up duplicates an admin has to skip past one by one.
    '2026_09_18_recipe_reports' => "
        CREATE TABLE recipe_reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipe_id VARCHAR(40) NOT NULL,
            reporter_user_id INT UNSIGNED NOT NULL,
            reason VARCHAR(30) NOT NULL,
            details VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_recipe_reporter (recipe_id, reporter_user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Admin suite build: backs admin_settings.php's real, enforced platform
    // toggles (see enforce_maintenance_mode() and platform_setting() in
    // includes/functions.php). Single fixed row (id=1) rather than a
    // key/value table - there are exactly four named switches, not an
    // open-ended list, so named columns are simpler to read and to guard
    // with a CHECK on id than a generic settings store would be.
    '2026_09_18_platform_settings' => "
        CREATE TABLE platform_settings (
            id TINYINT UNSIGNED PRIMARY KEY,
            allow_recipe_submissions TINYINT(1) NOT NULL DEFAULT 1,
            enable_ai_matching TINYINT(1) NOT NULL DEFAULT 1,
            show_marketplace TINYINT(1) NOT NULL DEFAULT 1,
            maintenance_mode TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        INSERT INTO platform_settings (id) VALUES (1)
    ",

    // Admin suite, part 2: real "active users" needs *some* signal of
    // recent activity, and nothing in the schema tracked one before this -
    // set from login.php and oauth_login_user() on every successful sign
    // in. NULL for an account that has never logged in since this shipped
    // (including every pre-existing account) rather than backfilled to
    // created_at, which would fabricate an activity signal that never
    // happened.
    '2026_09_18_last_login' => 'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER created_at',

    // Backs admin_analytics.php's real "recipe views" numbers - one row
    // per successful recipe.php page load (not rating/report POSTs). No
    // dedup by user/day: a page-load counter is what "views" conventionally
    // means in this kind of dashboard, and de-duplicating would need a
    // policy decision (per session? per day? per user ever?) that isn't
    // needed just to show honest totals and a top-viewed list.
    '2026_09_18_recipe_views' => "
        CREATE TABLE recipe_views (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recipe_id VARCHAR(40) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_recipe (recipe_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Admin-curated, publishable meal plan templates - distinct from each
    // member's own meal_plan_items (planner.php). day_offset is 0-6
    // relative to whatever start date a member later adopts it on
    // (meal_plan_templates.php), not a fixed calendar date, so one
    // template is reusable indefinitely rather than tied to the week it
    // was authored in.
    '2026_09_18_meal_plan_templates' => "
        CREATE TABLE meal_plan_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            description TEXT NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        CREATE TABLE meal_plan_template_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template_id INT UNSIGNED NOT NULL,
            day_offset TINYINT UNSIGNED NOT NULL,
            meal_type VARCHAR(30) NOT NULL,
            recipe_id VARCHAR(40) NOT NULL,
            FOREIGN KEY (template_id) REFERENCES meal_plan_templates(id) ON DELETE CASCADE,
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
            INDEX idx_template (template_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // CONTINUE.md §2.6 / Step 8: vendors could see net earnings (vendor.php)
    // but nothing tracked whether they'd actually been paid - all money sat
    // in the platform's PayFast account with no payout record at all. One
    // row per payout run, covering everything earned in (period_start,
    // period_end] - see calculate_vendor_owed() in
    // includes/vendor_payouts.php for why a contiguous period range, not a
    // per-sale join table, is enough to never double-count a sale across
    // payout runs. status stays pending/paid (not just a boolean) so a
    // future "record a scheduled payout, confirm later" flow has somewhere
    // to live, even though admin_payouts.php's one action today creates a
    // row already marked paid.
    '2026_09_18_vendor_payouts' => "
        CREATE TABLE vendor_payouts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            period_start DATETIME NULL,
            period_end DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            paid_by_admin_id INT UNSIGNED NULL,
            notes TEXT NULL,
            FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (paid_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_vendor_period (vendor_id, period_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
];
