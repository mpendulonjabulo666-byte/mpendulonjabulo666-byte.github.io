<?php
// Admin-only, one button: writes the 45 hidden draft recipes (ingredients,
// steps, diet tags, derived allergens) from data/draft_recipes/. Nothing is
// published. Safe to press twice: recipes that already have ingredients and
// steps are skipped. Done as a button rather than an automatic migration so
// the work never runs inside a visitor's first page load on the free host.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/draft_recipes.php';

$user = require_admin();
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        http_response_code(400);
        exit('Bad request.');
    }
    @set_time_limit(120);
    $result = nutritale_apply_draft_recipes(db());
}
$total = count(nutritale_draft_recipes());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fill draft recipes · <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/css/style.css?v=43">
<style>body{max-width:640px;margin:40px auto;padding:0 16px;font-family:sans-serif}.box{border:1px solid #ccc;border-radius:8px;padding:16px;margin-bottom:16px}</style>
</head>
<body>
<h1>Fill draft recipes</h1>
<div class="box">
  <p><?= (int)$total ?> hidden draft recipes are ready to write. They stay hidden until you publish each one in Admin → Recipes.</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <button type="submit" class="btn btn-primary">Write the draft recipes</button>
  </form>
</div>
<?php if ($result): ?>
<div class="box">
  <p><strong>Done.</strong> Added: <?= count($result['inserted']) ?> · Filled: <?= count($result['filled']) ?> · Skipped (already complete): <?= count($result['skipped']) ?></p>
  <?php if ($result['skipped']): ?><p>Skipped: <?= h(implode(', ', $result['skipped'])) ?></p><?php endif; ?>
  <p>All remain hidden.</p>
</div>
<?php endif; ?>
<p><a href="admin.php">Back to Admin</a></p>
</body>
</html>
