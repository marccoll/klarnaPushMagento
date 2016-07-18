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
        if ($this->_getHelper()->getIsEnabled()) {
            # get URL parameters
            $klarnaOrderId = $this->getRequest()->getParam('klarna_order');
            $storeID = $this->getRequest()->getParam('storeID');

            $reveOrder = Mage::getModel("klarna/order");

            // Klarna setup
            $klarnaUrl = Klarna_Checkout_Connector::BASE_URL;
            if (Mage::getStoreConfigFlag('revetab/general/klarna_env') == 'test') {
                $klarnaUrl = Klarna_Checkout_Connector::BASE_TEST_URL;
            }

            $connector = Klarna_Checkout_Connector::create(
                Mage::getStoreConfigFlag('revetab/general/klarna_secret'),
                $klarnaUrl
            );

            // fetch klarna order
            $klarnaOrder = new Klarna_Checkout_Order($connector, KLARNA_ORDER_ID);
            try {
                $klarnaOrder->fetch();
                #doLog('klarna order fetched');
            } catch (Exception $e) {
                #doLog('error on klarna connection. Exception:'. $e);
                #exit;
            }
        } else {
            // todo: if not Enabled, say something
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('Klarna pushing order'));
        $this->renderLayout();
    }
}
