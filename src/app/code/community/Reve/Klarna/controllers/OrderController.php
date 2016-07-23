<?php
require_once 'Klarna/Checkout.php';
/**
 * Created by   : Dmitry Shirokovskiy.
 * Email        : info@phpwebstudio.com
 * Date         : 10.07.16
 * Time         : 19:22
 * Description  : url to call pushing order
 * http[s]://www.your_lovely_shop.tld/klarna/order/?klarna_order=NNNN&storeID=MM
 * or
 * http[s]://www.your_lovely_shop.tld/klarna/order/index/klarna_order/NNNN/storeID/MM
 */
class Reve_Klarna_OrderController extends Mage_Checkout_Controller_Action
{
    /**
     * Retrieve Reve_Klarna Helper
     *
     * @return Reve_Klarna_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('klarna');
    }

    public function indexAction()
    {
        $response = ['status' => 'SUCCESS'];

        if ($this->_getHelper()->getIsEnabled()) {
            # get URL parameters
            $klarnaOrderId = $this->getRequest()->getParam('klarna_order');
            $storeID = $this->getRequest()->getParam('storeID');
            if ($storeID <= 0) {
                $storeID = 1;
            }

            Mage::app()->setCurrentStore($storeID);

            $reveOrder = Mage::getModel("klarna/order");

            // Klarna setup
            $klarnaUrl = Klarna_Checkout_Connector::BASE_URL;
            if (Mage::getStoreConfig('revetab/general/klarna_env', $storeID) == 'test') {
                $klarnaUrl = Klarna_Checkout_Connector::BASE_TEST_URL;
            }

            $connector = Klarna_Checkout_Connector::create(
                Mage::getStoreConfig('revetab/general/klarna_secret', $storeID),
                $klarnaUrl
            );

            // fetch klarna order
            $klarnaOrder = new Klarna_Checkout_Order($connector, $klarnaOrderId);
            try {
                $klarnaOrder->fetch();
            } catch (Exception $e) {
                Mage::log("Error on klarna connection (see exception.log)",null,"klarna-checkout.log");
                Mage::logException($e);

                $response['status'] = 'ERROR';
                $response['message'] = $this->__("Error on klarna connection:".$e->getMessage());
                $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));
                return;
            }

            if ($klarnaOrder['status'] == 'created') {
                Mage::log("Klarna Order ($klarnaOrderId) already exist!", null, "klarna-pushorder.log");

                $response['status'] = 'ERROR';
                $response['message'] = $this->__("Klarna Order ($klarnaOrderId) already exist!");
                $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));
                return;
            } else {
                // match klarna data with magento structure
                $user = $klarnaOrder['shipping_address']; // Klarna user, not Magento structure
                $cart = $klarnaOrder['cart']['items']; // Klarna cart, not Magento structure

                $_customer = Mage::getModel("klarna/customer");
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

                    Mage::log('quote : '. $quote->getId(), null, "klarna-pushorder.log");

                    // post quote as an order
                    $service = Mage::getModel('sales/service_quote', $quote);
                    $service->submitAll();
                    $newOrder = $service->getOrder();

                    Mage::log("Order created ID: ". $newOrder->getId(), null, "klarna-pushorder.log");

                    if (Mage::getStoreConfig('sales_email')['order']['enabled'] == 1) {
                        $newOrder->getSendConfirmation(null);
                        $newOrder->sendNewOrderEmail();

                        Mage::log("Order mail sent", null, "klarna-pushorder.log");

                    } else {
                        Mage::log("Order mail not sent, it's disabled", null, "klarna-pushorder.log");
                    }
                } catch (Exception $e) {
                    Mage::log("Error pushing order (see exception.log)",null,"klarna-checkout.log");
                    Mage::logException($e);

                    $response['status'] = 'ERROR';
                    $response['message'] = $this->__("Error pushing order:".$e->getMessage());
                    $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));
                    return;
                }

                // update klarna order with status created
                try {
                    $klarnaOrder->update(array('status' => 'created'));
                } catch (Exception $e) {
                    Mage::log("error getting order from klarna. (see exception.log)", null, "klarna-pushorder.log");
                    Mage::logException($e);

                    $response['status'] = 'ERROR';
                    $response['message'] = $this->__("Error getting order from klarna. (see exception.log):".$e->getMessage());
                    $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));
                    return;
                }
                Mage::log("Successfully done!", null, "klarna-pushorder.log");
            }
        } else {
            Mage::log("Module is Disabled!", null, "klarna-pushorder.log");

            $response['status'] = 'ERROR';
            $response['message'] = $this->__("Error: Module is Disabled!");
        }

        $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json')->setBody(Mage::helper('core')->jsonEncode($response));
    }
}
