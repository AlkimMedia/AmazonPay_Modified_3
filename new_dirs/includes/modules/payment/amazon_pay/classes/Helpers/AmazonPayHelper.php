<?php declare(strict_types=1);

namespace AlkimAmazonPay;

use AmazonPayApiSdkExtension\Client\Client;
use Exception;

class AmazonPayHelper
{
    private static Client $client;

    private ConfigHelper $configHelper;

    public function __construct()
    {
        $this->configHelper = new ConfigHelper();
    }

    public function getClient($forceSandbox = null): Client
    {
        $config = $this->configHelper->getMainConfig();
        if($forceSandbox !== null) {
            $config['sandbox'] = $forceSandbox;
        }
        if (!isset(self::$client)) {
            try {
                self::$client = new Client($config);
            } catch (Exception $e) {
                GeneralHelper::log('error', 'Unable to get client', $e->getMessage());
            }
        }

        return self::$client;
    }

    public function getHeaders(): array
    {
        return ['x-amz-pay-Idempotency-Key' => uniqid()];
    }
}