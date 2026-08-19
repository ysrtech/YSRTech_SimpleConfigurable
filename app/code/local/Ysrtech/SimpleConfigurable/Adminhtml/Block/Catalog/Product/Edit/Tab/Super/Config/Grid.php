<?php

/**
 * Simple Configurable rewrite of the "associated products" grid on the configurable
 * product edit page.
 *
 * The only behavioral change vs. core is that {@see addFilterByRequiredOptions()} is
 * omitted, so that simple/virtual products which have their OWN custom (required)
 * options remain selectable as associated products. Simple Configurable renders those
 * custom options via ajax on the storefront, so core's restriction (which exists
 * because vanilla configurable products cannot render per-child custom options) does
 * not apply here.
 */
class Ysrtech_SimpleConfigurable_Adminhtml_Block_Catalog_Product_Edit_Tab_Super_Config_Grid extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Super_Config_Grid
{
    /**
     * Prepare collection
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $allowProductTypes = [];
        foreach (Mage::helper('catalog/product_configuration')->getConfigurableAllowedTypes() as $type) {
            $allowProductTypes[] = $type->getName();
        }

        $product = $this->_getProduct();
        $collection = $product->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('attribute_set_id')
            ->addAttributeToSelect('type_id')
            ->addAttributeToSelect('price')
            ->addFieldToFilter('attribute_set_id', $product->getAttributeSetId())
            ->addFieldToFilter('type_id', $allowProductTypes)
            // Simple Configurable: intentionally NOT calling addFilterByRequiredOptions()
            // so that products with their own required custom options remain selectable.
            ->joinAttribute('name', 'catalog_product/name', 'entity_id', null, 'inner');

        if ($this->isModuleEnabled('Mage_CatalogInventory', 'catalog')) {
            Mage::getModel('cataloginventory/stock_item')->addCatalogInventoryToProductCollection($collection);
        }
        /** @var Mage_Catalog_Model_Product_Type_Configurable $productType */
        $productType = $product->getTypeInstance(true);

        foreach ($productType->getUsedProductAttributes($product) as $attribute) {
            $collection->addAttributeToSelect($attribute->getAttributeCode());
            $collection->addAttributeToFilter($attribute->getAttributeCode(), ['notnull' => 1]);
        }

        $this->setCollection($collection);

        if ($this->isReadonly()) {
            $collection->addFieldToFilter('entity_id', ['in' => $this->_getSelectedProducts()]);
        }

        Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
        return $this;
    }
}
