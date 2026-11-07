<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/legal_layout.php';

legal_page_start('Privacy Policy', 'What personal information ' . APP_NAME . ' collects, why, who it is shared with, and your rights under POPIA.');
?>
<p><?= APP_NAME ?> is run by <?= legal_contact_html() ?>. We are the "responsible party" for your personal information under South Africa's Protection of Personal Information Act (POPIA). This policy explains what we collect, why, who we share it with, and what you can do about it.</p>

<h2>1. What we collect</h2>
<ul>
    <li><strong>Your account:</strong> name, email address, and your password (stored only as a scrambled "hash" that cannot be turned back into the password). If you sign in with Google, Facebook or Apple, we receive your name, email and an account number from that provider, never your password there. A profile photo, only if you add one.</li>
    <li><strong>Food and health details you choose to give:</strong> allergies, diet preferences (for example vegetarian or gluten-free) and daily nutrition goals. Allergies count as health information under POPIA. By adding them you agree that we use them for the purpose below, and you can remove them at any time in your profile.</li>
    <li><strong>What you do in the app:</strong> your pantry list, meal plans, shopping lists, favourites, ratings and reviews, recipes you add, reports you send, the recipes you open, and the AI meal ideas you ask for.</li>
    <li><strong>Payments:</strong> PayFast handles your card or bank details. We never see or store them. We keep the payment reference, amount, date and status.</li>
    <li><strong>Security and device details:</strong> the internet (IP) address and email used for each login attempt, to block password-guessing, and the time you last signed in.</li>
    <li><strong>Cookies:</strong> one cookie that keeps you signed in for up to 30 days, your light/dark mode choice saved in your browser, and Google Analytics cookies that count visits.</li>
</ul>

<h2>2. Why we use it</h2>
<ul>
    <li>To run your account and show you recipes, meal plans and shopping lists.</li>
    <li>To hide recipes with your allergens and to check AI meal ideas against them.</li>
    <li>To take payments, pay recipe sellers, and keep the records the law requires.</li>
    <li>To keep accounts safe, stop abuse, and fix problems.</li>
    <li>To understand which parts of the app are used, so we can improve them.</li>
</ul>
<p>We do not sell your personal information, and we do not use it for advertising.</p>

<h2>3. Who we share it with</h2>
<ul>
    <li><strong>Google Gemini</strong> (AI meal ideas): your pantry items, diet preferences and allergies, only when you ask for meal ideas. Your name and email are not sent.</li>
    <li><strong>PayFast</strong> (payments): the amount and the details PayFast needs to take the payment.</li>
    <li><strong>Google Analytics</strong> (visit statistics): pages visited and device type.</li>
    <li><strong>Open Food Facts</strong> (barcode scanning): only the barcode number you scan.</li>
    <li><strong>TheMealDB</strong> (extra recipe ideas for Premium): ingredient names only.</li>
    <li><strong>Google, Facebook or Apple</strong>, only if you choose to sign in with them.</li>
    <li><strong>Our hosting provider</strong>, which stores the app and its database.</li>
</ul>
<p>Some of these companies store data outside South Africa. We only use providers that protect personal information to a standard similar to POPIA. We share information with the police or a court only when the law requires it.</p>

<h2>4. How long we keep it</h2>
<p>We keep your information while you have an account. When you delete your account, your personal information is removed straight away. Payment records are kept without your name or email for as long as tax law requires (at least 5 years), because they are business records and also belong to the other person in a sale.</p>

<h2>5. Your rights</h2>
<ul>
    <li><strong>See and correct</strong> your details at any time in your profile, or ask us for a copy of what we hold.</li>
    <li><strong>Delete</strong> your account and personal information in the app: Profile &gt; Delete account.</li>
    <li><strong>Withdraw permission</strong> for your allergy and diet information by removing it in your profile.</li>
    <li><strong>Object or complain</strong> to us first, using the contact details below. You can also complain to South Africa's Information Regulator at <a href="https://inforegulator.org.za" target="_blank" rel="noopener">inforegulator.org.za</a>.</li>
</ul>

<h2>6. Keeping it safe</h2>
<p>The app uses an encrypted (HTTPS) connection, passwords are stored only as hashes, every form is protected against forged requests, and repeated wrong passwords lock the account for a while. No system is perfectly secure. If we discover a breach that affects you, we will tell you and the Information Regulator as POPIA requires.</p>

<h2>7. Children</h2>
<p><?= APP_NAME ?> is meant for adults. Someone under 18 may use it only with a parent or guardian's permission.</p>

<h2>8. Changes and contact</h2>
<p>If we change this policy in a way that matters, we will tell you in the app or by email. Questions or requests: <?= legal_contact_html() ?>.</p>
<?php legal_page_end();
