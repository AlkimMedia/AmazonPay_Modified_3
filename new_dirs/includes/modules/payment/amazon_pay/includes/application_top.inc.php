<?php
/**
 * @var string $PHP_SELF
 */

use AlkimAmazonPay\ConfigHelper;

require_once __DIR__ . '/../amazon_pay.php';
$configHelper = new ConfigHelper();
if (str_contains($PHP_SELF, 'shopping_cart.php') && !empty($_SESSION['payment']) && $_SESSION['payment'] === 'amazon_pay') {
    unset($_SESSION['payment']);
}


if (str_contains($PHP_SELF, 'address_book.php')) {
    include __DIR__.'/actions/address_book.php';
}

if (str_contains($PHP_SELF, 'account.php')) {
    include __DIR__.'/actions/account.php';
}

if (str_contains($PHP_SELF, 'checkout_shipping.php')) {
    include __DIR__.'/actions/checkout_shipping.php';
}

if (str_contains($PHP_SELF, 'checkout_payment.php')) {
    include __DIR__.'/actions/checkout_payment.php';
}

if (str_contains($PHP_SELF, 'checkout_confirmation.php') && !empty($_SESSION['payment']) && $_SESSION['payment'] === $configHelper->getPaymentMethodName()) {
    $_POST['conditions'] = 1; //TODO
    $_POST['privacy'] = 1; //TODO
    include __DIR__.'/actions/checkout_confirmation.php';
}

if (str_contains($PHP_SELF, 'checkout_process.php') && !empty($_SESSION['payment']) && $_SESSION['payment'] === $configHelper->getPaymentMethodName()) {
    include __DIR__.'/actions/checkout_process.php';
}

if (str_contains($PHP_SELF, 'checkout_success.php')) {
    include __DIR__.'/actions/checkout_success.php';
}
