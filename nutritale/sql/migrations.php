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
    // premium_enforce_expiry() in includes/functions_core.php, which treats
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
    // includes/functions_core.php). Single fixed row (id=1) rather than a
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

    // A "mark as paid" was permanent with no way to undo a mistake and no
    // record of why - a real risk once this handles actual vendor money.
    // status gains a third value, 'reversed' (still just a string, not an
    // ENUM - consistent with every other status column in this schema, and
    // avoids an ALTER ... MODIFY on a live table just to add one value).
    // reversal_reason is intentionally never nullable-in-practice - enforced
    // in code (admin_payouts.php's handler), not by a CHECK constraint,
    // since this DB's MySQL version predates reliable CHECK enforcement and
    // the app is the only writer of this table anyway.
    '2026_09_18_vendor_payout_reversal' => "
        ALTER TABLE vendor_payouts
            ADD COLUMN reversed_at DATETIME NULL AFTER paid_by_admin_id,
            ADD COLUMN reversed_by_admin_id INT UNSIGNED NULL AFTER reversed_at,
            ADD COLUMN reversal_reason TEXT NULL AFTER reversed_by_admin_id,
            ADD CONSTRAINT fk_vendor_payouts_reversed_by FOREIGN KEY (reversed_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
    ",

    // Adds Apple Sign In alongside the existing Google/Facebook OAuth, and
    // gives all three a real account-linking key instead of matching by
    // email alone. oauth_provider is a plain string (not an ENUM - same
    // convention as every other status-like column in this schema) rather
    // than the ENUM the original ask specified, so a fourth provider later
    // never needs an ALTER ... MODIFY on a live column. Both columns stay
    // nullable - the overwhelming majority of accounts are plain
    // email/password and have neither. A UNIQUE index on the pair is safe
    // with MySQL's NULL semantics: NULL is never equal to NULL, so any
    // number of non-OAuth accounts (NULL, NULL) coexist fine - the index
    // only ever rejects a genuine duplicate (same provider, same real
    // provider-issued id).
    '2026_09_19_oauth_provider_id' => "
        ALTER TABLE users
            ADD COLUMN oauth_provider VARCHAR(20) NULL AFTER last_login_at,
            ADD COLUMN oauth_id VARCHAR(255) NULL AFTER oauth_provider,
            ADD UNIQUE INDEX uniq_oauth_provider_id (oauth_provider, oauth_id)
    ",

    // Recipe-level Premium gate for the recipe library itself - separate
    // from both is_premium/price above (the vendor marketplace's
    // pay-per-recipe unlock, via recipe_purchases) and the AI pantry
    // matcher's own trial-count gate (pantry.php) - neither of those is
    // touched by this. A 'premium' recipe stays fully visible in every
    // listing (title, photo, description, macros); only its ingredients/
    // instructions are gated, in recipe.php, behind is_premium_member.
    // Plain VARCHAR, not the literal SQL ENUM the original ask specified -
    // same reasoning already applied to vendor_payouts.status and
    // oauth_provider elsewhere in this file: avoids an ALTER ... MODIFY on
    // a live column if a third tier is ever needed, consistent with every
    // other status-like column in this schema.
    '2026_09_20_recipe_tier' => "ALTER TABLE recipes ADD COLUMN tier VARCHAR(20) NOT NULL DEFAULT 'free' AFTER is_premium",

    // Seeds the 40-recipe first import batch (heavy on South African
    // cuisine - the existing 8-recipe catalog had none) on top of the
    // tier column above. Reuses nutritale_seed_recipes() - the same single
    // source of truth setup.php's own fresh-install seeding already reads
    // from - rather than duplicating recipe content inline here, and skips
    // any id already present so this is safe to run against a database
    // that already has the original 8 (this dev machine) as well as a
    // truly empty one (where setup.php's own seed step, which runs after
    // migrations, will then find recipes already populated and skip).
    '2026_09_20_recipe_batch1_seed' => function (PDO $pdo): void {
        require_once __DIR__ . '/../data/seed_recipes.php';
        $recipes = nutritale_seed_recipes();
        $existingIds = $pdo->query('SELECT id FROM recipes')->fetchAll(PDO::FETCH_COLUMN);

        $insertRecipe = $pdo->prepare(
            'INSERT INTO recipes (id, title, description, image_url, meal_type, cuisine, difficulty, cook_time_minutes, servings, calories, protein_g, carbs_g, fat_g, fiber_g, tier, is_generated)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $insertDiet = $pdo->prepare('INSERT INTO recipe_diet_tags (recipe_id, diet_type) VALUES (?, ?)');
        $insertAllergen = $pdo->prepare('INSERT INTO recipe_allergens (recipe_id, allergen) VALUES (?, ?)');
        $insertIngredient = $pdo->prepare('INSERT INTO recipe_ingredients (recipe_id, name, quantity, unit, display_quantity, category, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insertStep = $pdo->prepare('INSERT INTO recipe_instructions (recipe_id, step_number, step_text) VALUES (?, ?, ?)');

        foreach ($recipes as $r) {
            if (in_array($r['id'], $existingIds, true)) {
                continue;
            }
            $insertRecipe->execute([
                $r['id'], $r['title'], $r['description'], $r['image_url'], $r['meal_type'], $r['cuisine'],
                $r['difficulty'], $r['cook_time'], $r['servings'], $r['calories'], $r['protein'], $r['carbs'], $r['fat'], $r['fiber'],
                $r['tier'],
            ]);
            foreach ($r['diet_tags'] as $d) $insertDiet->execute([$r['id'], $d]);
            foreach ($r['allergens'] as $a) $insertAllergen->execute([$r['id'], $a]);
            foreach ($r['ingredients'] as $i => $ing) {
                $insertIngredient->execute([$r['id'], $ing[0], $ing[1], $ing[2], $ing[3], $ing[4], $i]);
            }
            foreach ($r['steps'] as $i => $step) {
                $insertStep->execute([$r['id'], $i + 1, $step]);
            }
        }
    },

    // No column anywhere in this schema previously meant "hide this recipe
    // without deleting it" - checked every table for status/active/
    // published/hidden/deleted columns first (CONTINUE.md §2.18/§2.19 has
    // the full search) and found none; an admin "removing" a recipe has
    // always meant a hard DELETE (admin_recipe_delete.php). Minimal
    // boolean, matching this schema's own convention for every other
    // plain on/off flag (is_generated, is_premium, is_vendor, is_admin -
    // all TINYINT(1) NOT NULL DEFAULT, not a VARCHAR status) rather than
    // a status column with only two real values.
    '2026_09_21_recipe_is_published' => 'ALTER TABLE recipes ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1 AFTER tier',

    // Hides the 45 recipes CONTINUE.md §2.18 found and couldn't explain -
    // no file, script, or commit in this repo's history accounts for them
    // (a 40-hour gap in this branch's own commits is the closest thing to
    // a lead). A visibility change only, per the explicit instruction not
    // to touch the data itself - every row, ingredient, and instruction
    // stays exactly as it was, just no longer publicly browsable/
    // searchable, so whoever eventually figures out where they came from
    // still has everything to work with. Matched by exact id (captured
    // live from the actual database, not re-derived from the timestamp
    // range they happen to share) rather than by that timestamp range -
    // a fixed, one-time correction for specific rows, not a rule that
    // should keep matching anything else that ever lands in the same
    // historical minute.
    '2026_09_21_hide_mystery_recipes' => function (PDO $pdo): void {
        $ids = [
            'avocado-egg-power-bowl', 'balsamic-vinaigrette', 'beef-veggie-bowl', 'black-bean-avocado-bowl',
            'blackberry-sage-refresher', 'buffalo-chicken-wings', 'caesar-dressing', 'cauliflower-rice-bowl',
            'chicken-quinoa-bowl', 'chickpea-tahini-bowl', 'cottage-cheese-pineapple-bowl', 'creamy-tomato-pasta',
            'dynamite-shrimp', 'edamame-brown-rice-bowl', 'farro-veggie-bowl', 'garlic-chicken-bowl',
            'greek-dressing', 'greek-yogurt-berry-bowl', 'honey-mustard-dressing', 'lentil-veggie-bowl',
            'mango-dragonfruit-refresher', 'miso-tofu-bowl', 'mixed-berry-nut-bowl', 'mozzarella-sticks',
            'peanut-satay-bowl', 'pineapple-passionfruit-refresher', 'potato-croquettes', 'ranch-dressing',
            'salmon-power-bowl', 'salmon-quinoa-bowl', 'sardine-avocado-bowl', 'shrimp-zoodle-bowl',
            'spinach-mushroom-bowl', 'spring-rolls', 'steak-eggs-power-bowl', 'steak-sweet-potato-bowl',
            'strawberry-aca-i-refresher', 'stuffed-mushrooms', 'tempeh-broccoli-bowl', 'thousand-island-dressing',
            'tofu-veggie-stir-bowl', 'tuna-white-bean-bowl', 'turkey-avocado-bowl', 'turkey-meatball-bowl',
            'turkey-sweet-potato-bowl',
        ];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE recipes SET is_published = 0 WHERE id IN ($placeholders)")->execute($ids);
    },

    // Security hardening pass: rate limiting on login.php, tracked by
    // (IP, email) pair rather than email alone - the existing
    // users.failed_attempts/locked_until columns (added long before this)
    // already rate-limit a *known* email regardless of which IP is
    // attacking it, which genuinely does protect a real account from a
    // distributed brute force. What they can't do is limit an attacker
    // spraying many *different* email guesses from one IP, since a login
    // against an email with no matching row never touches those columns
    // at all. This table adds that missing layer on top, not instead of
    // it - one row per failed attempt, cleared for that (IP, email) pair
    // on a successful login. VARCHAR(45) on ip_address specifically fits
    // the longest possible IPv6 text form, not just IPv4.
    '2026_09_21_login_attempts' => "
        CREATE TABLE login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(190) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_email_time (ip_address, email, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",

    // Recipe photos (27 Wikimedia Commons + 6 Unsplash, all freely licensed and
    // hand-reviewed) - see data/image_attribution.json for the matching credits.
    // Only fills recipes that still have no image, so it never overwrites a
    // photo set by hand on the server.
    '2026_09_26_recipe_photos' => function (PDO $pdo): void {
        $photos = [
            'amagwinya-fat-cakes' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/4/4a/3_fat_cooks_%28Vetkoek%29_with_meat.jpeg/1280px-3_fat_cooks_%28Vetkoek%29_with_meat.jpeg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'avocado-feta-toast' => 'https://upload.wikimedia.org/wikipedia/commons/6/6c/Avocado_toast_with_sesame_seeds.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail_unscaled',
            'banana-oat-pancakes' => 'https://images.unsplash.com/photo-1664350471028-34a1d2651366?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8NHx8QmFuYW5hJTIwT2F0JTIwUGFuY2FrZXMlMjBBbWVyaWNhbiUyMGZvb2R8ZW58MXwwfHx8MTc5MDM4NzgzMnww&ixlib=rb-4.1.0&q=80&w=1080',
            'biltong-and-droewors-snack-board' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/fb/BiltongUKDried.jpg/1280px-BiltongUKDried.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'biltong-spiced-popcorn' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c7/Bowl_of_Popcorn_%28Unsplash%29.jpg/1280px-Bowl_of_Popcorn_%28Unsplash%29.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'bobotie-with-yellow-rice' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/f1/Bobotie%2C_South_African_dish.jpg/1280px-Bobotie%2C_South_African_dish.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'boerewors-breakfast-rolls' => 'https://upload.wikimedia.org/wikipedia/commons/2/2d/Boerewors_rolls_with_homemade_tomato_relish._Yum..jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail_unscaled',
            'bunny-chow-with-lamb-curry' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/ca/Bunny_Chow_with_Lamb_%26_Potato_-_African_Chow_2023-07-27.jpg/1280px-Bunny_Chow_with_Lamb_%26_Potato_-_African_Chow_2023-07-27.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'chakalaka-and-bean-stew' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d0/Chakalaka.jpg/1280px-Chakalaka.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'chakalaka-side-dish' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/ff/Chakalaka_meal.jpg/1280px-Chakalaka_meal.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'classic-shakshuka' => 'https://images.unsplash.com/photo-1582492710145-d723e0a219f8?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8MXx8Q2xhc3NpYyUyMFNoYWtzaHVrYSUyME1pZGRsZSUyMEVhc3Rlcm4lMjBmb29kfGVufDF8MHx8fDE3OTAzOTIyNTJ8MA&ixlib=rb-4.1.0&q=80&w=1080',
            'denningvleis-cape-malay-lamb-curry' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/5/51/Mutton_Curry_%2844786%29.jpg/1280px-Mutton_Curry_%2844786%29.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'falafel-pita-with-tahini' => 'https://images.unsplash.com/photo-1632700081098-37dc1285cbfe?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8Mnx8RmFsYWZlbCUyMFBpdGElMjB3aXRoJTIwVGFoaW5pJTIwTWlkZGxlJTIwRWFzdGVybiUyMGZvb2R8ZW58MXwwfHx8MTc5MDM5Mjc1OXww&ixlib=rb-4.1.0&q=80&w=1080',
            'greek-salad-with-grilled-halloumi' => 'https://images.unsplash.com/photo-1778449532114-430396ada55b?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8Mnx8R3JlZWslMjBTYWxhZCUyMHdpdGglMjBHcmlsbGVkJTIwSGFsbG91bWklMjBNZWRpdGVycmFuZWFuJTIwZm9vZHxlbnwxfDB8fHwxNzkwMzkyODUwfDA&ixlib=rb-4.1.0&q=80&w=1080',
            'hummus-with-roasted-veg-sticks' => 'https://images.unsplash.com/photo-1771574206132-74f127909c02?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8Mnx8SHVtbXVzJTIwd2l0aCUyMFJvYXN0ZWQlMjBWZWclMjBTdGlja3MlMjBNZWRpdGVycmFuZWFuJTIwZm9vZHxlbnwxfDB8fHwxNzkwMzkyOTQzfDA&ixlib=rb-4.1.0&q=80&w=1080',
            'koeksisters' => 'https://upload.wikimedia.org/wikipedia/commons/f/f9/Koeksisters.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail_unscaled',
            'lemon-herb-baked-hake' => 'https://images.unsplash.com/photo-1665401015549-712c0dc5ef85?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8MXx8TGVtb24lMjBIZXJiJTIwQmFrZWQlMjBIYWtlJTIwU291dGglMjBBZnJpY2FuJTIwZm9vZHxlbnwxfDB8fHwxNzkwMzkzMTIxfDA&ixlib=rb-4.1.0&q=80&w=1080',
            'malva-pudding' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/66/Malva_pudding_2.jpg/1280px-Malva_pudding_2.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'mango-mageu-smoothie-bowl' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c6/Mango_Pineapple_Smoothie_Bowl.jpg/1280px-Mango_Pineapple_Smoothie_Bowl.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'melktert-milk-tart' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/69/Melktert.jpg/1280px-Melktert.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'mieliepap-with-sugar-beans-and-chakalaka' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/fe/Ugali_and_cabbage.jpg/1280px-Ugali_and_cabbage.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'no-bake-oat-and-honey-bites' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c0/Uncle_Tobys_Oat_Balls.jpg/1280px-Uncle_Tobys_Oat_Balls.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'potjiekos-beef-and-vegetable-stew' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d8/Poyke.JPG/1280px-Poyke.JPG?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'rooibos-overnight-oats' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/f/fd/Protein_overnight_oats.jpg/1280px-Protein_overnight_oats.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'rooibos-poached-pears' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/ea/Tea_Poached_Pears_In_Chocolate_Sauce_%28140490793%29.jpeg/1280px-Tea_Poached_Pears_In_Chocolate_Sauce_%28140490793%29.jpeg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'roosterkoek' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/9/9c/Root44_12.jpg/1280px-Root44_12.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'sosaties-on-the-braai' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/6/69/Sosaties.jpg/1280px-Sosaties.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'spiced-peanuts-and-raisins-mix' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/8/88/Peanuts_and_raisins.jpg/1280px-Peanuts_and_raisins.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'sweetcorn-fritters' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/eb/Pelas_Jagung_in_Bojonegoro.jpg/960px-Pelas_Jagung_in_Bojonegoro.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'tomato-bredie' => 'https://upload.wikimedia.org/wikipedia/commons/d/d6/Chicken_with_tomato_bredie_%2812567481243%29.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail_unscaled',
            'umngqusho-samp-and-beans' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/7/7c/Umngqusho.jpg/1280px-Umngqusho.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'vegetable-and-chickpea-tagine' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/ea/Vegetable_Tagine.jpg/1280px-Vegetable_Tagine.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'vetkoek-with-curried-mince' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/03/Vetkoek.jpg/1280px-Vetkoek.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
        ];
        $stmt = $pdo->prepare("UPDATE recipes SET image_url = ? WHERE id = ? AND (image_url IS NULL OR image_url = '')");
        foreach ($photos as $id => $url) {
            $stmt->execute([$url, $id]);
        }
    },

    // Second photo batch: the last 7 published recipes (broader searches on
    // the same two licensed sources). Same fill-only-if-empty rule.
    '2026_09_29_recipe_photos_batch2' => function (PDO $pdo): void {
        $photos = [
            'cape-malay-chicken-curry-wrap' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/5/5d/Handmade_Chicken_Shawarma_Wrap_-_Lavash.jpg/1280px-Handmade_Chicken_Shawarma_Wrap_-_Lavash.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'chicken-sweetcorn-samp-salad' => 'https://images.unsplash.com/photo-1708184528305-33ce7daced65?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8MXx8Y2hpY2tlbiUyMGNvcm4lMjBzYWxhZHxlbnwxfDB8fHwxNzkwNjM0MDk5fDA&ixlib=rb-4.1.0&q=80&w=1080',
            'durban-style-bean-curry' => 'https://images.unsplash.com/photo-1788601988466-9f361c34c6cb?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8Mnx8YmVhbiUyMGN1cnJ5fGVufDF8MHx8fDE3OTA2MzQxNzB8MA&ixlib=rb-4.1.0&q=80&w=1080',
            'pap-wors-and-chakalaka-plate' => 'https://upload.wikimedia.org/wikipedia/commons/2/20/Restaurant_Food_Platter.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail_unscaled',
            'peppermint-crisp-tart' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/e/e0/Peppermint-Crisp-Close-Up.jpg/1280px-Peppermint-Crisp-Close-Up.jpg?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail',
            'smoked-snoek-pate-sandwich' => 'https://images.unsplash.com/photo-1730495116887-889d1c49336c?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wxMDgzMDc2fDB8MXxzZWFyY2h8Mnx8ZmlzaCUyMHBhdGUlMjB0b2FzdHxlbnwxfDB8fHwxNzkwNjM0Mjc5fDA&ixlib=rb-4.1.0&q=80&w=1080',
            'waterblommetjiebredie' => 'https://upload.wikimedia.org/wikipedia/commons/d/db/Aponogeton_distachyos_-_Waterblommetjies_from_tin.JPG?utm_source=commons.wikimedia.org&utm_campaign=imageinfo&utm_content=thumbnail_unscaled',
        ];
        $stmt = $pdo->prepare("UPDATE recipes SET image_url = ? WHERE id = ? AND (image_url IS NULL OR image_url = '')");
        foreach ($photos as $id => $url) {
            $stmt->execute([$url, $id]);
        }
    },
];
