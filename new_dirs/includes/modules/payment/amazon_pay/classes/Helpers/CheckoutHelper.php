<?php

namespace AlkimAmazonPay;

use AmazonPayApiSdkExtension\Struct\AddressDetails;
use AmazonPayApiSdkExtension\Struct\AddressRestrictions;
use AmazonPayApiSdkExtension\Struct\CheckoutSession;
use AmazonPayApiSdkExtension\Struct\DeliverySpecifications;
use AmazonPayApiSdkExtension\Struct\MerchantMetadata;
use AmazonPayApiSdkExtension\Struct\PaymentDetails;
use AmazonPayApiSdkExtension\Struct\Price;
use AmazonPayApiSdkExtension\Struct\WebCheckoutDetails;
use order;
use order_total;
use shipping;

class CheckoutHelper
{

    private AmazonPayHelper $amazonPayHelper;

    private ConfigHelper $configHelper;

    public function __construct()
    {
        $this->amazonPayHelper = new AmazonPayHelper();
        $this->configHelper = new ConfigHelper();
    }

    public function getNewCheckoutSessionObject():CheckoutSession{
        $storeName = ConfigHelper::getConstant('STORE_NAME', '');
        $encoding = mb_detect_encoding($storeName, ['UTF-8', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-15']);
        if($encoding !== 'UTF-8'){
            $storeName = mb_convert_encoding($storeName, 'UTF-8', $encoding);
        }
        $storeName = (mb_strlen($storeName) <= 50) ? $storeName : (mb_substr($storeName, 0, 47) . '...');

        $merchantData = new MerchantMetadata();
        $merchantData->setMerchantStoreName($storeName);
        $merchantData->setCustomInformation($this->configHelper->getCustomInformationString());

        $webCheckoutDetails = new WebCheckoutDetails();
        $webCheckoutDetails->setCheckoutReviewReturnUrl(xtc_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));

        $addressRestrictions = new AddressRestrictions();
        $addressRestrictions->setType('Allowed')
            ->setRestrictions($this->configHelper->getAllowedCountries());
        $deliverySpecifications = new DeliverySpecifications();
        $deliverySpecifications->setAddressRestrictions($addressRestrictions);

        $checkoutSession = new CheckoutSession();
        $checkoutSession->setMerchantMetadata($merchantData)
            ->setWebCheckoutDetails($webCheckoutDetails)
            ->setStoreId($this->configHelper->getClientId())
            ->setPlatformId($this->configHelper->getPlatformId())
            ->setDeliverySpecifications($deliverySpecifications);
        return $checkoutSession;
    }

    public function getApbCheckoutSessionObject(){
        global $order;
        $checkoutSession = $this->getNewCheckoutSessionObject();
        $checkoutSession->getWebCheckoutDetails()
            ->setCheckoutMode(WebCheckoutDetails::CHECKOUT_MODE_PROCESS_ORDER)
            ->setCheckoutResultReturnUrl($this->configHelper->getCheckoutResultReturnUrl())
            ->setCheckoutReviewReturnUrl(null);

        $paymentDetails = new PaymentDetails();
        $paymentDetails
            ->setPaymentIntent('Authorize')
            ->setCanHandlePendingAuthorization($this->configHelper->canHandlePendingAuth())
            ->setChargeAmount(new Price(['amount' => $order->info['total'], 'currencyCode' => $order->info['currency']]));
        $checkoutSession->setPaymentDetails($paymentDetails);


        $address = new AddressDetails();
        $address->setName($order->delivery['firstname'] . ' ' . $order->delivery['lastname'])
            ->setAddressLine1((string)(!empty($order->delivery['company'])?$order->delivery['company']:$order->delivery['street_address']))
            ->setAddressLine2((string)(!empty($order->delivery['company'])?$order->delivery['street']:$order->delivery['suburb']))
            ->setAddressLine3((string)(!empty($order->delivery['company'])?$order->delivery['suburb']:''))
            ->setCity($order->delivery['city'])
            ->setPostalCode($order->delivery['postcode'])
            ->setCountryCode($order->delivery['country']['iso_code_2'])
            ->setPhoneNumber($order->customer['telephone']?:'00000');
        $checkoutSession->setAddressDetails($address);

        return $checkoutSession;
    }



    public function createCheckoutSession():?CheckoutSession
    {
        try {
            return $this->amazonPayHelper->getClient()->createCheckoutSession($this->getNewCheckoutSessionObject());
        } catch (\Exception $e) {
            GeneralHelper::log('error', 'createCheckoutSession failed', $e->getMessage());
        }
        return null;
    }

    public function getCheckoutSession($checkoutSessionId):?CheckoutSession
    {
        try {
            return $this->amazonPayHelper->getClient()->getCheckoutSession($checkoutSessionId);
        } catch (\Exception $e) {
            GeneralHelper::log('error', 'getCheckoutSession failed', [$e->getMessage(), $checkoutSessionId]);
        }
        return null;
    }

    public function updateCheckoutSession($checkoutSessionId, CheckoutSession $checkoutSession)
    {
        try {
            return $this->amazonPayHelper->getClient()->updateCheckoutSession($checkoutSessionId, $checkoutSession);
        } catch (\Exception $e) {
            GeneralHelper::log('error', 'updateCheckoutSession failed', [$e->getMessage(), $checkoutSessionId, $checkoutSession]);
        }
        return null;
    }

    public function setOrderIdToChargePermission($chargePermissionId, $orderId):void
    {

        $this->amazonPayHelper->getClient()->updateChargePermission(
            $chargePermissionId,
            ['merchantMetadata' => ['merchantReferenceId' => $orderId]]
        );
    }

    protected function getCachedSignature($payload):string
    {
        $storageKey = 'apcv2_button_signature_' . md5(serialize([$this->configHelper->getMainConfig(), $payload]));
        $cacheFile = DIR_FS_CATALOG . 'cache/' . $storageKey;
        if (file_exists($cacheFile) && filemtime($cacheFile) > time() - 28800) {
            return file_get_contents($cacheFile);
        }

        $client = $this->amazonPayHelper->getClient();
        $signature = $client->generateButtonSignature($payload);
        file_put_contents($cacheFile, $signature);
        return $signature;
    }

    public function getJs($placement = 'Cart'):string
    {
        if (!$this->configHelper->isActive()) {
            return '';
        }
        $loginPayload = json_encode([
            'signInReturnUrl' => xtc_href_link('amazon_pay_login.php'),
            'storeId' => $this->configHelper->getClientId(),
            'signInScopes' => ["name", "email", "postalCode", "shippingAddress"],
        ]);

        $productType = 'PayAndShip';
        if ($_SESSION['cart']->count_contents() > 0) {
            if ($_SESSION['cart']->get_content_type() === 'virtual' || $_SESSION['cart']->count_contents_virtual() == 0) {
                $productType = 'PayOnly';
            }
        }
        $loginSignature = $this->getCachedSignature($loginPayload);
        $useCreditUrl = xtc_href_link('callback/amazon_pay/use_credit.php', '', 'SSL');

        global $product, $PHP_SELF;

        if ($product && $product->isProduct && str_contains($PHP_SELF, 'product_info.php')) {
            global $xtPrice;
            $productPrice = $xtPrice->xtcGetPrice($product->pID, false, 1, $product->data['products_tax_class_id'], $product->data['products_price']);
        } else {
            $productPrice = 0;
        }

        $estimatedOrderAmount = json_encode([
            'amount' => (string)($_SESSION['cart']->show_total()),
            'currencyCode' => $_SESSION['currency'],
        ]);
        $estimatedOrderAmountInclProduct = json_encode([
            'amount' => (string)($_SESSION['cart']->show_total() + $productPrice),
            'currencyCode' => $_SESSION['currency'],
        ]);

        $amazonPayParameters = [
            'merchantId' => $this->configHelper->getMerchantId(),
            'createCheckoutSessionUrl' => $this->configHelper->getCheckoutSessionAjaxUrl(),
            'isSandbox' => $this->configHelper->isSandbox(),
            'language' => $this->configHelper->getLanguage(),
            'ledgerCurrency' => $this->configHelper->getLedgerCurrency(),
            'checkoutSessionId' => (!empty($_SESSION['amazon_checkout_session']) ? $_SESSION['amazon_checkout_session'] : ''),
            'jsPath' => DIR_WS_CATALOG . 'includes/modules/payment/amazon_pay/js/amazon-pay.js',
            'checkoutButtonColor' => ConfigHelper::getConstant('APC_CHECKOUT_BUTTON_COLOR'),
            'loginButtonColor' => ConfigHelper::getConstant('APC_LOGIN_BUTTON_COLOR'),
            'loginPayload' => $loginPayload,
            'loginSignature' => $loginSignature,
            'publicKeyId' => $this->configHelper->getPublicKeyId(),
            'productType' => $productType,
            'useCreditUrl' => $useCreditUrl,
            'estimatedOrderAmount' => $estimatedOrderAmount,
            'estimatedOrderAmountInclProduct' => $estimatedOrderAmountInclProduct,
            'placement' => $placement,
        ];

        $amazonPayParametersJson = json_encode($amazonPayParameters);

        $return = '<script src="https://static-eu.payments-amazon.com/checkout.js"></script>'.
            '<script>const amazonPayParameters = ' . $amazonPayParametersJson . ';</script>'.
            '<script src="' . DIR_WS_CATALOG . 'includes/modules/payment/amazon_pay/js/amazon-pay.js"></script>';


        if(str_contains($PHP_SELF, 'amazon_pay_checkout.php')){
            //APB
            $checkoutSession = $this->getApbCheckoutSessionObject();
            $createCheckoutSessionPayload = stripcslashes(json_encode($checkoutSession->toArray(), JSON_UNESCAPED_UNICODE));
            $createCheckoutSessionSignature = $this->amazonPayHelper->getClient()->generateButtonSignature($createCheckoutSessionPayload);
//var_dump($checkoutSession->toArray());die;
            $createCheckoutSessionConfig = [
                'payloadJSON' => $createCheckoutSessionPayload,
                'signature' => $createCheckoutSessionSignature,
                'publicKeyId' => $this->configHelper->getPublicKeyId(),
            ];
            $return .= '<script>alkimAmazonPay.doApbCheckout('.json_encode($createCheckoutSessionConfig).');</script>';
        }


        if ($this->configHelper->isDebugMode()) {
            $return .= '<style>.amazon-login-button, .amazon-pay-button, #amazon-pay-button-manual, #amazon-pay-button-product-info{display:none;}</style>';
        }

        return $return;
    }

    /**
     * @param $checkoutSession
     */
    public function doUpdateCheckoutSessionBeforeCheckoutProcess($checkoutSession)
    {
        if (!empty($_SESSION['amazon_pay_checkout_no_pay'])) {
            unset($_SESSION['amazon_pay_checkout_no_pay']);
            return;
        }
        global $order, $order_totals, $shipping_modules, $order_total_modules;
        require_once DIR_WS_CLASSES . 'payment.php';
        require_once DIR_WS_CLASSES . 'shipping.php';
        $shipping_modules = new shipping($_SESSION['shipping']);
        require_once DIR_WS_CLASSES . 'order.php';
        $order = new order();
        require_once DIR_WS_CLASSES . 'order_total.php';
        $order_total_modules = new order_total();
        $order_totals = $order_total_modules->process();

        if ($order->info['total'] <= 0) {
            $_SESSION['amazon_pay_checkout_no_pay'] = 1;
            xtc_redirect(xtc_href_link(FILENAME_CHECKOUT_PROCESS));
        }
        $checkoutSessionUpdate = new CheckoutSession();

        $webCheckoutDetails = new WebCheckoutDetails();
        $webCheckoutDetails->setCheckoutResultReturnUrl($this->configHelper->getCheckoutResultReturnUrl());

        $paymentDetails = new PaymentDetails();
        $paymentDetails
            ->setPaymentIntent('Authorize')
            ->setCanHandlePendingAuthorization($this->configHelper->canHandlePendingAuth())
            ->setChargeAmount(new Price(['amount' => $order->info['total'], 'currencyCode' => $order->info['currency']]));

        $checkoutSessionUpdate
            ->setWebCheckoutDetails($webCheckoutDetails)
            ->setPaymentDetails($paymentDetails);
        $updatedCheckoutSession = $this->updateCheckoutSession($checkoutSession->getCheckoutSessionId(), $checkoutSessionUpdate);

        if ($redirectUrl = $updatedCheckoutSession->getWebCheckoutDetails()->getAmazonPayRedirectUrl()) {
            xtc_redirect($redirectUrl);
        } else {
            GeneralHelper::log('warning', 'updateCheckoutSession failed', $checkoutSessionUpdate);
            xtc_redirect(xtc_href_link(FILENAME_CHECKOUT_CONFIRMATION, 'amazon_pay_error', 'SSL'));
        }
    }

    public function defaultErrorHandling()
    {
        unset($_SESSION['payment']);
        xtc_redirect(xtc_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->configHelper->getPaymentMethodName()));
    }
}
