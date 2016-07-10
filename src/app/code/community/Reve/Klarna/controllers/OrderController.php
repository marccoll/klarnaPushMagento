<?php

/**
 * Created by   : Dmitry Shirokovskiy.
 * Email        : info@phpwebstudio.com
 * Date         : 10.07.16
 * Time         : 19:22
 * Description  :
 */
class Reve_Klarna_OrderController extends Mage_Checkout_Controller_Action
{
    public function indexAction()
    {
        # todo: put logic here

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('Klarna pushing order'));
        $this->renderLayout();
    }
}
