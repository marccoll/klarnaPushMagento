<?php
require_once 'config.php';
require_once 'Klarna/Checkout.php';
require_once 'LogClass.php';

// magento
require_once MAGE_PATH;
require_once 'OrderGenerator.php';
require_once 'CustomerGenerator.php';
umask(0);
Mage::app();

// get url params
define('KLARNA_ORDER_ID',  $_GET['klarna_order']);
$storeID = $_GET['storeID'];

// log helper
define("OUTPUT_LOG", $_GET['verbose']);
function doLog($message) {
  $prefix = KLARNA_ORDER_ID .': ';
  // TODO: use Mage::Log instead
  // Mage::Log($prefix . $message, null, "klarna-pushorder.log", true);
  Log::add($prefix . $message);
  if (OUTPUT_LOG) echo $message ."<br>";
}

doLog('push received');
if (KLARNA_ORDER_ID == "") {
  doLog("missing order id");
  exit;
}

// Klarna setup
$klarna_url = Klarna_Checkout_Connector::BASE_URL;
if (KLARNA_ENV == 'test') $klarna_url = Klarna_Checkout_Connector::BASE_TEST_URL;
$connector = Klarna_Checkout_Connector::create(
  KLARNA_SECRET,
  $klarna_url
);

// fetch order
$order = new Klarna_Checkout_Order($connector, KLARNA_ORDER_ID);
try {
  $order->fetch();
  doLog('order fetched');
} catch (Exception $e) {
  doLog('error on klarna connection. Exception:'. $e);
  exit;
}

// check if order already exist
if ($order['status'] == 'created') {
  doLog('order already exist');
  exit;

} else { // start order processing
  doLog('processing order');

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
            'password' => randomPassword(),
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

  $customerId = 0;
  try {
    $customerGenerator = new CustomerGenerator();
    $customerGenerated = $customerGenerator->createCustomer($customerData);
    $customerId = $customerGenerated->getId();
  } catch (Exception $e) {
    doLog("can't create/update customer. Exception:". $e);
    exit;
  }
  doLog('user : ' . $customerId);

  // create order
  $orderGenerator = new OrderGenerator();
  if ($storeID) $orderGenerator->setStoreId($storeID);
  $orderGenerator->setShippingMethod(SHIPPING_METHOD_CODE);
  $orderGenerator->setPaymentMethod(PAYMENT_METHOD_CODE);
  $orderGenerator->setCustomer($customerId);

  $newOrder = array();

  foreach ($cart as $key => $prod) {
    $ord = array(
        'product' => $prod['reference'],
        'qty' => $prod['quantity']
    );

    // get conf product info and convert it into codes
    if (isset($prod['merchant_item_data'])) {

      $attrs = explode(';', $prod['merchant_item_data']);
      $sAttrs = array();
      foreach ($attrs as $key => $attr) {
        if (trim($attr) == '') continue;

        $attrData = explode(':', $attr);
        $label = $attrData[0];
        $value = $attrData[1];

        $attrInfo = getAttrInfo($label, $value, $sizeAttrNames);

        $ord['super_attribute'][intval($attrInfo['labelId'])] = intval($attrInfo['valueId']);
      }
    }

    array_push($newOrder, $ord);
  };

  $orderCreated = false;
  try {
    $orderCreated = $orderGenerator->createOrder($newOrder);
  } catch (Exception $e) {
    doLog("can't create order. Exception:". $e);
  }
  if ($orderCreated) {
    doLog('order created');

    $update['status'] = 'created';
    try {
      $order->update($update);
    } catch (Exception $e) {
      doLog('error getting order from klarna. Exception:', $e);
    }
  }else{
    doLog('something went wrong when creating order');
  }
} // end order proccess


// HELPERS
function randomPassword() {
  return substr(str_shuffle("()[]$%@#!._-/~0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 30);
}

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
