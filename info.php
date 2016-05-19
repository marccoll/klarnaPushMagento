<?php

require_once 'config.php';

require_once MAGE_PATH;
umask(0);
Mage::app('default');

$methods = Mage::getSingleton('shipping/config')->getActiveCarriers();

echo '<strong>Shipping methods:</strong> <br>';

foreach ($methods as $shippingCode => $shippingModel)
{
  $shippingTitle = Mage::getStoreConfig('carriers/'.$shippingCode.'/title');
  echo  'code: ' .$shippingCode . '<br>';
  echo 'title: ' . $shippingTitle . '<br><br>';
}

echo '<strong>Payment methods: </strong><br>';

$payments = Mage::getSingleton('payment/config')->getActiveMethods();

foreach ($payments as $paymentCode=>$paymentModel) {
  $paymentTitle = Mage::getStoreConfig('payment/'.$paymentCode.'/title');
  echo  'code: ' .$paymentCode . '<br>';
  echo 'title: ' . $paymentTitle . '<br><br>';
}


// get order info
$orderID = $_GET['klarna_order'];
if ($orderID) {
  echo '<strong>Klarna order:</strong><br>';
  require_once './Klarna/Checkout.php';

  $connector = Klarna_Checkout_Connector::create(
    KLARNA_SECRET,
    Klarna_Checkout_Connector::BASE_TEST_URL
  );

  $order = new Klarna_Checkout_Order($connector, $orderID);

  try {
    $order->fetch();
  } catch (Klarna_Checkout_ApiErrorException $e) {
    var_dump($e->getMessage());
    var_dump($e->getPayload());
    die;
  }

  echo "<pre>";
  echo htmlentities( print_r($order, true) );
  echo "</pre>";
}
