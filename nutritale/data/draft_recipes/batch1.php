<?php
// Draft recipes, batch 1 of 5. Written fresh (own words) for the 45 hidden
// catalogue rows that had no steps. Ingredient lines: "Name|quantity|unit|display|category".
// Allergens are derived from the ingredient names by the loader, not typed here.
// Nutrition is left empty on purpose: it is calculated later from FoodData Central.
return [
    [
        'id' => 'avocado-egg-power-bowl', 'title' => 'Avocado Egg Power Bowl',
        'description' => 'Warm quinoa topped with soft-boiled eggs, creamy avocado, baby spinach and cherry tomatoes. A filling breakfast in 15 minutes.',
        'meal_type' => 'breakfast', 'cuisine' => 'International', 'difficulty' => 'easy', 'cook_time' => 15, 'servings' => 1,
        'diet_tags' => ['vegetarian', 'gluten-free', 'high-protein'],
        'ingredients' => [
            'Cooked quinoa|1|cup|1 cup|grains', 'Eggs|2|pieces|2|dairy', 'Ripe avocado|1|pieces|1|produce',
            'Baby spinach|1|cup|1 cup|produce', 'Cherry tomatoes|100|g|100 g|produce', 'Olive oil|1|tsp|1 tsp|pantry',
            'Lemon juice|1|tsp|1 tsp|pantry', 'Salt|0.25|tsp|1/4 tsp|pantry', 'Black pepper|0.25|tsp|1/4 tsp|pantry',
        ],
        'steps' => [
            'Lower the eggs into a pot of boiling water and cook for 6 to 7 minutes for a soft yolk. Move them to cold water, then peel.',
            'Warm the cooked quinoa in a pan or microwave until hot, and spoon it into a bowl.',
            'Toss the spinach with the olive oil, lemon juice and a pinch of the salt, and lay it over the quinoa.',
            'Halve the cherry tomatoes, slice the avocado, and arrange both on the bowl.',
            'Halve the eggs, place them on top, and season with the remaining salt and the pepper.',
        ],
    ],
    [
        'id' => 'beef-veggie-bowl', 'title' => 'Beef and Veggie Bowl',
        'description' => 'Smoky seared beef strips over brown rice with broccoli, carrot and sweet pepper. A balanced dinner from one pan.',
        'meal_type' => 'dinner', 'cuisine' => 'International', 'difficulty' => 'medium', 'cook_time' => 30, 'servings' => 2,
        'diet_tags' => ['gluten-free', 'high-protein'],
        'ingredients' => [
            'Lean beef strips|300|g|300 g|protein', 'Cooked brown rice|1.5|cup|1 1/2 cups|grains', 'Broccoli florets|200|g|200 g|produce',
            'Carrot|1|pieces|1 medium|produce', 'Red pepper|1|pieces|1|produce', 'Olive oil|1|tbsp|1 tbsp|pantry',
            'Garlic|2|cloves|2 cloves|produce', 'Smoked paprika|1|tsp|1 tsp|pantry', 'Lemon juice|1|tbsp|1 tbsp|pantry',
        ],
        'steps' => [
            'Mix the beef strips with the paprika, half the olive oil and a pinch of salt. Leave for 10 minutes.',
            'Slice the carrot thinly, cut the pepper into strips and crush the garlic.',
            'Heat a large pan until very hot and sear the beef for 2 to 3 minutes until browned. Lift it out onto a plate.',
            'In the same pan, add the rest of the oil, the carrot and broccoli, and stir-fry for 5 minutes. Add the pepper and garlic and cook 2 minutes more.',
            'Return the beef to the pan, squeeze in the lemon juice, and toss for 1 minute.',
            'Heat the rice, divide it between two bowls and spoon the beef and vegetables over it.',
        ],
    ],
    [
        'id' => 'black-bean-avocado-bowl', 'title' => 'Black Bean and Avocado Bowl',
        'description' => 'Spiced black beans, brown rice, sweetcorn and fresh avocado with a squeeze of lime. Plant-based and ready in 20 minutes.',
        'meal_type' => 'lunch', 'cuisine' => 'Mexican', 'difficulty' => 'easy', 'cook_time' => 20, 'servings' => 2,
        'diet_tags' => ['vegan', 'vegetarian', 'gluten-free'],
        'ingredients' => [
            'Black beans, drained|1|can|1 can (400 g)|pantry', 'Cooked brown rice|1|cup|1 cup|grains', 'Ripe avocado|1|pieces|1|produce',
            'Sweetcorn kernels|150|g|150 g|produce', 'Cherry tomatoes|150|g|150 g|produce', 'Red onion|0.5|pieces|1/2|produce',
            'Lime juice|2|tbsp|2 tbsp|pantry', 'Fresh coriander|0.25|bunch|1/4 bunch|produce', 'Ground cumin|1|tsp|1 tsp|pantry',
            'Olive oil|1|tbsp|1 tbsp|pantry',
        ],
        'steps' => [
            'Warm the olive oil in a pan, add the black beans and cumin, and cook for 4 minutes, mashing a few beans with your spoon.',
            'Add the sweetcorn and cook for 2 minutes until hot.',
            'Finely chop the red onion and coriander. Halve the tomatoes and slice the avocado.',
            'Spoon rice into two bowls. Top with the beans and sweetcorn, then the tomato, avocado and onion.',
            'Drizzle with lime juice and finish with the coriander.',
        ],
    ],
    [
        'id' => 'cauliflower-rice-bowl', 'title' => 'Cauliflower Rice and Chickpea Bowl',
        'description' => 'Light cauliflower rice with cumin-roasted chickpeas, red pepper and spinach. A low-carb dinner that still fills you up.',
        'meal_type' => 'dinner', 'cuisine' => 'International', 'difficulty' => 'easy', 'cook_time' => 25, 'servings' => 2,
        'diet_tags' => ['vegan', 'vegetarian', 'gluten-free'],
        'ingredients' => [
            'Cauliflower|1|pieces|1 small head|produce', 'Chickpeas, drained|1|can|1 can (400 g)|pantry', 'Red pepper|1|pieces|1|produce',
            'Baby spinach|2|cup|2 cups|produce', 'Olive oil|2|tbsp|2 tbsp|pantry', 'Garlic|2|cloves|2 cloves|produce',
            'Ground cumin|1|tsp|1 tsp|pantry', 'Lemon juice|1|tbsp|1 tbsp|pantry',
        ],
        'steps' => [
            'Heat the oven to 200 C. Pat the chickpeas dry, toss with 1 tbsp of the oil and the cumin, and spread on a baking tray. Roast for 15 minutes.',
            'Meanwhile, cut the cauliflower into pieces and pulse in a food processor, or grate it, until it looks like rice.',
            'Slice the pepper and crush the garlic. Heat the remaining oil in a large pan and cook the pepper for 4 minutes.',
            'Add the garlic and cauliflower rice and cook for 5 minutes, stirring, until just tender.',
            'Stir in the spinach until it wilts, then add the lemon juice and a pinch of salt.',
            'Serve in bowls, topped with the roasted chickpeas.',
        ],
    ],
    [
        'id' => 'chicken-quinoa-bowl', 'title' => 'Lemon Chicken and Quinoa Bowl',
        'description' => 'Paprika-rubbed chicken breast over fluffy quinoa with cucumber, tomato and spinach. A high-protein lunch that keeps well for meal prep.',
        'meal_type' => 'lunch', 'cuisine' => 'International', 'difficulty' => 'medium', 'cook_time' => 35, 'servings' => 2,
        'diet_tags' => ['gluten-free', 'high-protein'],
        'ingredients' => [
            'Chicken breast|300|g|300 g|protein', 'Quinoa, uncooked|0.75|cup|3/4 cup|grains', 'Cucumber|0.5|pieces|1/2|produce',
            'Cherry tomatoes|150|g|150 g|produce', 'Baby spinach|1|cup|1 cup|produce', 'Olive oil|2|tbsp|2 tbsp|pantry',
            'Lemon juice|2|tbsp|2 tbsp|pantry', 'Paprika|1|tsp|1 tsp|pantry', 'Garlic|1|cloves|1 clove|produce',
        ],
        'steps' => [
            'Rinse the quinoa, then simmer it in 1 1/2 cups of water with a pinch of salt for 15 minutes. Cover and rest 5 minutes, then fluff with a fork.',
            'Rub the chicken with the paprika, crushed garlic, 1 tbsp of the oil and a pinch of salt.',
            'Cook the chicken in a hot pan for 6 to 7 minutes each side until golden and cooked through. Rest it for 5 minutes, then slice.',
            'Dice the cucumber and halve the tomatoes. Mix the remaining oil and the lemon juice for the dressing.',
            'Divide the quinoa and spinach between two bowls, add the cucumber, tomato and chicken, and drizzle with the dressing.',
        ],
    ],
    [
        'id' => 'chickpea-tahini-bowl', 'title' => 'Chickpea and Tahini Bowl',
        'description' => 'Spiced chickpeas and fresh vegetables over quinoa with a lemony tahini sauce. Vegan, quick and full of fibre.',
        'meal_type' => 'lunch', 'cuisine' => 'Middle Eastern', 'difficulty' => 'easy', 'cook_time' => 20, 'servings' => 2,
        'diet_tags' => ['vegan', 'vegetarian', 'gluten-free'],
        'ingredients' => [
            'Chickpeas, drained|1|can|1 can (400 g)|pantry', 'Cooked quinoa|1|cup|1 cup|grains', 'Tahini|2|tbsp|2 tbsp|pantry',
            'Lemon juice|2|tbsp|2 tbsp|pantry', 'Garlic|1|cloves|1 clove|produce', 'Cucumber|0.5|pieces|1/2|produce',
            'Cherry tomatoes|150|g|150 g|produce', 'Fresh parsley|0.25|bunch|1/4 bunch|produce', 'Olive oil|1|tbsp|1 tbsp|pantry',
            'Ground cumin|0.5|tsp|1/2 tsp|pantry', 'Water|45|ml|3 tbsp|pantry',
        ],
        'steps' => [
            'Warm the olive oil in a pan and fry the chickpeas with the cumin for 5 minutes until lightly crisp.',
            'Whisk the tahini, lemon juice, crushed garlic and water with a pinch of salt until smooth and pourable.',
            'Dice the cucumber, halve the tomatoes and chop the parsley.',
            'Divide the quinoa between two bowls and top with the chickpeas, cucumber and tomato.',
            'Drizzle with the tahini sauce and scatter with parsley.',
        ],
    ],
    [
        'id' => 'cottage-cheese-pineapple-bowl', 'title' => 'Cottage Cheese and Pineapple Bowl',
        'description' => 'Creamy cottage cheese with sweet pineapple, pumpkin seeds and a drizzle of honey. A five-minute, high-protein breakfast or snack.',
        'meal_type' => 'breakfast', 'cuisine' => 'International', 'difficulty' => 'easy', 'cook_time' => 5, 'servings' => 1,
        'diet_tags' => ['vegetarian', 'gluten-free', 'high-protein'],
        'ingredients' => [
            'Cottage cheese|1|cup|1 cup (about 200 g)|dairy', 'Pineapple chunks|1|cup|1 cup|produce', 'Pumpkin seeds|1|tbsp|1 tbsp|pantry',
            'Honey|1|tsp|1 tsp|pantry', 'Ground cinnamon|0.25|tsp|1/4 tsp|pantry',
        ],
        'steps' => [
            'Spoon the cottage cheese into a bowl.',
            'Top with the pineapple chunks and pumpkin seeds.',
            'Drizzle with honey, dust with cinnamon and serve straight away.',
        ],
    ],
    [
        'id' => 'edamame-brown-rice-bowl', 'title' => 'Edamame and Brown Rice Bowl',
        'description' => 'Brown rice with protein-rich edamame, crunchy carrot and cucumber, dressed with tamari, rice vinegar and sesame.',
        'meal_type' => 'lunch', 'cuisine' => 'Asian', 'difficulty' => 'easy', 'cook_time' => 25, 'servings' => 2,
        'diet_tags' => ['vegan', 'vegetarian', 'gluten-free'],
        'ingredients' => [
            'Cooked brown rice|2|cup|2 cups|grains', 'Shelled edamame, frozen|200|g|200 g|produce', 'Carrot|1|pieces|1 medium|produce',
            'Cucumber|0.5|pieces|1/2|produce', 'Spring onions|2|pieces|2|produce', 'Tamari (gluten-free soy sauce)|1|tbsp|1 tbsp|pantry',
            'Rice vinegar|1|tbsp|1 tbsp|pantry', 'Toasted sesame oil|1|tsp|1 tsp|pantry', 'Sesame seeds|1|tsp|1 tsp|pantry',
        ],
        'steps' => [
            'Cook the edamame in boiling water for 4 minutes, drain and let them cool a little.',
            'Grate the carrot, slice the cucumber into half moons and slice the spring onions.',
            'Whisk the tamari, rice vinegar and sesame oil together.',
            'Warm the rice and divide it between two bowls. Arrange the edamame, carrot and cucumber on top.',
            'Pour over the dressing and finish with the spring onions and sesame seeds.',
        ],
    ],
    [
        'id' => 'farro-veggie-bowl', 'title' => 'Roasted Veggie and Farro Bowl',
        'description' => 'Chewy farro with oven-roasted zucchini, pepper and tomatoes, finished with balsamic vinegar and fresh basil.',
        'meal_type' => 'lunch', 'cuisine' => 'Mediterranean', 'difficulty' => 'medium', 'cook_time' => 35, 'servings' => 2,
        'diet_tags' => ['vegan', 'vegetarian'],
        'ingredients' => [
            'Farro|0.75|cup|3/4 cup|grains', 'Zucchini|1|pieces|1|produce', 'Red pepper|1|pieces|1|produce',
            'Red onion|0.5|pieces|1/2|produce', 'Cherry tomatoes|150|g|150 g|produce', 'Olive oil|2|tbsp|2 tbsp|pantry',
            'Balsamic vinegar|1|tbsp|1 tbsp|pantry', 'Fresh basil|0.25|bunch|1/4 bunch|produce', 'Salt|0.5|tsp|1/2 tsp|pantry',
        ],
        'steps' => [
            'Heat the oven to 210 C. Cut the zucchini, pepper and onion into bite-sized pieces and halve the tomatoes.',
            'Toss the vegetables with 1 tbsp of the oil and half the salt on a baking tray. Roast for 20 to 25 minutes until soft at the edges.',
            'Meanwhile, simmer the farro in plenty of salted water for 20 to 25 minutes until tender but chewy. Drain well.',
            'Toss the warm farro with the remaining oil and the balsamic vinegar.',
            'Serve the farro topped with the roasted vegetables and torn basil.',
        ],
    ],
    [
        'id' => 'garlic-chicken-bowl', 'title' => 'Garlic Chicken Rice Bowl',
        'description' => 'Juicy garlic and lemon chicken thighs over rice with crisp green beans. Simple, dairy-free and quick enough for a weeknight.',
        'meal_type' => 'dinner', 'cuisine' => 'International', 'difficulty' => 'easy', 'cook_time' => 30, 'servings' => 2,
        'diet_tags' => ['gluten-free', 'high-protein'],
        'ingredients' => [
            'Boneless chicken thighs|400|g|400 g|protein', 'Garlic|4|cloves|4 cloves|produce', 'Olive oil|1|tbsp|1 tbsp|pantry',
            'Lemon juice|1|tbsp|1 tbsp|pantry', 'Cooked white rice|1.5|cup|1 1/2 cups|grains', 'Green beans|200|g|200 g|produce',
            'Paprika|1|tsp|1 tsp|pantry', 'Fresh parsley|0.25|bunch|1/4 bunch|produce',
        ],
        'steps' => [
            'Slice the chicken into strips and mix with the crushed garlic, paprika, lemon juice and a pinch of salt. Leave for 10 minutes.',
            'Heat the oil in a large pan and cook the chicken for 6 to 8 minutes, turning, until golden and cooked through.',
            'Boil the green beans for 4 minutes until bright and just tender, then drain.',
            'Warm the rice and divide it between two bowls.',
            'Top with the chicken and green beans, spoon over the pan juices and scatter with chopped parsley.',
        ],
    ],
];
