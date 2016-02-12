<?php

class CustomerGenerator
{

    // create or update customer account
    public function createCustomer($data = array()){

        $email = $data['account']['email'];

        /** @var $customer Mage_Customer_Model_Customer */
        $customer = Mage::getModel('customer/customer');

        // load user if exist
        $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        $customer->loadByEmail($email);

        $isNew = true;
        if($customer->getId() > 0){
            $isNew = false;
        }

        if($isNew){
            $customer->setData($data['account']);
        }else{
            $customer->setFirstname($data['account']['firstname']);
            $customer->setLastname($data['account']['lastname']);
        }

        foreach (array_keys($data['address']) as $index) {
            $address = Mage::getModel('customer/address');

            $addressData = array_merge($data['account'], $data['address'][$index]);

            // Set default billing and shipping flags to address
            // TODO check if current shipping info is the same than current default one, and avoid create a new one.
            $isDefaultBilling = isset($data['account']['default_billing'])
                && $data['account']['default_billing'] == $index;
            $address->setIsDefaultBilling($isDefaultBilling);
            $isDefaultShipping = isset($data['account']['default_shipping'])
                && $data['account']['default_shipping'] == $index;
            $address->setIsDefaultShipping($isDefaultShipping);

            $address->addData($addressData);

            // Set post_index for detect default billing and shipping addresses
            $address->setPostIndex($index);

            $customer->addAddress($address);
        }

        // Default billing and shipping
        if (isset($data['account']['default_billing'])) {
            $customer->setData('default_billing', $data['account']['default_billing']);
        }
        if (isset($data['account']['default_shipping'])) {
            $customer->setData('default_shipping', $data['account']['default_shipping']);
        }
        if (isset($data['account']['confirmation'])) {
            $customer->setData('confirmation', $data['account']['confirmation']);
        }

        if (isset($data['account']['sendemail_store_id'])) {
            $customer->setSendemailStoreId($data['account']['sendemail_store_id']);
        }

        if($isNew){
            echo 'create user';
            $customer
                ->setPassword($data['account']['password'])
                ->setForceConfirmed(true)
                ->save();
            $customer->cleanAllAddresses();
        }else{
            echo 'update user';
            $customer->save();
	        $customer->setConfirmation(null);
	        $customer->save();
        }

        return $customer;
    }

}
