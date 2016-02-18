<?php

/*****************************************************************
How to get Configurable product Option by product Id
****************************************************************
*/
 require_once '../app/Mage.php';
 Mage::app();/*

 $product = Mage::getModel('catalog/product')->loadByAttribute('sku', 'c1234');
    $configurableAttributeCollection=$product->getTypeInstance()->getConfigurableAttributes();

    foreach($configurableAttributeCollection as $attribute){
       $attributeid = $attribute->getProductAttribute()->getId();
        //echo "super:->".$attributeid."";
         $new = $attributeid;
    }



  $productAttributeOptions = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
   $attributeOptions = array();


   foreach ($productAttributeOptions as $productAttribute) {
   echo $productAttribute['label']."";
   echo $productAttribute['attribute_id']."-------"."";

    foreach ($productAttribute['values'] as $attribute) {

    echo "id:".$attribute['value_index']."price:".$attribute['pricing_value'].  $attribute['store_label']."";

    }

  }*/

/*
$attr = Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('frontend_label', 'size');

echo $attr->getData()[0]['attribute_id'];
*/

$attr = 'size';
$_product = Mage::getModel('catalog/product');
$attr = $_product->getResource()->getAttribute($attr);
if ($attr->usesSource()) {
    echo $color_id = $attr->getSource()->getOptionId("MEDIUM");
}

   ?>
