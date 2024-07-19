<?php

namespace AlkimAmazonPay\Helpers;
use AlkimAmazonPay\AmazonPayHelper;
use AlkimAmazonPay\CheckoutHelper;
use AlkimAmazonPay\ConfigHelper;
use Exception;

class ValidationHelper
{
    const EXCEPTION_CODE_CONFIG = 1;
    const EXCEPTION_CODE_INVALID_KEY = 2;
    const EXCEPTION_CODE_INITIALIZE_CLIENT = 3;
    const EXCEPTION_CODE_CREATE_SESSION = 4;


    private AmazonPayHelper $amazonPayHelper;
    private ConfigHelper $configHelper;

    public function __construct()
    {
        $this->amazonPayHelper = new AmazonPayHelper();
        $this->configHelper = new ConfigHelper();
    }

    public function prepare(){
        defined('FILENAME_CHECKOUT_SHIPPING') || define('FILENAME_CHECKOUT_SHIPPING', 'checkout_shipping.php');
    }

    public function validate(): array
    {
        $this->prepare();
        $isSuccess = false;
        $exceptionMessage = '';
        $message = APC_VALIDATION_SUCCESS;
        try {
            $this->validateCredentials();
            $this->validatePrivateKey();
            $this->initializeClient();
            $this->createSession();
            $isSuccess = true;
        } catch (Exception $e) {
            switch ($e->getCode()) {
                case self::EXCEPTION_CODE_CONFIG:
                    $exceptionMessage = $message = APC_VALIDATION_CREDENTIALS_INCOMPLETE;
                    break;
                case self::EXCEPTION_CODE_INVALID_KEY:
                    $exceptionMessage = $message = APC_VALIDATION_INVALID_KEY;
                    break;
                case self::EXCEPTION_CODE_INITIALIZE_CLIENT:
                    $exceptionMessage = $message = APC_VALIDATION_INITIALIZE_CLIENT;
                    break;
                case self::EXCEPTION_CODE_CREATE_SESSION:
                    $message = APC_VALIDATION_CREATE_SESSION;
                    $exceptionMessage = $e->getMessage();
                    break;
                default:
                    $exceptionMessage = $message = $e->getMessage();
                    break;
            }
        }
        return [
            'success' => $isSuccess,
            'message' => $message,
            'exceptionMessage' => $exceptionMessage,
        ];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function validateCredentials(): void
    {
        if (empty($this->configHelper->getMerchantId())) {
            throw new Exception('merchantId', self::EXCEPTION_CODE_CONFIG);
        }

        if (empty($this->configHelper->getPublicKeyId())) {
            throw new Exception('publicKeyId', self::EXCEPTION_CODE_CONFIG);
        }

        if (empty($this->configHelper->getClientId())) {
            throw new Exception('storeId', self::EXCEPTION_CODE_CONFIG);
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function validatePrivateKey(): void
    {
        $keyContent = file_get_contents($this->configHelper->getPrivateKeyPath());
        if ((strpos($keyContent, 'BEGIN RSA PRIVATE KEY') === false) && (strpos($keyContent, 'BEGIN PRIVATE KEY') === false)) {
            throw new Exception('privateKey', self::EXCEPTION_CODE_INVALID_KEY);
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function initializeClient(): void
    {
        try{
            $this->amazonPayHelper->getClient(true);
        } catch (Exception $e) {
            var_dump($e->getMessage());die;
            throw new Exception($e->getMessage(), self::EXCEPTION_CODE_INITIALIZE_CLIENT);
        }
    }

    protected function createSession()
    {
        try {
            $checkoutHelper = new CheckoutHelper();
            $checkoutSession = $checkoutHelper->getNewCheckoutSessionObject();
            $session = $this->amazonPayHelper->getClient(true)->createCheckoutSession($checkoutSession);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'provided for PublicKeyId is invalid') !== false) {
                $msg = 'Die eingegebene Public Key ID ist nicht gültig';
            } elseif (strpos($msg, 'provided for \'storeId\'') !== false) {
                $msg = 'Die eingegebene Store-ID ist nicht gültig';
            } elseif (strpos($msg, 'Unable to verify signature') !== false) {
                $msg = 'Die eingegebene Public Key ID gehört nicht zum Public Key. Bitte eine neue Public Key ID in der Integration Central erzeugen.';
            } elseif (preg_match('/The value \'([^\']*)\' provided for \'addressRestrictions\' is invalid./', $msg, $matches)) {
                $msg = 'Es wurde ein ungültiger ISO Code für ein Land gefunden: ' . $matches[1];
            }

            throw new Exception($msg, self::EXCEPTION_CODE_CREATE_SESSION);
        }

        if (empty($session->getCheckoutSessionId())) {
            throw new Exception(print_r($session, true), self::EXCEPTION_CODE_CREATE_SESSION);
        }
    }
}