<?php

require_once 'config.php';
require_once 'Klarna/Checkout.php';

$klarna_order = $_GET['klarna_order'];

// set env credentials
if($klara_env == 'test'){
  $klarna_url = Klarna_Checkout_Connector::BASE_TEST_URL;
  $klarna_secret = $test_klarna_secret;
}else{
  $klarna_url = Klarna_Checkout_Connector::BASE_URL;
  $klarna_secret = $prod_klarna_secret;
}

// get klarna order
$connector = Klarna_Checkout_Connector::create(
    base64_encode(hash('sha256', $klarna_secret, true)),
    $klarna_url
);

$order = new Klarna_Checkout_Order($connector, $klarna_order);
try {
  $order->fetch();
  echo 'connection ok <br />';
  echo $order['status'];
} catch (Klarna_Checkout_ApiErrorException $e) {
  var_dump($e->getMessage());
  var_dump($e->getPayload());
  die;
}
