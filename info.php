<?php

require_once 'config.php';

require_once $magePath;
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
$orderID = _GET['klarna_order'];
if($orderID){
    require_once './Klarna/Checkout.php';

    $sharedSecret = $klarna_secret;
    $orderID = _GET['klarna_order'];

    $connector = Klarna_Checkout_Connector::create(
        $sharedSecret,
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

    var_dump($order);
}
