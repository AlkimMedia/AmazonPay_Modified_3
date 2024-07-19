<?php

namespace AlkimAmazonPay;

use AlkimAmazonPay\Helpers\TransactionHelper;
use AlkimAmazonPay\Models\Transaction;
use AmazonPayApiSdkExtension\Struct\CaptureAmount;
use AmazonPayApiSdkExtension\Struct\Charge;
use AmazonPayApiSdkExtension\Struct\StatusDetails;

class OrderHelper
{
    public function doShippingCapture(): void
    {
        if (ConfigHelper::getConstant('APC_CAPTURE_MODE') === 'after_shipping' && ConfigHelper::isConstantTrue('MODULE_PAYMENT_AMAZON_PAY_STATUS')) {
            $q = "SELECT DISTINCT a.* FROM " . TABLE_ORDERS . " o
                            JOIN amazon_pay_transactions AS a ON (o.orders_id = a.order_id AND a.type = 'Charge' AND a.status = '" . StatusDetails::AUTHORIZED . "')
                    WHERE
                        o.payment_method = 'amazon_pay'
                            AND
                        o.orders_status = '" . xtc_db_input(ConfigHelper::getConstant('APC_ORDER_STATUS_SHIPPED')) . "'";
            $rs = xtc_db_query($q);
            $amazonPayHelper = new AmazonPayHelper();
            $transactionHelper = new TransactionHelper();
            $apiClient = $amazonPayHelper->getClient();
            while ($r = xtc_db_fetch_array($rs)) {
                $chargeTransaction = new Transaction($r);
                $originalCharge = $apiClient->getCharge($chargeTransaction->reference);

                $captureCharge = new Charge();
                $amount = new CaptureAmount($originalCharge->getChargeAmount()->toArray());
                $captureCharge->setCaptureAmount($amount);
                $captureCharge = $apiClient->captureCharge($originalCharge->getChargeId(), $captureCharge);
                $transactionHelper->updateCharge($captureCharge);
            }
        }
    }

    public function setOrderStatusAuthorized($orderId): void
    {
        self::setOrderStatus($orderId, ConfigHelper::getConstant('APC_ORDER_STATUS_AUTHORIZED', 0), 'Amazon Pay - authorize');
    }

    public function setOrderStatus($orderId, $status, $comment = ''): void
    {
        $orderId = (int)$orderId;
        $status = (int)$status;
        if ($status <= 0) {
            $q = "SELECT orders_status FROM " . TABLE_ORDERS . " WHERE orders_id = " . $orderId;
            $rs = xtc_db_query($q);
            if ($r = xtc_db_fetch_array($rs)) {
                $status = (int)$r["orders_status"];
            } else {
                return;
            }
        }

        $q = "SELECT * FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = " . $orderId . " AND orders_status_id = " . $status;
        $rs = xtc_db_query($q);
        if (xtc_db_num_rows($rs)) {
            //already set
            return;
        }

        $data = [
            'orders_id' => $orderId,
            'orders_status_id' => $status,
            'date_added' => 'now()',
            'customer_notified' => 0,
            'comments' => $comment,
        ];
        xtc_db_perform(TABLE_ORDERS_STATUS_HISTORY, $data);
        $q = "UPDATE " . TABLE_ORDERS . " SET orders_status = " . $status . " WHERE orders_id = " . $orderId;
        xtc_db_query($q);
    }

    public function setOrderStatusDeclined($orderId): void
    {
        self::setOrderStatus($orderId, ConfigHelper::getConstant('APC_ORDER_STATUS_DECLINED', 0), 'Amazon Pay - declined');
    }

    public function setOrderStatusCaptured($orderId): void
    {
        self::setOrderStatus($orderId, ConfigHelper::getConstant('APC_ORDER_STATUS_CAPTURED', 0), 'Amazon Pay - captured');
    }
}