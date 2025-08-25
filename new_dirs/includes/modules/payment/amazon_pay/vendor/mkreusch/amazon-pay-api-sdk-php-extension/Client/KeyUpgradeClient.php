<?php

namespace AmazonPayApiSdkExtension\Client;


use Amazon\Pay\API\HttpCurl;

class KeyUpgradeClient
{
    const API_DOMAIN = 'pay-api.amazon.eu';
    const API_PATH = '/live/v2/publicKeyId';

    public function __construct()
    {
        $this->curl = new HttpCurl();
    }

    public function fetchPublicKeyId($merchantId, $accessKeyId, $secretKey, $newPublicKey)
    {
        $parameters = [
            'Action' => 'GetPublicKeyId',
            'PublicKey' => $newPublicKey,
            'MerchantId' => $merchantId,
            'AWSAccessKeyId' => $accessKeyId,
            'SignatureMethod' => 'HmacSHA256',
            'SignatureVersion' => '2',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $parameters['Signature'] = $this->calculateSignature($parameters, $secretKey);

        $response = $this->curl->invokeCurl(
            'GET',
            'https://' . static::API_DOMAIN . static::API_PATH . '?' . http_build_query($parameters),
            null,
            [
                'Content-Type: application/json',
            ]
        );
        $responseData = json_decode($response['response'], true);
        if (substr((string)$response['status'], 0, 1) !== '2') {
            throw new \Exception('Error fetching public key id: ' . $responseData['reasonCode'] . ' - ' . $responseData['message']);
        }
        return $responseData['publicKeyId'];
    }

    public function calculateSignature($parameters, $secretKey)
    {
        $signingParameters = $parameters;
        unset($signingParameters['PublicKey']);
        $signingParameters['SellerId'] = $signingParameters['MerchantId'];
        unset($signingParameters['MerchantId']);

        ksort($signingParameters);

        $stringToSign = 'GET' . "\n" .
            static::API_DOMAIN . "\n" .
            static::API_PATH . "\n" .
            http_build_query($signingParameters);

        return base64_encode(hash_hmac('sha256', $stringToSign, $secretKey, true));
    }
}
