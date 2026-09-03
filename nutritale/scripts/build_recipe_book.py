#!/usr/bin/env python3
"""Builds the NutriTale starter recipe book PDF from the seed recipe data.

Regenerate after editing data/seed_recipes.php's recipe list:
    pip install reportlab
    python3 scripts/build_recipe_book.py
"""

from pathlib import Path

from reportlab.lib.pagesizes import letter
from reportlab.lib.units import inch
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
    HRFlowable, Frame, PageTemplate, BaseDocTemplate, NextPageTemplate,
)
from reportlab.platypus.flowables import Flowable
from reportlab.pdfgen import canvas as pdfcanvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

SCRIPT_DIR = Path(__file__).resolve().parent
APP_DIR = SCRIPT_DIR.parent
BOOK_IMAGE = APP_DIR / 'assets' / 'img' / 'logo' / 'book-mark.png'
OUTPUT_PDF = APP_DIR / 'assets' / 'downloads' / 'nutritale-recipe-book.pdf'

pdfmetrics.registerFont(TTFont('Fraunces-SemiBold', str(SCRIPT_DIR / 'fonts' / 'Fraunces-SemiBold.ttf')))
pdfmetrics.registerFont(TTFont('Fraunces-Bold', str(SCRIPT_DIR / 'fonts' / 'Fraunces-Bold.ttf')))

GREEN = colors.HexColor('#2fae66')
GREEN_DARK = colors.HexColor('#1f7d49')
GREEN_LIGHT = colors.HexColor('#eafaf0')
INK = colors.HexColor('#1c2521')
MUTED = colors.HexColor('#6b7a72')
CREAM = colors.HexColor('#f6f9f7')
BORDER = colors.HexColor('#e2e8e4')

