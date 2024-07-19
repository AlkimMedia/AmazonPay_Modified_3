<?php

namespace AlkimAmazonPay;

use phpseclib3\Crypt\RSA;
use stdClass;

class ConfigHelper
{
    const FIELD_TYPE_STRING = 'string';
    const FIELD_TYPE_SELECT = 'select';
    const FIELD_TYPE_BOOL = 'bool';
    const FIELD_TYPE_READ_ONLY = 'read_only';
    const FIELD_TYPE_STATUS = 'status';
    const FIELD_TYPE_HEADING = 'heading';

    public Config $config;

    public function getMainConfig(): array
    {
        return [
            'public_key_id' => self::getConstant('APC_PUBLIC_KEY_ID'),
            'private_key' => $this->getPrivateKeyPath(),
            'region' => self::getConstant('APC_REGION'),
            'sandbox' => $this->isSandbox(),
            'integrator_id' => $this->getPlatformId(),
            'integrator_version' => $this->getPluginVersion(),
            'platform_version' => $this->getShopVersion(),
        ];
    }

    public function getShopVersion(): ?string
    {
        if (!defined('PROJECT_MAJOR_VERSION')) {
            require_once DIR_FS_CATALOG . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'includes/version.php';
        }

        $version = PROJECT_MAJOR_VERSION . '.' . PROJECT_MINOR_VERSION;
        if (preg_match('/^(\d+\.\d+\.\d+)/', $version, $match)) {
            return $match[1];
        }
        return null;
    }

    public function getPrivateKeyPath(): string
    {
        return $this->getBasePath() . 'keys/private.pem';
    }

