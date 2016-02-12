<?php

/*
NOTES, discuss / review
- For users with account in store we update billing info and create new shipping address
- Every time we edit user we add klarna address (so maybe we create some duplicates). TODO check if exist before create new one.
- Check customer associate to website (maybe we could add reve)
- Add currency code, shipping method and payment method in order.
- Using sku to find products
- TODO Create an error handler
- TODO Add custom atribute (klarna_order) to order and check if order already exist before create a new one.
*/

require_once 'env.php';

require_once '../app/Mage.php';
umask(0);
Mage::app('default');

require_once 'OrderGenerator.php';
require_once 'CustomerGenerator.php';

// get url params
//$isReve = $_GET['reve'];
//$transaction = $_GET['transaction'];
$klarna_order = $_GET['klarna_order'];

// load klarna order
$curl = curl_init();

$header = array();
$header[] = 'Authorization: Klarna ' . $klarna_auth;
$header[] = 'Accept: application/vnd.klarna.checkout.aggregated-order-v2+json';

curl_setopt($curl, CURLOPT_URL, 'https://checkout.testdrive.klarna.com/checkout/orders/' . $klarna_order );
curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$order = curl_exec($curl);
curl_close($curl);

$orderData = json_decode($order, true);

// match klarna data with magento structure
$user = $orderData['shipping_address'];
$cart = $orderData['cart']['items'];

$customerData = array (
      'account' => array(
          'website_id' => '1',
          'group_id' => '1',
          'prefix' => '',
          'firstname' => $user['given_name'],
          'middlename' => '',
          'lastname' => $user['family_name'],
          'suffix' => '',
          'email' => $user['email'],
          'dob' => '',
          'taxvat' => '',
          'gender' => '',
          'sendemail_store_id' => '1',
          'password' => rand(10000000,99999999),
          'default_billing' => '_item1',
          'default_shipping' => '_item1',
      ),
      'address' => array(
          '_item1' => array(
              'prefix' => '',
              'firstname' => $user['given_name'],
              'middlename' => '',
              'lastname' => $user['family_name'],
              'suffix' => '',
              'company' => '',
              'street' => array(
                  0 => $user['street_address'],
                  1 => '',
              ),
              'city' => $user['city'],
              'country_id' => strtoupper($user['country']),
              'region_id' => '',
              'region' => '',
              'postcode' => $user['postal_code'],
              'telephone' => $user['phone'],
              'fax' => '',
              'vat_id' => '',
          ),
      ),
  );

// create or update customer account
$customerGenerator = new CustomerGenerator();
$customerGenerated = $customerGenerator->createCustomer($customerData);
$customerId = $customerGenerated->getId();

// create order
$orderGenerator = new OrderGenerator();
$orderGenerator->setCustomer($customerId);

$newOrder = array();
foreach ($cart as $key => $prod) {
  $ord = array(
      'product' => $prod['reference'],
      'qty' => $prod['quantity']
  );

  /**
    * TODO add product attr or options
    * we use $prod['merchant_item_data'] to store atributes and result will be like
    * "merchant_item_data": "size:L;color:Blue;attr_x:y;"
  **/
  /*
  if(isset($prod['merchant_item_data'])){
    $attrs = explode(';', $prod['merchant_item_data']);
    // remove last array value because is set to ''
    array_pop($attrs);
    $options = array();
    foreach ($attrs as $key => $attr) {
      $attrData = explode(':', $attr);
      array_push($options, $attrData);
    }

    var_dump($options);
    $ord['options'] = $options;
  }*/

  array_push($newOrder, $ord);
};

$orderGenerator->createOrder($newOrder);