RECIPES = [
    {
        'title': 'Overnight Oats with Mixed Berries',
        'description': 'Creamy oats soaked overnight with chia seeds and a swirl of berry compote — ready when you wake up.',
        'meal_type': 'Breakfast', 'cuisine': 'American', 'difficulty': 'Easy',
        'cook_time': 10, 'servings': 1, 'calories': 340, 'protein': 12, 'carbs': 52, 'fat': 9,
        'diet_tags': ['Vegetarian'],
        'ingredients': ['1/2 cup rolled oats', '3/4 cup milk', '1 tbsp chia seeds', '1/2 cup mixed berries', '1 tbsp honey'],
        'steps': [
            'Combine oats, milk, chia seeds and honey in a jar.',
            'Stir well, cover and refrigerate overnight (at least 6 hours).',
            'Top with mixed berries before serving.',
        ],
    },
    {
        'title': 'Grilled Chicken & Quinoa Bowl',
        'description': 'A high-protein bowl with grilled chicken, fluffy quinoa, roasted vegetables and a lemon-tahini drizzle.',
        'meal_type': 'Lunch', 'cuisine': 'Mediterranean', 'difficulty': 'Medium',
        'cook_time': 35, 'servings': 2, 'calories': 520, 'protein': 42, 'carbs': 45, 'fat': 18,
        'diet_tags': ['High-protein', 'Gluten-free'],
        'ingredients': ['2 pieces chicken breast', '1 cup quinoa', '1 zucchini', '1 cup cherry tomatoes', '2 tbsp tahini', '1 tbsp lemon juice', '2 tbsp olive oil'],
        'steps': [
            'Rinse quinoa and cook in salted water according to package instructions.',
            'Season chicken with salt, pepper and olive oil, then grill 6-7 minutes per side until cooked through.',
            'Roast zucchini and cherry tomatoes at 200°C for 15 minutes.',
            'Whisk tahini with lemon juice and a splash of water to make the dressing.',
            'Slice chicken and assemble bowls with quinoa, vegetables and dressing.',
        ],
    },
    {
        'title': 'Hearty Lentil & Vegetable Soup',
        'description': 'A warming, fiber-rich soup packed with red lentils, carrots and spinach — one pot, big batch.',
        'meal_type': 'Dinner', 'cuisine': 'Middle Eastern', 'difficulty': 'Easy',
        'cook_time': 40, 'servings': 4, 'calories': 310, 'protein': 18, 'carbs': 48, 'fat': 6,
        'diet_tags': ['Vegan', 'Vegetarian', 'Gluten-free'],
        'ingredients': ['1 1/2 cups red lentils', '3 carrots', '1 onion', '3 cloves garlic', '6 cups vegetable stock', '2 cups spinach', '1 tsp cumin'],
        'steps': [
            'Sauté diced onion and garlic in a large pot until soft.',
            'Add cumin, carrots and lentils; stir for a minute to coat.',
            'Pour in vegetable stock, bring to a boil, then simmer 25 minutes until lentils are tender.',
            'Stir in spinach until wilted, season to taste and serve.',
        ],
    },
    {
        'title': 'Baked Salmon with Asparagus',
        'description': 'Sheet-pan salmon fillets with garlic butter asparagus — done in under 30 minutes.',
        'meal_type': 'Dinner', 'cuisine': 'American', 'difficulty': 'Easy',
        'cook_time': 25, 'servings': 2, 'calories': 460, 'protein': 38, 'carbs': 8, 'fat': 30,
        'diet_tags': ['High-protein', 'Gluten-free', 'Keto'],
        'ingredients': ['2 pieces salmon fillets', '1 bunch asparagus', '2 tbsp butter', '2 cloves garlic', '1 lemon'],
        'steps': [
            'Preheat oven to 200°C. Line a sheet pan with foil.',
            'Trim asparagus and place alongside salmon fillets on the pan.',
            'Melt butter with minced garlic, drizzle over salmon and asparagus, season with salt and pepper.',
            'Bake 12-15 minutes until salmon flakes easily. Finish with a squeeze of lemon.',
        ],
    },
    {
        'title': 'Greek Yogurt Parfait',
        'description': 'Layered Greek yogurt, granola and honey — a quick high-protein breakfast or snack.',
        'meal_type': 'Snack', 'cuisine': 'Greek', 'difficulty': 'Easy',
        'cook_time': 5, 'servings': 1, 'calories': 260, 'protein': 20, 'carbs': 30, 'fat': 7,
        'diet_tags': ['Vegetarian', 'High-protein'],
        'ingredients': ['1 cup Greek yogurt', '1/4 cup granola', '1 tbsp honey', '1 tbsp almonds'],
        'steps': [
            'Spoon a third of the yogurt into a glass.',
            'Layer with granola and a drizzle of honey, repeat twice more.',
            'Top with chopped almonds and serve immediately.',
        ],
    },
    {
        'title': 'Chickpea & Vegetable Stir-Fry',
        'description': 'A fast weeknight stir-fry with crispy chickpeas, bell peppers and a soy-ginger glaze.',
        'meal_type': 'Dinner', 'cuisine': 'Asian', 'difficulty': 'Easy',
        'cook_time': 20, 'servings': 2, 'calories': 380, 'protein': 15, 'carbs': 50, 'fat': 12,
        'diet_tags': ['Vegan', 'Vegetarian'],
        'ingredients': ['1 can chickpeas', '2 bell peppers', '1 cup broccoli', '2 tbsp soy sauce', '1 tbsp ginger', '1 cup rice'],
        'steps': [
            'Cook rice according to package instructions.',
            'Drain and pat chickpeas dry, then pan-fry in a hot skillet until crisp.',
            'Add sliced peppers and broccoli, stir-fry 4-5 minutes.',
            'Stir in soy sauce and grated ginger, cook 1 more minute, and serve over rice.',
        ],
    },
    {
        'title': 'Avocado Toast with Poached Egg',
        'description': 'Crushed avocado on toasted sourdough topped with a soft poached egg and chili flakes.',
        'meal_type': 'Breakfast', 'cuisine': 'American', 'difficulty': 'Medium',
        'cook_time': 15, 'servings': 1, 'calories': 350, 'protein': 15, 'carbs': 28, 'fat': 20,
        'diet_tags': ['Vegetarian'],
        'ingredients': ['2 slices sourdough bread', '1 avocado', '1 egg', 'pinch chili flakes', '1 tsp lemon juice'],
        'steps': [
            'Toast the sourdough slices until golden.',
            'Mash avocado with lemon juice, salt and pepper, then spread over toast.',
            'Bring a pot of water to a gentle simmer, swirl, and poach the egg for 3 minutes.',
            'Top toast with the poached egg and a sprinkle of chili flakes.',
        ],
    },
    {
        'title': 'One-Pot Turkey Chili',
        'description': 'A meal-prep favorite — lean ground turkey simmered with beans, tomatoes and warm spices.',
        'meal_type': 'Dinner', 'cuisine': 'Mexican', 'difficulty': 'Medium',
        'cook_time': 45, 'servings': 4, 'calories': 410, 'protein': 34, 'carbs': 32, 'fat': 14,
        'diet_tags': ['High-protein', 'Gluten-free'],
        'ingredients': ['500g ground turkey', '1 can kidney beans', '1 can diced tomatoes', '1 onion', '2 tbsp chili powder', '1 tsp cumin'],
        'steps': [
            'Brown ground turkey with diced onion in a large pot.',
            'Stir in chili powder and cumin, cook 1 minute until fragrant.',
            'Add beans and diced tomatoes, bring to a simmer.',
            'Cover and cook 30 minutes, stirring occasionally, then season to taste.',
        ],
    },
]


