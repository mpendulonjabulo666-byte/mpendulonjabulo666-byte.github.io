# NutriTale

A small recipe & nutrition app (PHP + MySQL). This is source code only —
it needs a PHP/MySQL host to run (GitHub Pages, which serves this
repository's other pages, cannot execute PHP).

## Deploying

1. Upload the `nutritale/` folder to your PHP host (e.g. via XAMPP locally,
   or your shared-hosting file manager / FTP).
2. Edit `config/config.php` and set `DB_HOST`, `DB_NAME`, `DB_USER`,
   `DB_PASS` to your own database's credentials.
3. Visit `install.php` in the browser. It creates the database/tables (if
   missing) and seeds a starter set of recipes. Safe to re-run any time.
4. Go to `register.php` to create your first account, then browse/search
   recipes and favorite them from `index.php`.

## Structure

- `config/` — app constants and the PDO connection (`db()`)
- `includes/` — shared helpers, inline SVG icons, nav and recipe-card partials
- `sql/schema.sql` — `users`, `recipes` + related tag/ingredient/instruction
  tables, `favorites`
- `data/seed_recipes.php` — starter recipe catalog used by `install.php`
- `register.php` / `login.php` / `logout.php` — auth
- `index.php` — recipe browse/search/filter (protected)
- `recipe.php` — recipe detail with ingredients/instructions
- `favorites.php` / `favorite_toggle.php` — per-user favorites
