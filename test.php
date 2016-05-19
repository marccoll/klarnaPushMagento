<?php

require_once 'config.php';
require_once 'Klarna/Checkout.php';

$klarna_order = $_GET['klarna_order'];

// set env credentials
if(KLARNA_ENV == 'test'){
  $klarna_url = Klarna_Checkout_Connector::BASE_TEST_URL;
}else{
  $klarna_url = Klarna_Checkout_Connector::BASE_URL;
}

// get klarna order
$connector = Klarna_Checkout_Connector::create(
  KLARNA_SECRET,
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
  exit;
}
