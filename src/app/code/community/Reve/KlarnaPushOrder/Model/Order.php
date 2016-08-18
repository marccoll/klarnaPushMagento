<?php

/**
 * Created by   : Dmitry Shirokovskiy.
 * Email        : info@phpwebstudio.com
 * Date         : 18.07.16
 * Time         : 23:16
 * Description  :
 */
class Reve_KlarnaPushOrder_Model_Order extends Mage_Sales_Model_Order
{
  const SHIPPING_METHOD_CODE = 'flatrate_flatrate';

  // Payment methods:
  // vaimo_klarna_checkout = Klarna Official
  // klarnaCheckout_payment = Avenla module
  const PAYMENT_METHOD_CODE = 'vaimo_klarna_checkout'; // TODO: should be autodetected based on installed module or overide using magento admin

    protected $sizeAttrNames = ['size'];

    public function pushKlarnaCartToQuote($cart, $storeId = null)
    {
        $quote = Mage::helper("klarna")->_getQuote();
        // add item to quote
        foreach ($cart as $key => $prod) {
            // load product
            $productId = $prod['reference'];
            $variantAttr = array(
                'qty' => intval($prod['quantity'])
            );

            // get product variant attribute id
            if (isset($prod['merchant_item_data'])) {
                $merchantItemData = explode(';', $prod['merchant_item_data']);

                foreach ($merchantItemData as $key => $attr) {
                    if (empty($attr)) continue;

                    $attrData = explode(':', $attr);
                    $label = $attrData[0];
                    $value = $attrData[1];
                    $attrInfo = Mage::helper('klarna')->getAttrInfo($label, $value, Mage::getStoreConfig("revetab/general/klarna_attr_names"));

                    $variantAttr['super_attribute'][intval($attrInfo['labelId'])] = intval($attrInfo['valueId']);
                }
            }

            $product = Mage::getModel('catalog/product')->load($productId);
            $quote->addProduct($product, new Varien_Object($variantAttr));
        }
    }

    public function saveQuote($_customer, $klarna_order)
    {
        $quote = Mage::helper("klarna")->_getQuote();

        // set billing and shipping based on customer defaults
        $shippingDefault = $_customer->getDefaultShippingAddress();

        if (!is_object($shippingDefault)) {
            $address = $quote->getCustomer()->getAddressesCollection()->getFirstItem()->getData();

            $addressData = array(
                'firstname' => $address['firstname'],
                'lastname' => $address['lastname'],
                'street' => $address['street'],
                'city' => $address['city'],
                'postcode' => $address['postcode'],
                'telephone' => $address['telephone'],
                'country_id' => $address['country_id']
            );
        } else {
            $addressData = array(
                'firstname' => $shippingDefault->getFirstname(),
                'lastname' => $shippingDefault->getLastname(),
                'street' => $shippingDefault->getStreet(),
                'city' => $shippingDefault->getCity(),
                'postcode' => $shippingDefault->getPostcode(),
                'telephone' => $shippingDefault->getTelephone(),
                'country_id' => $shippingDefault->getCountryId()
            );
        }

        $quote->getBillingAddress()->addData($addressData);
        $shippingAddress = $quote->getShippingAddress()->addData($addressData);

        // shipping and payments method
        $shippingAddress->setShippingMethod(self::SHIPPING_METHOD_CODE)
            ->setPaymentMethod(self::PAYMENT_METHOD_CODE)
            ->setCollectShippingRates(true)
            ->collectShippingRates();
        $quote->getPayment()->addData(array('method' => self::PAYMENT_METHOD_CODE));

        $quote->getPayment()->setAdditionalInformation(array(

          // Avenla module support
          'klarna_order_id' => $klarna_order['id'],
          'klarna_order_reservation' => $klarna_order['reservation'],

          // Klarna Official module support (also need to set a AUTH transaction, see order save)
          'klarna_reservation_reference' => $klarna_order['id'],
          'klarna_reservation_id' => $klarna_order['reservation']

        ));

        // calculate totals and save
        $quote->collectTotals();
        $quote->save();

        return $this;
    }
}