def cover_page(c, doc_width, doc_height):
    c.saveState()
    c.setFillColor(CREAM)
    c.rect(0, 0, doc_width, doc_height, fill=1, stroke=0)
    c.setFillColor(GREEN)
    c.rect(0, doc_height - 0.35 * inch, doc_width, 0.35 * inch, fill=1, stroke=0)
    c.rect(0, 0, doc_width, 0.35 * inch, fill=1, stroke=0)

    book_w = 2.9 * inch
    book_h = book_w * 455 / 700
    c.drawImage(
        str(BOOK_IMAGE), doc_width / 2 - book_w / 2, doc_height - 1.0 * inch - book_h,
        width=book_w, height=book_h, mask='auto', preserveAspectRatio=True,
    )

    c.setFillColor(INK)
    c.setFont('Fraunces-Bold', 34)
    c.drawCentredString(doc_width / 2, doc_height - 3.35 * inch, 'NutriTale')

    c.setFillColor(GREEN_DARK)
    c.setFont('Fraunces-SemiBold', 19)
    c.drawCentredString(doc_width / 2, doc_height - 3.75 * inch, '8 Free Starter Recipes')

    c.setFillColor(MUTED)
    c.setFont('Helvetica', 12)
    c.drawCentredString(doc_width / 2, doc_height - 4.05 * inch, 'Balanced breakfasts, lunches and dinners to get you cooking today.')

    # Divider
    c.setStrokeColor(BORDER)
    c.setLineWidth(1)
    c.line(doc_width / 2 - 1.2 * inch, doc_height - 4.4 * inch, doc_width / 2 + 1.2 * inch, doc_height - 4.4 * inch)

    # What's inside
    c.setFillColor(MUTED)
    c.setFont('Helvetica-Bold', 9)
    c.drawCentredString(doc_width / 2, doc_height - 4.75 * inch, "WHAT'S INSIDE")
    c.setFont('Helvetica', 11)
    y = doc_height - 5.15 * inch
    text_x = doc_width / 2 - 1.6 * inch
    for i, r in enumerate(RECIPES):
        row_y = y - i * 0.3 * inch
        c.setFillColor(GREEN)
        c.circle(text_x + 2, row_y + 3, 2, fill=1, stroke=0)
        c.setFillColor(INK)
        c.drawString(text_x + 10, row_y, r['title'])

    c.setFillColor(GREEN_DARK)
    c.setFont('Fraunces-SemiBold', 12)
    c.drawCentredString(doc_width / 2, 1.4 * inch, 'Want more?')
    c.setFillColor(MUTED)
    c.setFont('Helvetica', 10)
    c.drawCentredString(doc_width / 2, 1.2 * inch, 'Create a free NutriTale account for meal planning, shopping lists,')
    c.drawCentredString(doc_width / 2, 1.05 * inch, 'nutrition goals, and an AI-matched "What Can I Make?" tool.')
    c.restoreState()


def make_ingredient_table(ingredients, styles):
    half = (len(ingredients) + 1) // 2
    left_col = ingredients[:half]
    right_col = ingredients[half:]
    rows = []
    for i in range(half):
        left = Paragraph('&#8226;&nbsp;&nbsp;' + left_col[i], styles['Ingredient'])
        right = Paragraph('&#8226;&nbsp;&nbsp;' + right_col[i], styles['Ingredient']) if i < len(right_col) else Paragraph('', styles['Ingredient'])
        rows.append([left, right])
    t = Table(rows, colWidths=[2.55 * inch, 2.55 * inch], hAlign='LEFT')
    t.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 2),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 2),
    ]))
    return t


