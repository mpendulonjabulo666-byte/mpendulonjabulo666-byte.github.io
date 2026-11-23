<?php
// Loads data/draft_recipes/batch*.php (the 45 recipes written to replace the
// empty hidden catalogue rows) and writes them to the database as HIDDEN
// drafts. Called once by the 2026_11_13_fill_draft_recipes migration.
//
// Safety rules (each one is tested in tests/draft_recipes_test.php):
//  - Never publishes anything and never changes is_published on an existing row.
//  - A recipe that already has BOTH ingredients and steps is left alone.
//  - Existing title, image, price, tier and nutrition numbers are kept.
//  - Allergens are derived from the ingredient names with the app's own
//    allergen scanner, not typed by hand, so a tag cannot be forgotten.
require_once __DIR__ . '/allergens.php';

/** @return array<int, array<string, mixed>> */
function nutritale_draft_recipes(): array
{
    $out = [];
    foreach (glob(__DIR__ . '/../data/draft_recipes/batch*.php') ?: [] as $file) {
        foreach (require $file as $r) {
            $order = 0;
            $r['ingredients'] = array_map(function (string $line) use (&$order): array {
                [$name, $qty, $unit, $display, $category] = explode('|', $line);
                return ['name' => $name, 'quantity' => (float)$qty, 'unit' => $unit, 'display' => $display, 'category' => $category, 'order' => ++$order];
            }, $r['ingredients']);
            $r['allergens'] = nutritale_draft_allergens($r['ingredients']);
            $out[$r['id']] = $r;
        }
    }
    ksort($out);
    return array_values($out);
}

/** @param array<int, array<string, mixed>> $ingredients @return string[] */
function nutritale_draft_allergens(array $ingredients): array
{
    $text = implode("\n", array_column($ingredients, 'name'));
    $hits = text_allergen_hits($text, array_keys(allergen_keywords()));
    $hits = array_values(array_unique(array_map('strval', $hits)));
    sort($hits);
    return $hits;
}

/** @return array{inserted: string[], filled: string[], skipped: string[]} */
function nutritale_apply_draft_recipes(PDO $pdo): array
{
    $res = ['inserted' => [], 'filled' => [], 'skipped' => []];
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $exists = $pdo->prepare('SELECT (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = r.id) AS ing,
                                        (SELECT COUNT(*) FROM recipe_instructions WHERE recipe_id = r.id) AS stp
                                   FROM recipes r WHERE r.id = ?');
        $insert = $pdo->prepare('INSERT INTO recipes (id, title, description, meal_type, cuisine, difficulty, cook_time_minutes, servings, is_generated, tier, is_published)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, \'free\', 0)');
        $update = $pdo->prepare('UPDATE recipes SET description = ?, meal_type = ?, cuisine = ?, difficulty = ?, cook_time_minutes = ?, servings = ? WHERE id = ?');
        $clear = [];
        foreach (['recipe_ingredients', 'recipe_instructions', 'recipe_diet_tags', 'recipe_allergens'] as $t) {
            $clear[$t] = $pdo->prepare("DELETE FROM $t WHERE recipe_id = ?");
        }
        $addIng = $pdo->prepare('INSERT INTO recipe_ingredients (recipe_id, name, quantity, unit, display_quantity, category, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $addStep = $pdo->prepare('INSERT INTO recipe_instructions (recipe_id, step_number, step_text) VALUES (?, ?, ?)');
        $addDiet = $pdo->prepare('INSERT INTO recipe_diet_tags (recipe_id, diet_type) VALUES (?, ?)');
        $addAlg = $pdo->prepare('INSERT INTO recipe_allergens (recipe_id, allergen) VALUES (?, ?)');

        foreach (nutritale_draft_recipes() as $r) {
            $id = $r['id'];
            $exists->execute([$id]);
            $row = $exists->fetch(PDO::FETCH_ASSOC);
            if ($row && (int)$row['ing'] > 0 && (int)$row['stp'] > 0) {
                $res['skipped'][] = $id;
                continue;
            }
            $meta = [$r['description'], $r['meal_type'], $r['cuisine'], $r['difficulty'], $r['cook_time'], $r['servings']];
            if ($row) {
                $update->execute([...$meta, $id]);
                $res['filled'][] = $id;
            } else {
                $insert->execute([$id, $r['title'], ...$meta]);
                $res['inserted'][] = $id;
            }
            foreach ($clear as $stmt) {
                $stmt->execute([$id]);
            }
            foreach ($r['ingredients'] as $i) {
                $addIng->execute([$id, $i['name'], $i['quantity'], $i['unit'], $i['display'], $i['category'], $i['order']]);
            }
            foreach ($r['steps'] as $n => $text) {
                $addStep->execute([$id, $n + 1, $text]);
            }
            foreach ($r['diet_tags'] as $tag) {
                $addDiet->execute([$id, $tag]);
            }
            foreach ($r['allergens'] as $alg) {
                $addAlg->execute([$id, $alg]);
            }
        }
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $res;
}
