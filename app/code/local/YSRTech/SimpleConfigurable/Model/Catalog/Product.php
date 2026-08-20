<?php

/**
 * Simple Configurable rewrite of the product model.
 */
class YSRTech_SimpleConfigurable_Model_Catalog_Product extends Mage_Catalog_Model_Product
{
    /**
     * @return float
     */
    public function getMaxPossibleFinalPrice()
    {
        $priceModel = $this->getPriceModel();
        if (is_callable([$priceModel, 'getMaxPossibleFinalPrice'])) {
            return $priceModel->getMaxPossibleFinalPrice($this);
        }
        return parent::getMaxPrice();
    }

    /**
     * A Simple Configurable child product is not directly visible/orderable on its
     * own; force it to report as visible so link/url generation etc. still work
     * when it is being rendered in the context of its configurable parent.
     *
     * @return bool
     */
    public function isVisibleInSiteVisibility()
    {
        $typeInstance = $this->getTypeInstance();
        if (is_callable([$typeInstance, 'hasConfigurableProductParentId']) && $typeInstance->hasConfigurableProductParentId()) {
            return true;
        }
        return parent::isVisibleInSiteVisibility();
    }

    /**
     * True once a specific child has been chosen for this configurable - on a
     * quote item, an order item, or a product prepared for the cart.
     *
     * @return bool
     */
    protected function _hasChosenChild()
    {
        $option = $this->getCustomOption('simple_product');
        if (!$option || !is_object($option->getProduct())) {
            return false;
        }

        return $option->getProduct()->getId() != $this->getId();
    }

    /**
     * @param null|float $qty
     * @return float
     */
    public function getFinalPrice($qty = null)
    {
        $storeId = $this->getStoreId();

        /**
         * min_price/final_price are the "cheapest child" figures a listing or a
         * price-indexed collection puts on the product so the catalogue can show
         * a from-price without loading every child. They describe the whole
         * configurable, so they must never stand in for the price of a child the
         * shopper actually picked - otherwise a cart line is charged the lowest
         * child's price (or the parent's own catalogue-rule price) instead of the
         * chosen one's.
         */
        if (
            !$this->_hasChosenChild()
            && Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore($storeId)
            && Mage::getStoreConfigFlag('simpleconfigurable/product_page/set_price_is_lowest_price', $storeId)
        ) {
            $price = $this->getData('min_price');
            if ($price !== null) {
                return $price;
            }
            $price = $this->getData('final_price');
            if ($price !== null) {
                return $price;
            }
        }

        return $this->getPriceModel()->getFinalPrice($qty, $this);
    }

    /**
     * If this product is a Simple Configurable child, always link to the
     * configurable parent's product URL instead of its own.
     *
     * @param bool|null $useSid
     * @return string
     */
    public function getProductUrl($useSid = null)
    {
        $typeInstance = $this->getTypeInstance();
        if (is_callable([$typeInstance, 'getConfigurableProductParentId'])) {
            $parentId = $typeInstance->getConfigurableProductParentId();
            if ($parentId) {
                $parent = Mage::getModel('catalog/product')->load($parentId);
                if ($parent->getId()) {
                    return $parent->getProductUrl($useSid);
                }
            }
        }

        return parent::getProductUrl($useSid);
    }
}