    public function getBasePath(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    public function isSandbox(): bool
    {
        return !self::isConstantTrue('APC_IS_LIVE');
    }

    public function getCheckoutResultReturnUrl(): string
    {
        return xtc_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL');
    }

    public function getCheckoutSessionAjaxUrl(): string
    {
        return xtc_href_link('callback/amazon_pay/create_checkout_session.php', '', 'SSL');
    }

    public function getLanguage(): string
    {
        $supportedLanguages = [
            'en' => 'en_GB',
            'de' => 'de_DE',
            'fr' => 'fr_FR',
            'it' => 'it_IT',
            'es' => 'es_ES',
        ];
        return $supportedLanguages[$_SESSION['language_code']] ?? 'de_DE';
    }

    public function getPaymentMethodName(): string
    {
        return 'amazon_pay';
    }

    public function isDebugMode(): bool
    {
        return self::getConstant('APC_IS_DEBUG') === 'True';
    }

    public function isActive(): bool
    {
        return self::getConstant('MODULE_PAYMENT_AMAZON_PAY_STATUS') === 'True';
    }

    public function getMerchantId(): ?string
    {
        return self::getConstant('APC_MERCHANT_ID');
    }

    public function getClientId(): ?string
    {
        return self::getConstant('APC_CLIENT_ID');
    }

    public function getConfigurationFields(): array
    {
        $this->initKey();
        return [
            'HEADING_CREDENTIALS' => [
                'type' => static::FIELD_TYPE_HEADING,
            ],
            'APC_REGION' => [
                'type' => static::FIELD_TYPE_SELECT,
                'options' => [
                    ['text' => 'EU', 'id' => 'EU'],
                    ['text' => 'UK', 'id' => 'UK'],
                ],
            ],
            'APC_MERCHANT_ID' => [
                'type' => static::FIELD_TYPE_STRING,
            ],
            'APC_CLIENT_ID' => [
                'type' => static::FIELD_TYPE_STRING,
            ],
            'APC_PUBLIC_KEY_ID' => [
                'type' => static::FIELD_TYPE_STRING,
            ],
            'APC_PUBLIC_KEY' => [
                'type' => static::FIELD_TYPE_READ_ONLY,
                'value' => '<pre>' . file_get_contents($this->getPublicKeyPath()) . '</pre>
                          <div><a href="https://sellercentral-europe.amazon.com/gp/pyop/seller/integrationcentral/" target="_blank">Public Key ID bei Amazon generieren</a></div>
                          <div><a href="' . xtc_href_link('amazon_pay_configuration.php', 'action=reset_key', 'SSL') . '">Keys zur&uuml;cksetzen</a></div>',
            ],
            'APC_IPN_URL' => [
                'type' => static::FIELD_TYPE_READ_ONLY,
                'value' => (defined('HTTPS_CATALOG_SERVER') ? HTTPS_CATALOG_SERVER : HTTPS_SERVER) . DIR_WS_CATALOG . 'callback/amazon_pay/ipn.php',
            ],
            'APC_CRON_STATUS' => [
                'type' => static::FIELD_TYPE_BOOL,
            ],
            'HEADING_GENERAL' => [
                'type' => static::FIELD_TYPE_HEADING,
            ],

            'MODULE_PAYMENT_AMAZON_PAY_STATUS' => [
                'type' => static::FIELD_TYPE_BOOL,
            ],
            'MODULE_PAYMENT_AMAZON_PAY_SORT_ORDER' => [
                'type' => static::FIELD_TYPE_STRING,
            ],
            'APC_IS_LIVE' => [
                'type' => static::FIELD_TYPE_SELECT,
                'options' => [
                    ['text' => 'Live', 'id' => 'True'],
                    ['text' => 'Sandbox', 'id' => 'False'],
                ],
            ],
            'APC_IS_DEBUG' => [
                'type' => static::FIELD_TYPE_BOOL,
            ],
            'MODULE_PAYMENT_AMAZON_PAY_ALLOWED' => [
                'type' => static::FIELD_TYPE_STRING,
            ],
            'APC_ORDER_STATUS_AUTHORIZED' => [
                'type' => static::FIELD_TYPE_STATUS,
            ],
            'APC_ORDER_STATUS_DECLINED' => [
                'type' => static::FIELD_TYPE_STATUS,
            ],
            'APC_ORDER_STATUS_CAPTURED' => [
                'type' => static::FIELD_TYPE_STATUS,
            ],
            'APC_AUTHORIZATION_MODE' => [
                'type' => static::FIELD_TYPE_SELECT,
                'options' => [
                    ['text' => 'nur autorisierte Bestellungen', 'id' => 'fast_auth'],
                    ['text' => 'asynchrone Autorisierung erlauben', 'id' => 'async'],
                ],
            ],
            'APC_CAPTURE_MODE' => [
                'type' => static::FIELD_TYPE_SELECT,
                'options' => [
                    ['text' => 'Manuell', 'id' => 'manually'],
                    ['text' => 'Nach Versand', 'id' => 'after_shipping'],
                    ['text' => 'Direkt nach Autorisierung', 'id' => 'after_auth'],
                ],
            ],
            'APC_ORDER_STATUS_SHIPPED' => [
                'type' => static::FIELD_TYPE_STATUS,
            ],
            'APC_ORDER_REFERENCE_IN_COMMENT' => [
                'type' => static::FIELD_TYPE_BOOL,
            ],
            'HEADING_STYLE' => [
                'type' => static::FIELD_TYPE_HEADING,
            ],

            'APC_CHECKOUT_BUTTON_COLOR' => [
                'type' => static::FIELD_TYPE_SELECT,
                'options' => [
                    ['text' => 'Gold', 'id' => 'Gold'],
                    ['text' => 'LightGray', 'id' => 'LightGray'],
                    ['text' => 'DarkGray', 'id' => 'DarkGray'],
                ],
            ],
            'APC_LOGIN_BUTTON_COLOR' => [
                'type' => static::FIELD_TYPE_SELECT,
                'options' => [
                    ['text' => 'Gold', 'id' => 'Gold'],
                    ['text' => 'LightGray', 'id' => 'LightGray'],
                    ['text' => 'DarkGray', 'id' => 'DarkGray'],
                ],
            ],
        ];
    }

    public function getPublicKeyPath(): string
    {
        return $this->getBasePath() . 'keys/public.pub';
    }

    public function getConfigurationValue($key)
    {
        $q = "SELECT * FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='" . xtc_db_input($key) . "'";
        $rs = xtc_db_query($q);
        if ($r = xtc_db_fetch_array($rs)) {
            return $r['configuration_value'];
        } else {
            return null;
        }
    }

    public function addConfigurationValue($key, $value): void
    {
        $q = "SELECT * FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='" . xtc_db_input($key) . "'";
        $rs = xtc_db_query($q);
        if (xtc_db_num_rows($rs) > 0) {
            $this->updateConfigurationValue($key, $value);
        } else {
            xtc_db_perform(TABLE_CONFIGURATION, [
                'configuration_key' => $key,
                'configuration_value' => $value,
            ]);
        }
    }

    public function getAllowedCountries(): array
    {
        $return = [];
        $q = "SELECT countries_iso_code_2 AS iso FROM " . TABLE_COUNTRIES . " WHERE status = '1'";
        $rs = xtc_db_query($q);
        while ($r = xtc_db_fetch_array($rs)) {
            $return[$r['iso']] = new stdClass();
        }
        foreach (['TP', 'ZR', 'CS'] as $notExistingCountry) {
            if (isset($return[$notExistingCountry])) {
                unset($return[$notExistingCountry]);
            }
        }

        return $return;
    }

    public function initKey(): void
    {
        if (!file_exists($this->getPrivateKeyPath()) || !file_exists($this->getPublicKeyPath())) {
            $this->resetKey();
        }
    }

    public function resetKey(): bool
    {

        if ($privateKeyObject = RSA::createKey(2048)) {
            $privateKey = $privateKeyObject->toString('PKCS1');
            /** @var RSA\PublicKey $publicKeyObject */
            $publicKeyObject = $privateKeyObject->getPublicKey();
            $publicKey = $publicKeyObject->toString('PKCS8');
            file_put_contents($this->getPrivateKeyPath(), $privateKey);
            file_put_contents($this->getPublicKeyPath(), $publicKey);
            $this->updateConfigurationValue('APC_PUBLIC_KEY_ID', '');
            return true;
        }
        return false;
    }

    public function updateConfigurationValue($key, $value): void
    {
        xtc_db_perform(
            TABLE_CONFIGURATION,
            [
                'configuration_value' => $value,
            ],
            'update',
            "configuration_key='" . xtc_db_input($key) . "'"
        );
    }

    public function getPublicKeyId(): ?string
    {
        return self::getConstant('APC_PUBLIC_KEY_ID');
    }

    public function getPlatformId(): string
    {
        return Config::PLATFORM_ID;
    }

    public function getPluginVersion(): string
    {
        return Config::PLUGIN_VERSION;
    }

    public function getCustomInformationString(): string
    {
        return 'Created by AlkimMedia, ' . Config::PLATFORM_NAME . ', V' . $this->getPluginVersion();
    }

    public function getLedgerCurrency(): string
    {
        return self::getConstant('APC_REGION') === 'UK' ? 'GBP' : 'EUR';
    }

    public function canHandlePendingAuth(): bool
    {
        return self::getConstant('APC_AUTHORIZATION_MODE') !== 'fast_auth';
    }

    public static function getConstant(string $name, $default = null)
    {
        return defined($name) ? constant($name) : $default;
    }

    public static function isConstantTrue(string $name): bool
    {
        return strtolower(self::getConstant($name, '')) === 'true';
    }
}
