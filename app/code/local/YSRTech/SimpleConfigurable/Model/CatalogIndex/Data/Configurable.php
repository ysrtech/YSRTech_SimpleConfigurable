<?php

/**
 * Simple Configurable rewrite of the CatalogIndex configurable product data model.
 *
 * Ensures children are always fetched for tiers/prices/attributes (since pricing
 * and dynamic content are fully driven by the associated products), and disables
 * this legacy indexer's own final-price computation in favor of the price index.
 */
class YSRTech_SimpleConfigurable_Model_CatalogIndex_Data_Configurable extends Mage_CatalogIndex_Model_Data_Configurable
{
    protected $_haveChildren = [
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_TIERS      => true,
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_PRICES     => true,
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_ATTRIBUTES => true,
    ];

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Core_Model_Store $store
     * @param Mage_Customer_Model_Group $group
     * @return bool
     */
    public function getFinalPrice($product, $store, $group)
    {
        return false;
    }
}
