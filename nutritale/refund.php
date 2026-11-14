<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/legal_layout.php';

$price = 'R' . number_format(PREMIUM_MONTHLY_PRICE, 2);

legal_page_start('Refund Policy', 'How cancellations and refunds work for ' . APP_NAME . ' Premium, recipe purchases and marketplace orders.');
?>
<p>This policy explains how to cancel and when you can get your money back. It does not take away any right you have under South African law, including the Consumer Protection Act and the Electronic Communications and Transactions Act.</p>

<h2>1. Premium (<?= h($price) ?> per month)</h2>
<ul>
    <li><strong>Cancel any time</strong> from your profile. PayFast stops charging you straight away, and Premium features end at the same time.</li>
    <li><strong>First 7 days:</strong> if you cancel within 7 days of your first Premium payment, email us and we will refund that payment in full.</li>
    <li><strong>After that:</strong> payments for months that have already started are not refunded, unless Premium did not work because of a fault on our side.</li>
    <li>If you were charged after you cancelled, or charged twice, we refund the extra payment in full.</li>
</ul>

<h2>2. Recipes you buy from other members</h2>
<p>A recipe opens straight after you pay, so it cannot be "returned". We refund the purchase in full if the recipe will not open, is missing important parts, or is clearly not what its listing described. Email us within 14 days of buying it.</p>

<h2>3. Ingredients from the marketplace</h2>
<p>The seller is responsible for what they send. If your order does not arrive, or arrives damaged, spoiled or different from the listing, contact the seller and us within 7 days. If it cannot be fixed, we refund your payment.</p>

<h2>4. How refunds are paid</h2>
<p>Refunds go back through PayFast to the card or account you paid with, usually within 7 to 10 working days after we approve them.</p>

<h2>5. How to ask</h2>
<p>Email <?= legal_contact_html() ?> with the email address on your account, the date and amount of the payment, and what went wrong. We reply within 3 working days.</p>
<?php legal_page_end();
