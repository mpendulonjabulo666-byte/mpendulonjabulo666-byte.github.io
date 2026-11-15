<?php
// Draft recipes, batch 4 of 5. See batch1.php for the format.
return [
    [
        'id' => 'greek-dressing', 'title' => 'Greek Salad Dressing',
        'description' => 'A bright lemon, oregano and garlic dressing for tomato, cucumber and feta salads. Shake it in a jar.',
        'meal_type' => 'snack', 'cuisine' => 'Greek', 'difficulty' => 'easy', 'cook_time' => 5, 'servings' => 6,
        'diet_tags' => ['vegan', 'vegetarian', 'gluten-free'],
        'ingredients' => [
            'Extra virgin olive oil|6|tbsp|6 tbsp|pantry', 'Red wine vinegar|2|tbsp|2 tbsp|pantry', 'Lemon juice|1|tbsp|1 tbsp|pantry',
            'Dried oregano|1|tsp|1 tsp|pantry', 'Garlic|1|cloves|1 clove|produce', 'Salt|0.25|tsp|1/4 tsp|pantry',
            'Black pepper|0.25|tsp|1/4 tsp|pantry',
        ],
        'steps' => [
            'Crush the garlic finely.',
            'Put all the ingredients in a jar, seal and shake for 20 seconds.',
            'Taste, adjust the salt or lemon, and shake again before using. Keeps for a week in the fridge.',
        ],
    ],
    [
        'id' => 'honey-mustard-dressing', 'title' => 'Honey Mustard Dressing',
        'description' => 'Sweet, tangy and creamy. Good on salads, grilled chicken and as a dip for roasted vegetables.',
        'meal_type' => 'snack', 'cuisine' => 'American', 'difficulty' => 'easy', 'cook_time' => 5, 'servings' => 6,
        'diet_tags' => ['vegetarian', 'gluten-free'],
        'ingredients' => [
            'Dijon mustard|2|tbsp|2 tbsp|pantry', 'Honey|2|tbsp|2 tbsp|pantry', 'Mayonnaise|3|tbsp|3 tbsp|pantry',
            'Apple cider vinegar|1|tbsp|1 tbsp|pantry', 'Olive oil|1|tbsp|1 tbsp|pantry', 'Salt|0.25|tsp|1/4 tsp|pantry',
        ],
        'steps' => [
            'Whisk the mustard, honey, mayonnaise and vinegar until smooth.',
            'Whisk in the olive oil and season with salt.',
            'Loosen with a teaspoon of water if you want it pourable. Chill for 20 minutes and use within 5 days.',
        ],
    ],
    [
        'id' => 'ranch-dressing', 'title' => 'Homemade Ranch Dressing',
        'description' => 'Cool, herby ranch made with buttermilk and mayonnaise. A dip for veggies, wings and crisps, or a salad dressing.',
        'meal_type' => 'snack', 'cuisine' => 'American', 'difficulty' => 'easy', 'cook_time' => 10, 'servings' => 8,
        'diet_tags' => ['vegetarian', 'gluten-free'],
        'ingredients' => [
            'Mayonnaise|0.5|cup|1/2 cup|pantry', 'Buttermilk|0.25|cup|1/4 cup|dairy', 'Plain yoghurt|0.25|cup|1/4 cup|dairy',
            'Garlic powder|0.5|tsp|1/2 tsp|pantry', 'Onion powder|0.5|tsp|1/2 tsp|pantry', 'Dried dill|1|tsp|1 tsp|pantry',
            'Fresh chives|1|tbsp|1 tbsp|produce', 'Lemon juice|1|tsp|1 tsp|pantry', 'Salt|0.25|tsp|1/4 tsp|pantry',
        ],
        'steps' => [
            'Whisk the mayonnaise, buttermilk and yoghurt together until smooth.',
            'Stir in the garlic powder, onion powder, dill, chopped chives, lemon juice and salt.',
            'Chill for at least 30 minutes so the flavours develop. Thin with a little more buttermilk if needed. Keeps for 5 days.',
        ],
    ],
    [
        'id' => 'thousand-island-dressing', 'title' => 'Thousand Island Dressing',
        'description' => 'Creamy, slightly sweet pink dressing with chopped pickles. Classic on burgers, wraps and crisp lettuce.',
        'meal_type' => 'snack', 'cuisine' => 'American', 'difficulty' => 'easy', 'cook_time' => 5, 'servings' => 8,
        'diet_tags' => ['vegetarian', 'gluten-free'],
        'ingredients' => [
            'Mayonnaise|0.5|cup|1/2 cup|pantry', 'Tomato ketchup|3|tbsp|3 tbsp|pantry', 'Sweet pickle relish or chopped gherkins|2|tbsp|2 tbsp|pantry',
            'Lemon juice|1|tsp|1 tsp|pantry', 'Onion powder|0.5|tsp|1/2 tsp|pantry', 'Paprika|0.25|tsp|1/4 tsp|pantry',
        ],
        'steps' => [
            'Mix the mayonnaise and ketchup until evenly pink.',
            'Stir in the relish, lemon juice, onion powder and paprika.',
            'Chill for 15 minutes before serving. Keeps for 5 days in the fridge.',
        ],
    ],
    [
        'id' => 'buffalo-chicken-wings', 'title' => 'Baked Buffalo Chicken Wings',
        'description' => 'Crispy oven-baked wings tossed in tangy, buttery buffalo sauce. Serve with celery and a cool dip.',
        'meal_type' => 'snack', 'cuisine' => 'American', 'difficulty' => 'medium', 'cook_time' => 55, 'servings' => 4,
        'diet_tags' => ['gluten-free', 'high-protein'],
        'ingredients' => [
            'Chicken wings|1|kg|1 kg|protein', 'Baking powder (aluminium-free)|1|tbsp|1 tbsp|pantry', 'Salt|1|tsp|1 tsp|pantry',
            'Black pepper|0.5|tsp|1/2 tsp|pantry', 'Hot sauce|0.33|cup|1/3 cup|pantry', 'Butter|3|tbsp|3 tbsp|dairy',
            'Celery sticks|4|pieces|4 sticks|produce',
        ],
        'steps' => [
            'Heat the oven to 230 C and put a wire rack on a baking tray. Pat the wings completely dry.',
            'Toss the wings with the baking powder, salt and pepper until lightly coated.',
            'Arrange the wings on the rack without touching and bake for 45 to 50 minutes, turning once halfway, until deep golden and crisp.',
            'Melt the butter and stir in the hot sauce.',
            'Toss the hot wings in the sauce and serve at once with celery sticks.',
        ],
    ],
    [
        'id' => 'dynamite-shrimp', 'title' => 'Dynamite Shrimp',
        'description' => 'Crispy shrimp in a creamy, spicy sweet-chilli sauce. A party starter or a rice-bowl topping.',
        'meal_type' => 'snack', 'cuisine' => 'Asian', 'difficulty' => 'medium', 'cook_time' => 25, 'servings' => 4,
        'diet_tags' => ['gluten-free', 'high-protein'],
        'ingredients' => [
            'Peeled raw shrimp|400|g|400 g|protein', 'Cornflour|0.5|cup|1/2 cup|pantry', 'Egg white|1|pieces|1|dairy',
            'Vegetable oil|0.5|cup|1/2 cup (for frying)|pantry', 'Mayonnaise|4|tbsp|4 tbsp|pantry', 'Sweet chilli sauce|3|tbsp|3 tbsp|pantry',
            'Sriracha or chilli sauce|1|tsp|1 tsp|pantry', 'Spring onion|1|pieces|1|produce', 'Salt|0.5|tsp|1/2 tsp|pantry',
        ],
        'steps' => [
            'Pat the shrimp dry, then toss with the salt and the egg white. Coat each piece lightly in cornflour.',
            'Heat the oil in a large pan over medium-high heat. Fry the shrimp in batches for 2 minutes per side until crisp and golden. Drain on paper towels.',
            'Stir the mayonnaise, sweet chilli sauce and sriracha together in a large bowl.',
            'Add the hot shrimp and toss to coat.',
            'Scatter with sliced spring onion and serve immediately.',
        ],
    ],
    [
        'id' => 'mozzarella-sticks', 'title' => 'Baked Mozzarella Sticks',
        'description' => 'Crunchy golden coating around gooey melted mozzarella. Baked, not deep-fried.',
        'meal_type' => 'snack', 'cuisine' => 'American', 'difficulty' => 'medium', 'cook_time' => 30, 'servings' => 4,
        'diet_tags' => ['vegetarian'],
        'ingredients' => [
            'Mozzarella string cheese or block|250|g|250 g|dairy', 'Plain flour|0.33|cup|1/3 cup|grains', 'Eggs|2|pieces|2|dairy',
            'Breadcrumbs|1|cup|1 cup|grains', 'Grated parmesan|2|tbsp|2 tbsp|dairy', 'Dried oregano|1|tsp|1 tsp|pantry',
            'Olive oil spray|1|tsp|a light spray|pantry', 'Marinara or tomato sauce, for dipping|0.5|cup|1/2 cup|pantry',
        ],
        'steps' => [
            'Cut the mozzarella into sticks about 8 cm long and freeze for 30 minutes so they hold their shape.',
            'Set up three plates: flour; beaten eggs; breadcrumbs mixed with the parmesan and oregano.',
            'Coat each stick in flour, then egg, then breadcrumbs. Dip again in egg and breadcrumbs for a thicker crust.',
            'Heat the oven to 220 C. Place the sticks on a lined tray, spray lightly with oil and bake for 8 to 10 minutes, turning once, until golden.',
            'Rest for 2 minutes and serve with warm tomato sauce.',
        ],
    ],
    [
        'id' => 'potato-croquettes', 'title' => 'Crispy Potato Croquettes',
        'description' => 'Fluffy mashed potato with parmesan and chives, crumbed and baked until crisp outside and soft inside.',
        'meal_type' => 'snack', 'cuisine' => 'International', 'difficulty' => 'medium', 'cook_time' => 50, 'servings' => 4,
        'diet_tags' => ['vegetarian'],
        'ingredients' => [
            'Floury potatoes|600|g|600 g|produce', 'Butter|1|tbsp|1 tbsp|dairy', 'Grated parmesan|3|tbsp|3 tbsp|dairy',
            'Fresh chives|1|tbsp|1 tbsp|produce', 'Eggs|2|pieces|2|dairy', 'Plain flour|0.25|cup|1/4 cup|grains',
            'Breadcrumbs|1|cup|1 cup|grains', 'Salt|0.5|tsp|1/2 tsp|pantry', 'Olive oil|1|tbsp|1 tbsp|pantry',
        ],
        'steps' => [
            'Peel and chop the potatoes, boil in salted water for 15 to 20 minutes until soft, then drain very well and let the steam escape.',
            'Mash with the butter until smooth. Stir in the parmesan, chives, salt and one egg yolk (save the white). Chill for 30 minutes.',
            'Shape the mixture into 12 logs. Roll each in flour, then in the remaining beaten egg plus the saved white, then in breadcrumbs.',
            'Heat the oven to 220 C. Place on an oiled tray, brush lightly with oil and bake for 20 minutes, turning once, until deep golden.',
        ],
    ],
    [
        'id' => 'spring-rolls', 'title' => 'Vegetable Spring Rolls',
        'description' => 'Crisp wrappers filled with cabbage, carrot and glass noodles. Bake or air-fry for a lighter version.',
        'meal_type' => 'snack', 'cuisine' => 'Asian', 'difficulty' => 'medium', 'cook_time' => 45, 'servings' => 4,
        'diet_tags' => ['vegan', 'vegetarian'],
        'ingredients' => [
            'Spring roll wrappers|12|pieces|12 sheets|grains', 'Cabbage, finely shredded|2|cup|2 cups|produce', 'Carrots, grated|2|pieces|2 medium|produce',
            'Glass noodles|50|g|50 g|grains', 'Spring onions|3|pieces|3|produce', 'Garlic|2|cloves|2 cloves|produce',
            'Soy sauce|1|tbsp|1 tbsp|pantry', 'Fresh ginger, grated|1|tsp|1 tsp|produce', 'Vegetable oil|2|tbsp|2 tbsp|pantry',
        ],
        'steps' => [
            'Soak the glass noodles in hot water for 5 minutes, drain and cut into short lengths.',
            'Heat 1 tbsp of the oil in a pan and stir-fry the garlic and ginger for 30 seconds. Add the cabbage and carrot and cook for 3 minutes.',
            'Stir in the noodles, soy sauce and spring onions. Cool the filling completely.',
            'Place 2 tbsp of filling near a corner of each wrapper, fold in the sides and roll up tightly. Seal the edge with a little water.',
            'Heat the oven to 220 C, place the rolls on a tray, brush with the remaining oil and bake for 15 to 20 minutes, turning once, until golden.',
        ],
    ],
    [
        'id' => 'stuffed-mushrooms', 'title' => 'Cheesy Spinach Stuffed Mushrooms',
        'description' => 'Button mushroom caps filled with cream cheese, spinach, garlic and parmesan, baked until golden.',
        'meal_type' => 'snack', 'cuisine' => 'International', 'difficulty' => 'easy', 'cook_time' => 30, 'servings' => 4,
        'diet_tags' => ['vegetarian', 'gluten-free'],
        'ingredients' => [
            'Large button mushrooms|16|pieces|16|produce', 'Cream cheese|100|g|100 g|dairy', 'Baby spinach, chopped|1|cup|1 cup|produce',
            'Grated parmesan|3|tbsp|3 tbsp|dairy', 'Garlic|2|cloves|2 cloves|produce', 'Olive oil|1|tbsp|1 tbsp|pantry',
            'Black pepper|0.25|tsp|1/4 tsp|pantry',
        ],
        'steps' => [
            'Heat the oven to 200 C. Wipe the mushrooms clean and pull out the stalks. Chop the stalks finely.',
            'Heat the oil and cook the chopped stalks and garlic for 3 minutes. Add the spinach until wilted, then cool slightly.',
            'Mix with the cream cheese, half the parmesan and the pepper.',
            'Spoon the filling into the caps, place on a tray and sprinkle with the remaining parmesan.',
            'Bake for 18 to 20 minutes until the mushrooms are tender and the tops are golden.',
        ],
    ],
];
