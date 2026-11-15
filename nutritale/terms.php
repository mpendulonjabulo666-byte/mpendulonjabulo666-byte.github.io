<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/legal_layout.php';

$price = 'R' . number_format(PREMIUM_MONTHLY_PRICE, 2);
$commission = (int)PLATFORM_COMMISSION_PCT;

legal_page_start('Terms of Service', APP_NAME . "'s Terms of Service: accounts, Premium, the ingredient marketplace.");
?>
<p>These terms are the agreement between you and <?= legal_contact_html() ?> ("we", "us"), who runs <?= APP_NAME ?>. By creating an account or using <?= APP_NAME ?> you agree to them. If you do not agree, please do not use the service.</p>

<h2>1. Who can use <?= APP_NAME ?></h2>
<p>You must be 18 or older, or have a parent or guardian's permission to use <?= APP_NAME ?>. You must give your real name and an email address you can receive mail at, keep your password private, and tell us if you think someone else has used your account.</p>

<h2>2. Recipes, nutrition and allergies</h2>
<ul>
    <li>Recipes, calorie and nutrition figures and meal ideas are general information only. They are not medical or dietary advice. Speak to a doctor or dietitian about any medical condition.</li>
    <li>You can tell <?= APP_NAME ?> about your allergies. We use them to hide recipes that contain those allergens and to check AI meal ideas. These checks can miss things: always read the ingredients and the labels on the food you buy.</li>
    <li>AI meal ideas are written by a computer (Google Gemini). They can be wrong or incomplete. Use your own judgement before cooking them.</li>
    <li>The hormone-supportive eating guide is general nutrition information about everyday foods. It does not diagnose, treat or cure any condition and makes no promise about your health. If you have a medical condition, are pregnant or breastfeeding, or take medication, speak to your doctor or clinic before changing your diet.</li>
    <li>Links to Pick n Pay open their website. Anything you buy there is a sale between you and Pick n Pay, at their prices and on their terms. We are not paid for those links.</li>
</ul>

<h2>3. Free and Premium</h2>
<ul>
    <li>A free account includes up to <?= (int)FREE_RECIPE_LIMIT ?> recipes and a limited number of pantry matches and AI meal ideas.</li>
    <li>Premium costs <?= h($price) ?> per month, in South African rand, VAT included where it applies. It unlocks the features listed on the Premium page. Premium is charged through PayFast every month until you cancel.</li>
    <li><strong>Delivery:</strong> <?= APP_NAME ?> is a digital service, so nothing is posted or couriered to you. Premium features, and any recipe you buy, are available in your account as soon as PayFast confirms your payment, usually within a few minutes. If they have not appeared after an hour, contact us and we will fix it or refund you.</li>
    <li>You can cancel at any time from your profile. Cancelling stops all future payments, and Premium features end straight away. See the <a href="refund.php">Refund Policy</a> for refunds.</li>
    <li>We will tell you at least 30 days before a price change. The new price applies only from your next payment after that notice.</li>
</ul>

<h2>4. Selling recipes</h2>
<p>Selling your own recipes to other members is coming soon and is not available yet. These terms will be updated, with the details, before it opens.</p>

<h2>5. The ingredient marketplace</h2>
<p>The ingredient marketplace is coming soon. For now, members can list ingredients and contact each other directly. Any sale is between the buyer and the seller, who agree the price, pickup or delivery and pay each other themselves. <?= h(APP_NAME) ?> does not take payment for these sales, does not hold anyone's money, and is not a party to them. The seller is responsible for the quality and safety of what they sell. Premium-only listings are visible to Premium members only. When we add payments inside the app, these terms will be updated first.</p>

<h2>6. What you may not do</h2>
<ul>
    <li>Break the law, or post anything offensive, hateful, dangerous or misleading.</li>
    <li>Upload anything you do not have the right to share.</li>
    <li>Try to get into other people's accounts, get round payment or usage limits, or overload or attack the service.</li>
    <li>Use automated tools to copy recipes or data from <?= APP_NAME ?>.</li>
</ul>
<p>You can report a recipe from its page. We may suspend or close an account that breaks these rules.</p>

<h2>7. Your content</h2>
<p>You keep ownership of the recipes, photos and reviews you add. You give us permission to show them in <?= APP_NAME ?> for as long as they are on the service. Deleting a recipe or your account removes it, except a recipe someone has paid for, which stays available to that buyer only.</p>

<h2>8. Deleting your account</h2>
<p>You can delete your account at any time from Profile &gt; Delete account. The <a href="privacy.php">Privacy Policy</a> explains what is removed and what payment records we must keep.</p>

<h2>9. Availability and changes</h2>
<p>We work to keep <?= APP_NAME ?> running, but it may sometimes be unavailable, and features can change. If we change these terms in a way that matters, we will tell you in the app or by email before the change takes effect.</p>

<h2>10. Liability</h2>
<p>Nothing in these terms takes away rights you have under South African law, including the Consumer Protection Act. As far as the law allows, we are not responsible for loss caused by relying on recipe, nutrition or AI information, or by a sale between two members.</p>

<h2>11. Law and contact</h2>
<p>These terms are governed by the laws of South Africa. Questions or complaints: <?= legal_contact_html() ?>.</p>
<?php legal_page_end();
