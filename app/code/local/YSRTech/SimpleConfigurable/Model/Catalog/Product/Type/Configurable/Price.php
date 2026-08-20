<?php

/**
 * Simple Configurable rewrite of the configurable product price model.
 *
 * When enabled, all pricing for a configurable product is delegated to its
 * cheapest in-stock associated (child) product, rather than the configurable
 * product's own price plus per-attribute price modifiers.
 */
class YSRTech_SimpleConfigurable_Model_Catalog_Product_Type_Configurable_Price extends Mage_Catalog_Model_Product_Type_Configurable_Price
{
    /**
     * @var array
     */
    protected static $_childProductsCache = [];

    /**
     * Product ids whose price is currently being resolved, used to break link cycles.
     *
     * Pricing here is delegated to the associated products, so getPrice()/getFinalPrice() ask each
     * child for its own price. If the link graph ever leads back to a product already being priced
     * - most simply a row in catalog_product_super_link where parent_id = product_id - that walk
     * never terminates and the request dies on memory. Core never hits this because it prices a
     * configurable from its own price attribute and never consults the children.
     *
     * @var array<int, bool>
     */
    protected static $_pricingInProgress = [];

    /**
     * The associated product a shopper has actually chosen, if there is one.
     *
     * Magento records it against the configurable as the "simple_product" custom option while
     * building a quote item, so this is what distinguishes a cart line - where the price is a
     * settled fact - from a product page, where nothing is chosen yet and a range or the cheapest
     * price is the honest answer.
     *
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Product|null
     */
    protected function _getChosenProduct($product)
    {
        $option = $product->getCustomOption('simple_product');
        if (!$option || !$option->getProduct()) {
            return null;
        }

        $chosen = $option->getProduct();

        return $chosen->getId() == $product->getId() ? null : $chosen;
    }

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

        $productId = (int) $product->getId();
        if (isset(self::$_pricingInProgress[$productId])) {
            return $this->getPrice($product);
        }
        self::$_pricingInProgress[$productId] = true;

        try {
            $child = $this->getChildProductWithHighestPrice($product, 'finalPrice', true);
            if (!$child) {
                $child = $this->getChildProductWithHighestPrice($product, 'finalPrice', false);
            }

            return $child ? $child->getFinalPrice() : $this->getPrice($product);
        } finally {
            unset(self::$_pricingInProgress[$productId]);
        }
    }

    /**
     * @param float|null $qty
     * @param Mage_Catalog_Model_Product $product
     * @return float
     */
    public function getFinalPrice($qty, $product)
    {
        $storeId = $product->getStoreId();
        if (!Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore($storeId)) {
            return parent::getFinalPrice($qty, $product);
        }

        // A chosen product settles the price, so this is checked before anything else. It is
        // deliberately not behind set_price_is_lowest_price: that setting governs which figure the
        // product page shows before a choice is made, and has no bearing on what a chosen item
        // costs. Gating this on it charged the configurable's own price in the cart whenever the
        // setting was off - the parent SKU with the wrong money against it.
        $chosen = $this->_getChosenProduct($product);
        if ($chosen) {
            // Custom options belong to the configurable, so they are applied on top of the
            // chosen product's price, the same way core layers them onto a base price.
            $chosenPrice = $this->_applyOptionsPrice($product, $qty, $chosen->getFinalPrice($qty));
            $product->setFinalPrice($chosenPrice);

            return $chosenPrice;
        }

        if (!Mage::getStoreConfigFlag('simpleconfigurable/product_page/set_price_is_lowest_price', $storeId)) {
            return parent::getFinalPrice($qty, $product);
        }

        $productId = (int) $product->getId();
        if (isset(self::$_pricingInProgress[$productId])) {
            return parent::getFinalPrice($qty, $product);
        }
        self::$_pricingInProgress[$productId] = true;

        try {
            $child = $this->getChildProductWithLowestPrice($product, 'finalPrice', true);
            if (!$child) {
                $child = $this->getChildProductWithLowestPrice($product, 'finalPrice', false);
            }

            $finalPrice = $child ? $child->getFinalPrice() : $this->getPrice($product);
        } finally {
            unset(self::$_pricingInProgress[$productId]);
        }

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

        $chosen = $this->_getChosenProduct($product);
        if ($chosen) {
            return $chosen->getPrice();
        }

        if ($product->getData('indexed_price') !== null) {
            return $product->getData('indexed_price');
        }

        $productId = (int) $product->getId();
        if (isset(self::$_pricingInProgress[$productId])) {
            return parent::getPrice($product);
        }
        self::$_pricingInProgress[$productId] = true;

        try {
            $child = $this->getChildProductWithLowestPrice($product, 'price', true);
            if (!$child) {
                $child = $this->getChildProductWithLowestPrice($product, 'price', false);
            }

            return $child ? $child->getPrice() : parent::getPrice($product);
        } finally {
            unset(self::$_pricingInProgress[$productId]);
        }
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
            // A configurable is never its own associated product. Such rows do occur in real
            // catalogues, and including one makes the price walk below recurse into itself.
            if ((int) $child->getId() === (int) $product->getId()) {
                continue;
            }
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
