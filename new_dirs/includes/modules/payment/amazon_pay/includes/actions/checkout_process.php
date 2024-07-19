<?php

\AlkimAmazonPay\GeneralHelper::log('debug', 'start checkout_process');
if (isset($_POST['checkout_confirmation_comments'])) {
    $_SESSION['comments'] = $_POST['checkout_confirmation_comments'];
}

if(!empty($_GET['amazonCheckoutSessionId'])){
    $_SESSION['amazon_checkout_session'] = $_GET['amazonCheckoutSessionId'];
}

$checkoutHelper = new \AlkimAmazonPay\CheckoutHelper();
$configHelper   = new \AlkimAmazonPay\ConfigHelper();

if (empty($_SESSION['amazon_checkout_session'])) {
    //APB
    xtc_redirect(xtc_href_link('amazon_pay_checkout.php', '', 'SSL'));
}

$checkoutSession = $checkoutHelper->getCheckoutSession($_SESSION['amazon_checkout_session']);

if (!$checkoutSession || !$checkoutSession->getCheckoutSessionId()) {
    \AlkimAmazonPay\GeneralHelper::log('warning', 'invalid amazon checkout session id', [$_SESSION['amazon_checkout_session'], $checkoutSession]);
    xtc_redirect(xtc_href_link(FILENAME_SHOPPING_CART));
}
\AlkimAmazonPay\GeneralHelper::log('debug', 'checkout_process CheckoutSession', [$checkoutSession->toArray()]);

if ($checkoutSession->getStatusDetails()->getState() === \AmazonPayApiSdkExtension\Struct\StatusDetails::OPEN) {
    if ($checkoutSession->getWebCheckoutDetails()->getAmazonPayRedirectUrl() && !$checkoutSession->getConstraints()) {
        //do checkout
    } else {
        //redirect to amazon
        $checkoutHelper->doUpdateCheckoutSessionBeforeCheckoutProcess($checkoutSession);
    }
} else {
    if ($checkoutSession->getStatusDetails()->getState() === \AmazonPayApiSdkExtension\Struct\StatusDetails::CANCELED) {
        \AlkimAmazonPay\GeneralHelper::log('debug', 'amazon pay payment cancelled', $checkoutSession->toArray());
        $checkoutHelper->defaultErrorHandling();
    } else {
        \AlkimAmazonPay\GeneralHelper::log('warning', 'amazon pay payment in unexpected state', $checkoutSession->toArray());
        xtc_redirect(xtc_href_link(FILENAME_CHECKOUT_PAYMENT, 'amazon_pay_error=1'));
    }
}