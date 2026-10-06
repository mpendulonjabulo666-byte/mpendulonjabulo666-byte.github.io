<?php
/** @var array $user  @var string $navCurrent  Included by includes/nav.php.
 *
 * The first-visit guided tour: a short walk through what each part of the
 * app is for, spotlighting the real tab (phone) or sidebar link (desktop)
 * as it explains it. Starts by itself on a new account's first page in
 * the app (user_needs_tour()); anyone can replay it with ?tour=1 (linked
 * from the profile page). The steps live here rather than in the script
 * so they can use the person's name and what their plan includes;
 * assets/js/app-tour.js does the showing.
 *
 * Each step: title/text, optional *Desktop variants, and the element to
 * spotlight on a phone and on desktop (null = a centred card). "only"
 * limits a step to one layout - the planner has its own sidebar link on
 * desktop but lives under Profile on a phone. */
$tourName = trim((string)strtok((string)$user['name'], ' ')) ?: 'there';
$tourSteps = [
    [
        'title' => 'Welcome to NutriTale, ' . $tourName . '!',
        'text' => "Here's a quick look at what each part of the app does. It takes about 30 seconds.",
        'next' => 'Show me',
    ],
    [
        'title' => 'Recipes',
        'text' => 'Every recipe in one place. Search by name or ingredient, filter by meal and diet, and open one to see its ingredients, method and nutrition.',
        'phone' => '.app-tabbar a[href="index.php"]',
        'desktop' => '.app-nav-links a[href="index.php"]',
    ],
    [
        'title' => 'What can I make?',
        'text' => "Tell NutriTale what's in your fridge and cupboards and it shows the recipes you can cook with it. Add expiry dates and it reminds you to use things up."
            . (user_has_full_library($user) ? ' You can scan barcodes to add food faster, too.' : ' Premium adds barcode scanning.'),
        'phone' => '.app-tabbar a[href="pantry.php"]',
        'desktop' => '.app-nav-links a[href="pantry.php"]',
    ],
    [
        'title' => 'Add your own recipes',
        'text' => 'Tap + to save a recipe of your own, with its ingredients, steps and nutrition. Everything you add is kept under My Recipes.',
        'titleDesktop' => 'My Recipes',
        'textDesktop' => 'Save recipes of your own, with their ingredients, steps and nutrition, and find everything you have added here.',
        'phone' => '.app-tabbar-fab',
        'desktop' => '.app-nav-links a[href="my_recipes.php"]',
    ],
    [
        'title' => 'Favorites',
        'text' => 'Tap the heart on any recipe to keep it here, so your go-to meals are always one tap away.',
        'phone' => '.app-tabbar a[href="favorites.php"]',
        'desktop' => '.app-nav-links a[href="favorites.php"]',
    ],
    [
        'title' => 'Meal Planner',
        'text' => 'Plan breakfast, lunch, dinner and snacks for the week, then turn the whole plan into a shopping list in one tap.',
        'desktop' => '.app-nav-links a[href="planner.php"]',
        'only' => 'desktop',
    ],
    [
        'title' => 'Your profile',
        'text' => 'Your photo and your diet and allergy settings - recipes that contain your allergens get a warning. The Meal Planner, Shopping list, My Recipes and dark mode are in here too.',
        'textDesktop' => 'Your photo and your diet and allergy settings - recipes that contain your allergens get a warning everywhere in the app.',
        'phone' => '.app-tabbar a[href$="profile.php"]',
        'desktop' => '.app-nav-identity-link',
    ],
    [
        'title' => "You're all set",
        'text' => 'You can take this tour again any time from your profile.',
        'next' => 'Start exploring',
    ],
];
$tourConfig = [
    'steps' => $tourSteps,
    'csrf' => csrf_token(),
    'record' => empty($_GET['tour']), // a replay is already recorded
];
?>
<script type="application/json" id="app-tour-data"><?= json_encode($tourConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/js/app-tour.js?v=24" defer></script>
