-- NutriTale schema

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
    id VARCHAR(40) PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    image_url VARCHAR(500),
    meal_type VARCHAR(30) NOT NULL,
    cuisine VARCHAR(60),
    difficulty VARCHAR(20) NOT NULL,
    cook_time_minutes SMALLINT UNSIGNED NOT NULL,
    servings SMALLINT UNSIGNED NOT NULL,
    calories SMALLINT UNSIGNED,
    protein_g SMALLINT UNSIGNED,
    carbs_g SMALLINT UNSIGNED,
    fat_g SMALLINT UNSIGNED,
    fiber_g SMALLINT UNSIGNED,
    is_generated TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_meal_type (meal_type),
    INDEX idx_cuisine (cuisine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_diet_tags (
    recipe_id VARCHAR(40) NOT NULL,
    diet_type VARCHAR(40) NOT NULL,
    PRIMARY KEY (recipe_id, diet_type),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_allergens (
    recipe_id VARCHAR(40) NOT NULL,
    allergen VARCHAR(40) NOT NULL,
    PRIMARY KEY (recipe_id, allergen),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    quantity DECIMAL(8,2) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL DEFAULT '',
    display_quantity VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'other',
    order_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_recipe_order (recipe_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_instructions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id VARCHAR(40) NOT NULL,
    step_number SMALLINT UNSIGNED NOT NULL,
    step_text TEXT NOT NULL,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_recipe_step (recipe_id, step_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
    user_id INT UNSIGNED NOT NULL,
    recipe_id VARCHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, recipe_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meal_plan_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_date DATE NOT NULL,
    meal_type VARCHAR(30) NOT NULL,
    recipe_id VARCHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, plan_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopping_list_checks (
    user_id INT UNSIGNED NOT NULL,
    item_key VARCHAR(200) NOT NULL,
    checked TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, item_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_diet_preferences (
    user_id INT UNSIGNED NOT NULL,
    diet_type VARCHAR(40) NOT NULL,
    PRIMARY KEY (user_id, diet_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_allergens (
    user_id INT UNSIGNED NOT NULL,
    allergen VARCHAR(40) NOT NULL,
    PRIMARY KEY (user_id, allergen),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
