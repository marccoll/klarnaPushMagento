<?php

require_once 'config.php';
require_once 'Klarna/Checkout.php';
require_once 'LogClass.php';

require_once $magePath;
umask(0);
Mage::app();

require_once 'OrderGenerator.php';
require_once 'CustomerGenerator.php';


// get url params
$storeID = $_GET['storeID'];
$klarna_order = $_GET['klarna_order'];
Log::add($klarna_order . ': push received');

// get klarna order
$connector = Klarna_Checkout_Connector::create(
    $klarna_secret,
    Klarna_Checkout_Connector::BASE_TEST_URL
);

$order = new Klarna_Checkout_Order($connector, $klarna_order);
try {
  $order->fetch();
} catch (Klarna_Checkout_ApiErrorException $e) {
  Log::add($klarna_order . ': error on klarna connection');
  var_dump($e->getMessage());
  var_dump($e->getPayload());
  die;
}

// check if order already exist
if($order['status'] == 'created'){
  Log::add($klarna_order . ': order already exist');
  die;
}else{
  Log::add($klarna_order . ': order not exist in system, processing');
  // match klarna data with magento structure
  $user = $order['shipping_address'];
  $cart = $order['cart']['items'];

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

  Log::add($klarna_order . ': user : ' . $customerId);

  // create order
  $orderGenerator = new OrderGenerator();
  if($storeID){
    $orderGenerator->setStoreId($storeID);
  }
  $orderGenerator->setShippingMethod($shippingMethodCode);
  $orderGenerator->setPaymentMethod($paymentMethodCode);
  $orderGenerator->setCustomer($customerId);

  $newOrder = array();

  foreach ($cart as $key => $prod) {
    $ord = array(
        'product' => $prod['reference'],
        'qty' => $prod['quantity']
    );

    // get conf product info and convert it into codes
    if(isset($prod['merchant_item_data'])){

      $attrs = explode(';', $prod['merchant_item_data']);
      // remove last array value because is set to ''
      array_pop($attrs);

      $sAttrs = array();
      foreach ($attrs as $key => $attr) {
        $attrData = explode(':', $attr);
        $label = $attrData[0];
        $value = $attrData[1];

        $attrInfo = getAttrInfo($label, $value, $sizeAttrNames);

        $ord['super_attribute'][intval($attrInfo['labelId'])] = intval($attrInfo['valueId']);
      }
    }

    array_push($newOrder, $ord);
  };


  if($orderGenerator->createOrder($newOrder)){
    Log::add($klarna_order . ': order created');

    $update['status'] = 'created';
    try {
      $order->update($update);
    } catch (Klarna_Checkout_ApiErrorException $e) {
      Log::add($klarna_order . ': error getting order from klarna');
      var_dump($e->getMessage());
      var_dump($e->getPayload());
    }
  }else{
    Log::add($klarna_order . ': something goes wrong creating order');
  }

}


// HELPERS
function getAttrInfo($label, $value, $sizeAttrNames){
  $attrInfo = Array();

  if($label == 'size'){
    foreach ($sizeAttrNames as $attrName) {
      $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attrName);
      $attrInfo['labelId'] = $attribute->getId();

      if ($attribute->usesSource()) {
        $attrInfo['valueId'] = $attribute->getSource()->getOptionId($value);
      }

      if($attrInfo['valueId']){
        break;
      }
    }

  }else{
    $attr = Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('frontend_label', $label);
    $attrInfo['labelId'] = $attr->getData()[0]['attribute_id'];
    // get value code
    $_product = Mage::getModel('catalog/product');
    $labelData = $_product->getResource()->getAttribute($label);
    if ($labelData->usesSource()) {
      $attrInfo['valueId'] = $labelData->getSource()->getOptionId($value);
    }
  }

  return $attrInfo;
}
