<?php
require_once 'config.php';
require_once 'Klarna/Checkout.php';

// magento
require_once MAGE_PATH;
require_once 'CustomerGenerator.php';
umask(0);
Mage::app();

// get magento config
define('SEND_ORDER_MAIL', Mage::getStoreConfig('sales_email')['order']['enabled'] == 1);

// get url params
define('KLARNA_ORDER_ID',  $_GET['klarna_order']);
$storeID = $_GET['storeID'];

// log helper
define("OUTPUT_LOG", $_GET['verbose']);
function doLog($message) {
  $prefix = KLARNA_ORDER_ID .': ';
  Mage::Log($prefix . $message, null, "klarna-pushorder.log", true);
  if (OUTPUT_LOG) echo $message ."<br>";
}

doLog('push received');
if (KLARNA_ORDER_ID == "") {
  doLog("missing klarna order id");
  exit;
}

// Klarna setup
$klarna_url = Klarna_Checkout_Connector::BASE_URL;
if (KLARNA_ENV == 'test') $klarna_url = Klarna_Checkout_Connector::BASE_TEST_URL;
$connector = Klarna_Checkout_Connector::create(
  KLARNA_SECRET,
  $klarna_url
);

// fetch klarna order
$klarnaOrder = new Klarna_Checkout_Order($connector, KLARNA_ORDER_ID);
try {
  $klarnaOrder->fetch();
  doLog('klarna order fetched');
} catch (Exception $e) {
  doLog('error on klarna connection. Exception:'. $e);
  exit;
}

// check if order already exist
if ($klarnaOrder['status'] == 'created') {
  doLog('klarna order already exist');
  exit;

} else { // start order processing
  doLog('processing klarna order');

  // match klarna data with magento structure
  $user = $klarnaOrder['shipping_address'];
  $cart = $klarnaOrder['cart']['items'];

  // create or update customer account
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
  $customerId = 0;
  $_customer = null;
  try {
    $customerGenerator = new CustomerGenerator();
    $_customer = $customerGenerator->createCustomer($customerData);
    $customerId = $_customer->getId();
  } catch (Exception $e) {
    doLog("can't create/update customer. Exception:". $e);
    exit;
  }
  doLog('user : ' . $customerId);



  // create sales quote
  $quote = Mage::getModel('sales/quote');
  if ($storeID) {
    $quote->setStoreId($storeID);
  } else {
    $quote->setStoreId(Mage::app()->getStore('default')->getId());
  }

  // add item to quote
  foreach ($cart as $key => $prod) {
    // load product
    $productId = $prod['reference'];
    $variantAttr = array(
      'qty' => intval($prod['quantity'])
    );

    // get product variant attribute id
    if (isset($prod['merchant_item_data'])) {

      $merchantItemData = explode(';', $prod['merchant_item_data']);
      $sAttrs = array();
      foreach ($merchantItemData as $key => $attr) {
        if (trim($attr) == '') continue;

        $attrData = explode(':', $attr);
        $label = $attrData[0];
        $value = $attrData[1];
        $attrInfo = getAttrInfo($label, $value, $sizeAttrNames);

        $variantAttr['super_attribute'][intval($attrInfo['labelId'])] = intval($attrInfo['valueId']);
      }
    }

    $product = Mage::getModel('catalog/product')->load($productId);
    $quote->addProduct($product, new Varien_Object($variantAttr));
  }

  // add customer to quote
  $quote->assignCustomer($_customer);

  // guest only order
  // $quote->setCustomerEmail("user@domain.net");

  // set billing and shipping based ón customer defaults
  $shippingDefault = $_customer->getDefaultShippingAddress();
  $addressData = array(
    'firstname' => $shippingDefault->getFirstname(),
    'lastname' => $shippingDefault->getLastname(),
    'street' => $shippingDefault->getStreet(),
    'city' => $shippingDefault->getCity(),
    'postcode' => $shippingDefault->getPostcode(),
    'telephone' => $shippingDefault->getTelephone(),
    'country_id' => $shippingDefault->getCountryId()
  );
  $quote->getBillingAddress()->addData($addressData);
  $shippingAddress = $quote->getShippingAddress()->addData($addressData);

  // shipping and payments method
  $shippingAddress->setShippingMethod(SHIPPING_METHOD_CODE)
    ->setPaymentMethod(PAYMENT_METHOD_CODE)
    ->setCollectShippingRates(true)
    ->collectShippingRates();
  $quote->getPayment()->addData(array('method' => PAYMENT_METHOD_CODE));
  $quote->getPayment()->setAdditionalInformation(array('klarna_order_id' => KLARNA_ORDER_ID,'klarna_order_reservation' => $klarnaOrder['reservation']));

  // calulate totals and save
  $quote->collectTotals();
  $quote->save();
  $quoteId = $quote->entity_id;
  doLog('quote : '. $quoteId);

  // post quote as a order
  try{
    $service = Mage::getModel('sales/service_quote', $quote);
    $service->submitAll();
    $newOrder = $service->getOrder();
    if (SEND_ORDER_MAIL) {
      $newOrder->getSendConfirmation(null);
      $newOrder->sendNewOrderEmail();
      doLog('order mail sent');
    } else {
      doLog("order mail not sent, it's disabled");
    }
    doLog('order created id: '. $newOrder->getId());
  } catch (Exception $e) {
    doLog('error submiting order. Exception:'. $e);
    exit;
  }

  // update klarna order with status created
  try {
    $klarnaOrder->update(array('status' => 'created'));
  } catch (Exception $e) {
    doLog('error getting order from klarna. Exception:'. $e);
  }
  doLog('all done');
  exit;

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
