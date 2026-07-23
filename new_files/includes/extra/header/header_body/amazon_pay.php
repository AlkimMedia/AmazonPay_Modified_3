<?php
if(defined('MODULE_PAYMENT_AMAZON_PAY_STATUS') && MODULE_PAYMENT_AMAZON_PAY_STATUS == 'True') {
    if (empty($smarty) || empty($last_order) || empty($PHP_SELF)) {
        return;
    }
    if (strpos($PHP_SELF, "checkout_success.php") === false) {
        return;
    }

    $processAmazonPayAddition = function () use ($smarty, $last_order) {
        $q = "SELECT * FROM " . TABLE_ORDERS . " WHERE orders_id = " . (int)$last_order;
        $rs = xtc_db_query($q);
        if ($order = xtc_db_fetch_array($rs)) {
            if ($order['payment_method'] !== 'amazon_pay') {
                return;
            }
            $transactions = (new \AlkimAmazonPay\Helpers\TransactionHelper())->getTransactionsByOrderId($last_order);
            if (empty($transactions)) {
                $smarty->assign('amazonPayError', TEXT_AMAZON_PAY_CHECKOUT_SUCCESS_ERROR);
            }
        }
    };

    $processAmazonPayAddition();
}
