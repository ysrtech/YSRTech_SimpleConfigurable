<?php

/**
 * Simple Configurable rewrite of the configurable product price model.
 *
 * When enabled, all pricing for a configurable product is delegated to its
 * cheapest in-stock associated (child) product, rather than the configurable
 * product's own price plus per-attribute price modifiers.
 */
class Ysrtech_SimpleConfigurable_Catalog_Model_Product_Type_Configurable_Price extends Mage_Catalog_Model_Product_Type_Configurable_Price
{
    /**
     * @var array
     */
    protected static $_childProductsCache = [];

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return float
     */
    public function getMinimalPrice($product)
    {
        return $this->getPrice($product);
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return float
     */
    public function getMaxPossibleFinalPrice($product)
    {
        if ($product->getData('max_price') !== null) {
            return $product->getData('max_price');
        }

        $child = $this->getChildProductWithHighestPrice($product, 'finalPrice', true);
        if (!$child) {
            $child = $this->getChildProductWithHighestPrice($product, 'finalPrice', false);
        }

        return $child ? $child->getFinalPrice() : $this->getPrice($product);
    }

    /**
     * @param float|null $qty
     * @param Mage_Catalog_Model_Product $product
     * @return float
     */
    public function getFinalPrice($qty, $product)
    {
        $storeId = $product->getStoreId();
        if (
            !Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore($storeId)
            || !Mage::getStoreConfigFlag('simpleconfigurable/product_page/set_price_is_lowest_price', $storeId)
        ) {
            return parent::getFinalPrice($qty, $product);
        }

        $child = $this->getChildProductWithLowestPrice($product, 'finalPrice', true);
        if (!$child) {
            $child = $this->getChildProductWithLowestPrice($product, 'finalPrice', false);
        }

        $finalPrice = $child ? $child->getFinalPrice() : $this->getPrice($product);
        $product->setFinalPrice($finalPrice);

        return $finalPrice;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return float
     */
    public function getPrice($product)
    {
        $storeId = $product->getStoreId();
        if (!Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore($storeId)) {
            return parent::getPrice($product);
        }

        if ($product->getData('indexed_price') !== null) {
            return $product->getData('indexed_price');
        }

        $child = $this->getChildProductWithLowestPrice($product, 'price', true);
        if (!$child) {
            $child = $this->getChildProductWithLowestPrice($product, 'price', false);
        }

        return $child ? $child->getPrice() : parent::getPrice($product);
    }

    /**
     * Tier pricing is not applicable: Simple Configurable delegates all pricing
     * to the selected child product.
     *
     * @param float|null $qty
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getTierPrice($qty, $product)
    {
        $storeId = $product->getStoreId();
        if (
            !Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore($storeId)
            || !Mage::getStoreConfigFlag('simpleconfigurable/product_page/set_price_is_lowest_price', $storeId)
        ) {
            return parent::getTierPrice($qty, $product);
        }

        return [];
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param bool $checkSalable
     * @return Mage_Catalog_Model_Product[]
     */
    public function getChildProducts($product, $checkSalable = true)
    {
        $cacheKey = $product->getId() . ':' . ($checkSalable ? '1' : '0');
        if (isset(self::$_childProductsCache[$cacheKey])) {
            return self::$_childProductsCache[$cacheKey];
        }

        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $collection = $typeInstance->getUsedProductCollection($product)
            ->addAttributeToSelect(['price', 'final_price', 'special_price', 'special_from_date', 'special_to_date']);

        $children = [];
        foreach ($collection as $child) {
            if ($checkSalable && !$child->isSaleable()) {
                continue;
            }
            $children[] = $child;
        }

        self::$_childProductsCache[$cacheKey] = $children;

        return $children;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param string $priceType 'price' or 'finalPrice'
     * @param bool $checkSalable
     * @return Mage_Catalog_Model_Product|null
     */
    public function getChildProductWithHighestPrice($product, $priceType = 'finalPrice', $checkSalable = true)
    {
        $getter = 'get' . ucfirst($priceType);
        $highest = null;
        foreach ($this->getChildProducts($product, $checkSalable) as $child) {
            if ($highest === null || $child->$getter() > $highest->$getter()) {
                $highest = $child;
            }
        }
        return $highest;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param string $priceType 'price' or 'finalPrice'
     * @param bool $checkSalable
     * @return Mage_Catalog_Model_Product|null
     */
    public function getChildProductWithLowestPrice($product, $priceType = 'finalPrice', $checkSalable = true)
    {
        $getter = 'get' . ucfirst($priceType);
        $lowest = null;
        foreach ($this->getChildProducts($product, $checkSalable) as $child) {
            if ($lowest === null || $child->$getter() < $lowest->$getter()) {
                $lowest = $child;
            }
        }
        return $lowest;
    }
}
