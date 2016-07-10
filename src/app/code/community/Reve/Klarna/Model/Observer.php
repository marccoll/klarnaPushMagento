<?php
/**
 * Created by   : Dmitry Shirokovskiy.
 * Email        : info@phpwebstudio.com
 * Date         : 10.07.16
 * Time         : 19:13
 * Description  :
 */
class Reve_Klarna_Model_Observer extends Varien_Event_Observer
{
    public function pushOrder(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('klarna')->getIsEnabled()){ //if module is not enabled
            if (isset($_SERVER['MAGE_IS_DEVELOPER_MODE']))
                Mage::log('Module is not Enabled! '.__METHOD__.', line:'.__LINE__);
            return $this;
        }
    }
}
