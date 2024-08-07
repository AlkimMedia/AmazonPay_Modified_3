<?php

include 'includes/application_top.php';

if (empty($_GET['buyerToken'])) {
    xtc_redirect(xtc_href_link(FILENAME_DEFAULT));
}

$accountHelper   = new \AlkimAmazonPay\AccountHelper();
$checkoutHelper  = new \AlkimAmazonPay\CheckoutHelper();
$configHelper    = new \AlkimAmazonPay\ConfigHelper();
$amazonPayHelper = new \AlkimAmazonPay\AmazonPayHelper;
$token           = $_GET['buyerToken'];
$buyer = $amazonPayHelper->getClient()->getBuyer($token);

$q  = "SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . xtc_db_input($buyer->getEmail()) . "' and account_type = '0'";
$rs = xtc_db_query($q);
if ($r = xtc_db_fetch_array($rs)) {
    $accountHelper->doLogin($r['customers_id']);
} else {
    require_once DIR_FS_INC . 'xtc_create_password.inc.php';
    $password = xtc_create_password(32);
    $names    = explode(' ', $buyer->getName());
    if (count($names) > 1) {
        $lastName  = array_pop($names);
        $firstName = implode(' ', $names);
    } else {
        $lastName  = $buyer->getName();
        $firstName = '';
    }
    $sql_data_array = [
        'customers_status' => DEFAULT_CUSTOMERS_STATUS_ID,
        'customers_gender' => '',
        'customers_firstname' => $firstName,
        'customers_lastname' => $lastName,
        'customers_dob' => '0000-00-00 00:00:00',
        'customers_email_address' => $buyer->getEmail(),
        'customers_default_address_id' => '0',
        'customers_telephone' => '',
        'customers_password' => $password,
        'customers_newsletter' => 0,
        'member_flag' => 0,
        'delete_user' => 0,
        'account_type' => 0,
    ];
    xtc_db_perform(TABLE_CUSTOMERS, $sql_data_array);
    $customerId = xtc_db_insert_id();
    xtc_db_perform(TABLE_CUSTOMERS_INFO, [
        'customers_info_id' => $customerId,
    ]);
    
    $address = $buyer->getShippingAddress();
    $addressBookSqlArray = $accountHelper->convertAddressToArray($address);
    
    $addressId = (int)$accountHelper->createAddress($address, $customerId);
    xtc_db_perform(TABLE_CUSTOMERS, ['customers_default_address_id' => $addressId], 'update', 'customers_id = ' . (int)$customerId);

    $accountHelper->doLogin($customerId);
}

xtc_redirect(xtc_href_link('account.php'));
