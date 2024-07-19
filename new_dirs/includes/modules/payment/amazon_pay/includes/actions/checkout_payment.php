<?php
$configHelper   = new \AlkimAmazonPay\ConfigHelper();
$_SESSION['amazon_pay_delivery_zip']     = null;
$_SESSION['amazon_pay_delivery_country'] = null;

if (isset($_SESSION['sendto']) && $_SESSION['sendto'] === false) {
    require_once (DIR_WS_CLASSES . 'order.php');
    $order = new order();
    if (!($order->content_type == 'virtual' || ($order->content_type == 'virtual_weight') || ($_SESSION['cart']->count_contents_virtual() == 0))) {
        unset($_SESSION['sendto']);
    }
}

if (!empty($_SESSION['sendto'])) {
    $q  = "SELECT entry_postcode, entry_country_id FROM " . TABLE_ADDRESS_BOOK . " WHERE address_book_id = " . (int)$_SESSION['sendto'];
    $rs = xtc_db_query($q);
    if ($r = xtc_db_fetch_array($rs)) {
        $_SESSION['amazon_pay_delivery_zip']     = $r['entry_postcode'];
        $_SESSION['amazon_pay_delivery_country'] = $r['entry_country_id'];
    }
}

if (isset($_GET['_action']) && $_GET['_action'] === 'reset_payment') {
    unset($_SESSION['payment']);
} elseif (!empty($_SESSION['payment']) && $_SESSION['payment'] === $configHelper->getPaymentMethodName() && empty($_GET['error_message']) && isset($_SESSION['sendto'])) {
    require_once (DIR_WS_CLASSES . 'order.php');
    $order = new order();
    if ($order->billing['country']['iso_code_2'] != '') {
	    $_SESSION['billing_zone'] = $order->billing['country']['iso_code_2'];
    }
    \AlkimAmazonPay\GeneralHelper::log('debug', 'skip checkout_payment');
    xtc_redirect(xtc_href_link('checkout_confirmation.php', '', 'SSL'));
}
