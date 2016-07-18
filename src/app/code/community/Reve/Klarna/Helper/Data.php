<?php
/**
 * Created by   : Dmitry Shirokovskiy.
 * Email        : info@phpwebstudio.com
 * Date         : 10.07.16
 * Time         : 19:00
 * Description  : helper of Reve_Klarna module
 * Checks if module available & enabled in CMS
 */
class Reve_Klarna_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getIsEnabled()
    {
        return Mage::getStoreConfigFlag('revetab/general/active');
    }

    public function getKlarnaAttrNames()
    {
        $attrNames = Mage::getStoreConfigFlag('revetab/general/klarna_attr_names');
        if (!empty($attrNames)) {
            $attrNames = implode(",",$attrNames);
        }

        return $attrNames;
    }
}
