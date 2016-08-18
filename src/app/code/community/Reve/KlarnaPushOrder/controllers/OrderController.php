<?php
/**
 * Created by   : Dmitry Shirokovskiy.
 * Email        : info@phpwebstudio.com
 * Date         : 10.07.16
 * Time         : 19:22
 * Description  : url to call pushing order
 * http[s]://www.your_lovely_shop.tld/reve_klarna/order/?klarna_order=NNNN&storeID=MM
 * or
 * http[s]://www.your_lovely_shop.tld/reve_klarna/order/index/klarna_order/NNNN/storeID/MM
 */
class Reve_KlarnaPushOrder_OrderController extends Mage_Checkout_Controller_Action
{
    /**
     * Retrieve Reve_Klarna Helper
     *
     * @return Reve_Klarna_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('klarnapushorder');
    }

    public function indexAction()
    {
        require_once 'ReveKlarna/Checkout.php';
        $response = ['status' => 'SUCCESS'];

        if ($this->_getHelper()->getIsEnabled()) {
            # get URL parameters
            $klarnaOrderId = $this->getRequest()->getParam('klarna_order');
            $storeID = $this->getRequest()->getParam('storeID');
            if ($storeID <= 0) {
                $storeID = 1;
            }

            Mage::app()->setCurrentStore($storeID);

            Mage::log("-----------", null, "klarnapushorder-checkout.log");
            Mage::log("Processing Klarna order:". $klarnaOrderId, null, "klarnapushorder-checkout.log");

            $reveOrder = Mage::getModel("klarnapushorder/order");

            // Klarna setup
            $klarnaUrl = Klarna_Checkout_Connector::BASE_URL;
            if (Mage::getStoreConfig('revetab/general/klarna_env', $storeID) == 'test') { // TODO: read from Klarna modules
                $klarnaUrl = Klarna_Checkout_Connector::BASE_TEST_URL;
            }

            $connector = Klarna_Checkout_Connector::create(
                Mage::getStoreConfig('revetab/general/klarna_secret', $storeID), // TODO: read from Klarna modules
                $klarnaUrl
            );

            // fetch klarna order
            $klarnaOrder = new Klarna_Checkout_Order($connector, $klarnaOrderId);
            try {
                $klarnaOrder->fetch();
            } catch (Exception $e) {
                Mage::log("Error on klarna connection (see exception.log)",null,"klarnapushorder-checkout.log");
                Mage::logException($e);

                $response['status'] = 'ERROR';
                $response['message'] = $this->__("Error on klarna connection:".$e->getMessage());
                $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));

                Mage::log("-----------", null, "klarnapushorder-checkout.log");
                return;
            }

            if ($klarnaOrder['status'] == 'created') {
                Mage::log("Klarna Order ($klarnaOrderId) already exist!", null, "klarnapushorder-checkout.log");

                $response['status'] = 'ERROR';
                $response['message'] = $this->__("Klarna Order ($klarnaOrderId) already exist!");
                $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));

                Mage::log("-----------", null, "klarnapushorder-checkout.log");
                return;
            } else {
                // match klarna data with magento structure
                $user = $klarnaOrder['shipping_address']; // Klarna user, not Magento structure
                $cart = $klarnaOrder['cart']['items']; // Klarna cart, not Magento structure

                $_customer = Mage::getModel("klarnapushorder/customer");
                $_customer->assignKlarnaData($user);

                // create sales quote
                $quote = $this->_getHelper()->_getQuote();
                if ($storeID) {
                    $quote->setStoreId($storeID);
                } else {
                    $quote->setStoreId(Mage::app()->getStore('default')->getId());
                }


                try{
                    // add cart to quote
                    $reveOrder->pushKlarnaCartToQuote($cart, $storeID);

                    // add customer to quote
                    $quote->assignCustomer($_customer);

                    $reveOrder->saveQuote($_customer, ['id'=>$klarnaOrderId, 'reservation'=>$klarnaOrder['reservation']]);

                    Mage::log('quote : '. $quote->getId(), null, "klarnapushorder-checkout.log");

                    // post quote as an order
                    $service = Mage::getModel('sales/service_quote', $quote);
                    $service->submitAll();
                    $newOrder = $service->getOrder();

                    // generate a auth transaction, needed for Klarna Offical Module
                    $payment = $newOrder->getPayment();
                    $payment->setTransactionId( $klarnaOrder['reservation'] )
                      ->setIsTransactionClosed(0)
                      ->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED);
                    if ($transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH)) {
                      $transaction->save();
                    }
                    $payment->save();

                    Mage::log("Order created ID: ". $newOrder->getId(), null, "klarnapushorder-checkout.log");

                    if (Mage::getStoreConfig('sales_email')['order']['enabled'] == 1) {
                        $newOrder->getSendConfirmation(null);
                        $newOrder->sendNewOrderEmail();

                        Mage::log("Order mail sent", null, "klarnapushorder-checkout.log");

                    } else {
                        Mage::log("Order mail not sent, it's disabled", null, "klarnapushorder-checkout.log");
                    }
                } catch (Exception $e) {
                    Mage::log("Error pushing order (see exception.log)",null,"klarnapushorder-checkout.log");
                    Mage::logException($e);

                    $response['status'] = 'ERROR';
                    $response['message'] = $this->__("Error pushing order:".$e->getMessage());
                    $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));

                    Mage::log("-----------", null, "klarnapushorder-checkout.log");
                    return;
                }

                // update klarna order with status created
                try {
                    $klarnaOrder->update(array('status' => 'created'));
                } catch (Exception $e) {
                    Mage::log("error getting order from klarna. (see exception.log)", null, "klarnapushorder-checkout.log");
                    Mage::logException($e);

                    $response['status'] = 'ERROR';
                    $response['message'] = $this->__("Error getting order from klarna. (see exception.log):".$e->getMessage());
                    $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));

                    Mage::log("-----------", null, "klarnapushorder-checkout.log");
                    return;
                }
                Mage::log("Successfully done!", null, "klarnapushorder-checkout.log");
            }
        } else {
            Mage::log("Module is Disabled!", null, "klarnapushorder-checkout.log");

            $response['status'] = 'ERROR';
            $response['message'] = $this->__("Error: Module is Disabled!");
        }

        Mage::log("-----------", null, "klarnapushorder-checkout.log");
        $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));
    }
}