def build():
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle('RecipeTitle', fontName='Fraunces-Bold', fontSize=21, textColor=INK, spaceAfter=6, leading=25))
    styles.add(ParagraphStyle('RecipeDesc', fontName='Helvetica-Oblique', fontSize=10.5, textColor=MUTED, spaceAfter=12, leading=14))
    styles.add(ParagraphStyle('SectionHead', fontName='Fraunces-SemiBold', fontSize=13, textColor=GREEN_DARK, spaceBefore=14, spaceAfter=6))
    styles.add(ParagraphStyle('Ingredient', fontName='Helvetica', fontSize=10, textColor=INK, leading=14))
    styles.add(ParagraphStyle('Step', fontName='Helvetica', fontSize=10.5, textColor=INK, leading=15, spaceAfter=7))
    styles.add(ParagraphStyle('Tag', fontName='Helvetica-Bold', fontSize=8, textColor=GREEN_DARK))
    styles.add(ParagraphStyle('PageNum', fontName='Helvetica', fontSize=8.5, textColor=MUTED))

    def on_page(c, doc):
        if doc.page == 1:
            return
        c.saveState()
        c.setStrokeColor(BORDER)
        c.setLineWidth(0.6)
        c.line(0.75 * inch, 0.65 * inch, letter[0] - 0.75 * inch, 0.65 * inch)
        c.setFillColor(MUTED)
        c.setFont('Helvetica', 8.5)
        c.drawString(0.75 * inch, 0.48 * inch, 'NutriTale — 8 Free Starter Recipes')
        c.drawRightString(letter[0] - 0.75 * inch, 0.48 * inch, str(doc.page))
        c.restoreState()

    def on_cover(c, doc):
        cover_page(c, letter[0], letter[1])

    doc = BaseDocTemplate(
        str(OUTPUT_PDF),
        pagesize=letter, topMargin=0.9 * inch, bottomMargin=0.9 * inch, leftMargin=0.75 * inch, rightMargin=0.75 * inch,
    )
    frame_cover = Frame(0, 0, letter[0], letter[1], id='cover')
    frame_body = Frame(doc.leftMargin, doc.bottomMargin, doc.width, doc.height, id='body')
    doc.addPageTemplates([
        PageTemplate(id='Cover', frames=[frame_cover], onPage=on_cover),
        PageTemplate(id='Body', frames=[frame_body], onPage=on_page),
    ])

    story = [NextPageTemplate('Body'), PageBreak()]
    for idx, r in enumerate(RECIPES):
        story.append(Paragraph(r['title'], styles['RecipeTitle']))
        story.append(Paragraph(r['description'], styles['RecipeDesc']))

        meta = f"{r['meal_type']} &nbsp;&#8226;&nbsp; {r['cuisine']} &nbsp;&#8226;&nbsp; {r['difficulty']} &nbsp;&#8226;&nbsp; {r['cook_time']} min &nbsp;&#8226;&nbsp; Serves {r['servings']}"
        story.append(Paragraph(meta, ParagraphStyle('Meta', fontName='Helvetica', fontSize=9.5, textColor=MUTED, spaceAfter=4)))

        macros = f"{r['calories']} cal &nbsp;&#8226;&nbsp; {r['protein']}g protein &nbsp;&#8226;&nbsp; {r['carbs']}g carbs &nbsp;&#8226;&nbsp; {r['fat']}g fat"
        story.append(Paragraph(macros, ParagraphStyle('Macros', fontName='Helvetica-Bold', fontSize=9.5, textColor=GREEN_DARK, spaceAfter=4)))

        if r['diet_tags']:
            tags = '&nbsp;&nbsp;'.join('[' + t + ']' for t in r['diet_tags'])
            story.append(Paragraph(tags, styles['Tag']))

        story.append(HRFlowable(width='100%', thickness=0.6, color=BORDER, spaceBefore=10, spaceAfter=2))
        story.append(Paragraph('INGREDIENTS', styles['SectionHead']))
        story.append(make_ingredient_table(r['ingredients'], styles))

        story.append(Paragraph('METHOD', styles['SectionHead']))
        for i, step in enumerate(r['steps']):
            story.append(Paragraph(f"<b>{i + 1}.</b> {step}", styles['Step']))

        if idx < len(RECIPES) - 1:
            story.append(PageBreak())

    doc.build(story)
    print('PDF built.')


if __name__ == '__main__':
    build()
