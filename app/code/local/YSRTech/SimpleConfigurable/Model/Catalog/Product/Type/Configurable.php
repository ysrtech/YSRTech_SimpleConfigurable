<?php

/**
 * Simple Configurable rewrite of the configurable product type model.
 *
 * The only behavioral change vs. core is that {@see addFilterByRequiredOptions()}
 * is skipped when Simple Configurable is active for the current store, since custom
 * options on associated (child) products are rendered/handled via ajax rather than
 * being disallowed.
 */
class YSRTech_SimpleConfigurable_Model_Catalog_Product_Type_Configurable extends Mage_Catalog_Model_Product_Type_Configurable
{
    /**
     * Retrieve array of "subproducts"
     *
     * @param array|null $requiredAttributeIds
     * @param Mage_Catalog_Model_Product|null $product
     * @return Mage_Catalog_Model_Product[]
     */
    public function getUsedProducts($requiredAttributeIds = null, $product = null)
    {
        Varien_Profiler::start('CONFIGURABLE:' . __METHOD__);
        if (!$this->getProduct($product)->hasData($this->_usedProducts)) {
            if (
                is_null($requiredAttributeIds)
                && is_null($this->getProduct($product)->getData($this->_configurableAttributes))
            ) {
                // If used products load before attributes, we will load attributes.
                $this->getConfigurableAttributes($product);
                // After attributes loading products loaded too.
                Varien_Profiler::stop('CONFIGURABLE:' . __METHOD__);
                return $this->getProduct($product)->getData($this->_usedProducts);
            }

            $usedProducts = [];
            $collection = $this->getUsedProductCollection($product);

            $storeId = $this->getProduct($product)->getStoreId();
            if (!Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore($storeId)) {
                $collection->addFilterByRequiredOptions();
            }

            // Provides a mechanism for attaching additional attributes to the children of configurable products
            // Will primarily have affect on the configurable product view page
            $childAttributes = Mage::getConfig()->getNode(self::XML_PATH_PRODUCT_CONFIGURABLE_CHILD_ATTRIBUTES);

            if ($childAttributes) {
                $childAttributes = $childAttributes->asArray();
                $childAttributes = array_keys($childAttributes);

                $collection->addAttributeToSelect($childAttributes);
            }

            if (is_array($requiredAttributeIds)) {
                foreach ($requiredAttributeIds as $attributeId) {
                    $attribute = $this->getAttributeById($attributeId, $product);
                    if (!is_null($attribute)) {
                        $collection->addAttributeToFilter($attribute->getAttributeCode(), ['notnull' => 1]);
                    }
                }
            }

            foreach ($collection as $item) {
                $usedProducts[] = $item;
            }

            $this->getProduct($product)->setData($this->_usedProducts, $usedProducts);
        }
        Varien_Profiler::stop('CONFIGURABLE:' . __METHOD__);
        return $this->getProduct($product)->getData($this->_usedProducts);
    }
}
