<?php

/**
 * Simple Configurable rewrite of the virtual product type model.
 *
 * @see Ysrtech_SimpleConfigurable_Catalog_Model_Product_Type_Simple
 */
class Ysrtech_SimpleConfigurable_Catalog_Model_Product_Type_Virtual extends Mage_Catalog_Model_Product_Type_Virtual
{
    /**
     * @param Varien_Object $buyRequest
     * @param Mage_Catalog_Model_Product|null $product
     * @return Mage_Catalog_Model_Product[]|string
     */
    public function prepareForCart(Varien_Object $buyRequest, $product = null)
    {
        $result = parent::prepareForCart($buyRequest, $product);

        if (is_array($result) && $buyRequest->getCpid()) {
            foreach ($result as $resultProduct) {
                $resultProduct->addCustomOption('cpid', $buyRequest->getCpid());
            }
        }

        return $result;
    }
}
