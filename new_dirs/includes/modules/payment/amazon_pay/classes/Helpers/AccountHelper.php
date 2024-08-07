<?php

namespace AlkimAmazonPay;

use AmazonPayApiSdkExtension\Struct\Address;

class AccountHelper
{

    public function convertAddressToArray(Address $address): array
    {
        $name = $address->getName();
        $t = explode(' ', $name);
        $lastNameKey = max(array_keys($t));
        $lastName = $t[$lastNameKey];
        unset($t[$lastNameKey]);
        $firstName = implode(' ', $t);

        if ($address->getAddressLine3() !== '') {
            $street = trim($address->getAddressLine3());
            $company = trim($address->getAddressLine1() . ' ' . $address->getAddressLine2());
        } elseif ($address->getAddressLine2() !== '') {
            $street = trim($address->getAddressLine2());
            $company = trim($address->getAddressLine1());
        } else {
            $street = trim($address->getAddressLine1());
            $company = '';
        }
        $sql = "SELECT countries_name, countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2 = '" . $address->getCountryCode() . "' LIMIT 1";
        $country_query = xtc_db_query($sql);
        $country_result = xtc_db_fetch_array($country_query);

        return [
            'name' => GeneralHelper::autoDecode($name),
            'firstname' => GeneralHelper::autoDecode($firstName),
            'lastname' => GeneralHelper::autoDecode($lastName),
            'company' => GeneralHelper::autoDecode($company),
            'phone' => GeneralHelper::autoDecode($address->getPhoneNumber()),
            'street_address' => GeneralHelper::autoDecode($street),
            'suburb' => '',
            'city' => GeneralHelper::autoDecode($address->getCity()),
            'postcode' => GeneralHelper::autoDecode($address->getPostalCode()),
            'state' => '',
            'country' => [
                'iso_code_2' => GeneralHelper::autoDecode($address->getCountryCode()),
                'title' => $country_result['countries_name'],
                'id' => $country_result["countries_id"],
            ],
            'country_iso_2' => GeneralHelper::autoDecode($address->getCountryCode()),
            'format_id' => '5',
        ];
    }


    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['customer_id']);
    }

    public function getStatusId(): int
    {
        return (int)$_SESSION['customers_status']['customers_status_id'];
    }

    public function isAccountComplete($customersId): ?bool
    {
        $q = "SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$customersId;
        $rs = xtc_db_query($q);
        if ($r = xtc_db_fetch_array($rs)) {
            if (empty($r['customers_firstname'])) {
                return false;
            }
            if (empty($r['customers_lastname'])) {
                return false;
            }
            if (ConfigHelper::isConstantTrue('ACCOUNT_DOB') && (empty($r['customers_dob']) || date('Y', strtotime($r['customers_dob'])) < 1900)) {
                return false;
            }
            if (strlen($r['customers_telephone']) < ConfigHelper::getConstant('ENTRY_TELEPHONE_MIN_LENGTH', 0) && !ConfigHelper::isConstantTrue('ACCOUNT_TELEPHONE_OPTIONAL')) {
                return false;
            }
            return true;
        } else {
            return null;
        }
    }

    public function hasAddress($customersId): bool
    {
        $q = "SELECT * FROM " . TABLE_CUSTOMERS . " c JOIN " . TABLE_ADDRESS_BOOK . " a ON (c.customers_default_address_id = a.address_book_id) WHERE c.customers_id = " . (int)$customersId;
        $rs = xtc_db_query($q);
        if (xtc_db_fetch_array($rs)) {
            return true;
        } else {
            $q = "SELECT * FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = " . (int)$customersId;
            $rs = xtc_db_query($q);
            if ($r = xtc_db_fetch_array($rs)) {
                xtc_db_query("UPDATE " . TABLE_CUSTOMERS . " c SET customers_default_address_id = " . (int)$r['address_book_id'] . " WHERE customers_id = " . (int)$customersId);
                $_SESSION['customer_default_address_id'] = (int)$r['address_book_id'];
                return true;
            } else {
                return false;
            }
        }
    }

    public function getAddressId(Address $address, $customerId = null): ?int
    {
        if (empty($customerId)) {
            $customerId = $_SESSION['customer_id'];
        }
        $addressArray = $this->convertAddressToArray($address);
        $q = "SELECT * FROM " . TABLE_ADDRESS_BOOK . " WHERE
                            customers_id = " . (int)$customerId . "
                                AND
                            entry_firstname = '" . xtc_db_input($addressArray['firstname']) . "'
                                AND
                            entry_lastname = '" . xtc_db_input($addressArray['lastname']) . "'
                                AND
                            entry_street_address = '" . xtc_db_input($addressArray['street_address']) . "'
                                AND
                            entry_postcode = '" . xtc_db_input($addressArray['postcode']) . "'
                                AND
                            entry_city = '" . xtc_db_input($addressArray['city']) . "'";
        $rs = xtc_db_query($q);
        if (xtc_db_num_rows($rs) > 0) {
            $r = xtc_db_fetch_array($rs);

            return (int)$r["address_book_id"];
        }

        return null;
    }

    public function createAddress(Address $address, $customerId = null): ?int
    {
        if (empty($customerId)) {
            $customerId = $_SESSION['customer_id'];
        }
        $addressArray = $this->convertAddressToArray($address);

        $address_book_sql_array = [
            'customers_id' => $customerId,
            'entry_firstname' => $addressArray['firstname'],
            'entry_lastname' => $addressArray['lastname'],
            'entry_company' => $addressArray['company'],
            'entry_suburb' => $addressArray['suburb'],
            'entry_street_address' => $addressArray["street_address"],
            'entry_postcode' => $addressArray['postcode'],
            'entry_city' => $addressArray['city'],
            'entry_country_id' => $addressArray['country']["id"],
        ];
        xtc_db_perform(TABLE_ADDRESS_BOOK, $address_book_sql_array);
        return xtc_db_insert_id() ?? null;
    }

    public function doLogin($customerId): void
    {
        $customerId = (int)$customerId;
        $q = "SELECT * FROM " . TABLE_CUSTOMERS . " c LEFT JOIN " . TABLE_ADDRESS_BOOK . " a ON (c.customers_default_address_id = a.address_book_id) WHERE c.customers_id = " . $customerId;
        $rs = xtc_db_query($q);
        if ($r = xtc_db_fetch_array($rs)) {
            $_SESSION['customer_gender'] = $r['customers_gender'];
            $_SESSION['customer_first_name'] = $r['customers_firstname'];
            $_SESSION['customer_last_name'] = $r['customers_lastname'];
            $_SESSION['customer_id'] = $r['customers_id'];
            $_SESSION['customer_vat_id'] = $r['customers_vat_id'];
            $_SESSION['customer_default_address_id'] = $r['customers_default_address_id'];
            $_SESSION['customer_country_id'] = $r['entry_country_id'];
            $_SESSION['customer_zone_id'] = $r['entry_zone_id'];
            $_SESSION['customer_email_address'] = $r['customers_email_address'];
            $_SESSION['customer_time'] = $r['customers_password_time'];
            $_SESSION['customer_id'] = $customerId;

            if ($_SESSION['customer_time'] == 0) {
                $_SESSION['customer_time'] = time();
                xtc_db_query("UPDATE " . TABLE_CUSTOMERS . " SET customers_password_time = '" . (int)$_SESSION['customer_time'] . "' WHERE customers_id = " . $customerId);
            }
        }
    }
}
