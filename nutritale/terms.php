<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/legal_layout.php';

$price = 'R' . number_format(PREMIUM_MONTHLY_PRICE, 2);
$commission = (int)PLATFORM_COMMISSION_PCT;

legal_page_start('Terms of Service', APP_NAME . "'s Terms of Service: accounts, Premium, selling recipes and the marketplace.");
?>
<p>These terms are the agreement between you and <?= legal_contact_html() ?> ("we", "us"), who runs <?= APP_NAME ?>. By creating an account or using <?= APP_NAME ?> you agree to them. If you do not agree, please do not use the service.</p>

<h2>1. Who can use <?= APP_NAME ?></h2>
<p>You must be 18 or older, or have a parent or guardian's permission to use <?= APP_NAME ?>. You must give your real name and an email address you can receive mail at, keep your password private, and tell us if you think someone else has used your account.</p>

<h2>2. Recipes, nutrition and allergies</h2>
<ul>
    <li>Recipes, calorie and nutrition figures and meal ideas are general information only. They are not medical or dietary advice. Speak to a doctor or dietitian about any medical condition.</li>
    <li>You can tell <?= APP_NAME ?> about your allergies. We use them to hide recipes that contain those allergens and to check AI meal ideas. These checks can miss things: always read the ingredients and the labels on the food you buy.</li>
    <li>AI meal ideas are written by a computer (Google Gemini). They can be wrong or incomplete. Use your own judgement before cooking them.</li>
</ul>

<h2>3. Free and Premium</h2>
<ul>
    <li>A free account includes up to <?= (int)FREE_RECIPE_LIMIT ?> recipes and a limited number of pantry matches and AI meal ideas.</li>
    <li>Premium costs <?= h($price) ?> per month and unlocks the features listed on the Premium page. Recipes that other members sell are bought separately. Premium is charged through PayFast every month until you cancel.</li>
    <li>You can cancel at any time from your profile. Cancelling stops all future payments, and Premium features end straight away. See the <a href="refund.php">Refund Policy</a> for refunds.</li>
    <li>We will tell you at least 30 days before a price change. The new price applies only from your next payment after that notice.</li>
</ul>

<h2>4. Selling recipes</h2>
<ul>
    <li>Premium members can sell their own recipes. You must own the recipe and any photos you upload, or have permission to use them.</li>
    <li>We keep a <?= $commission ?>% platform fee from each sale. The rest is owed to you and paid by EFT to your bank account.</li>
    <li>We can hide or remove a recipe that breaks these terms, copies someone else's work, or is reported and found to be unsafe or misleading.</li>
</ul>

<h2>5. The ingredient marketplace</h2>
<p>When you buy ingredients from another member, the sale is between you and that seller. The seller is responsible for the quality, safety and delivery of what they sell. We process the payment through PayFast, keep a <?= $commission ?>% platform fee, and will help sort out a problem if you contact us.</p>

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
