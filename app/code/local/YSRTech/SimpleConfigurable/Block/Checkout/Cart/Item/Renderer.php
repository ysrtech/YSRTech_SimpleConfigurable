<?php

/**
 * Simple Configurable rewrite of the cart item renderer.
 *
 * Resolves the configurable parent product for a cart item that was added via
 * a Simple Configurable child, and (depending on config) displays the parent's
 * name/image/url/attribute-derived options instead of the child's own.
 */
class YSRTech_SimpleConfigurable_Block_Checkout_Cart_Item_Renderer extends Mage_Checkout_Block_Cart_Item_Renderer
{
    /**
     * @return int|null
     */
    protected function getConfigurableProductParentId()
    {
        $cpidOption = $this->getItem()->getOptionByCode('cpid');
        if ($cpidOption) {
            return $cpidOption->getValue();
        }

        $buyRequestOption = $this->getItem()->getOptionByCode('info_buyRequest');
        if ($buyRequestOption && $buyRequestOption->getValue()) {
            $buyRequest = @unserialize($buyRequestOption->getValue(), ['allowed_classes' => false]);
            if (is_array($buyRequest) && !empty($buyRequest['cpid'])) {
                return $buyRequest['cpid'];
            }
        }

        return null;
    }

    /**
     * @return Mage_Catalog_Model_Product
     */
    protected function getConfigurableProductParent()
    {
        return Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($this->getConfigurableProductParentId());
    }

    /**
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($this->getItem()->getProductId());
    }

    /**
     * @return string
     */
    public function getProductName()
    {
        if (
            Mage::getStoreConfigFlag('simpleconfigurable/cart/show_configurable_product_name')
            && $this->getConfigurableProductParentId()
        ) {
            return $this->getConfigurableProductParent()->getName();
        }

        return parent::getProductName();
    }

    /**
     * @return bool
     */
    public function hasProductUrl()
    {
        if ($this->getConfigurableProductParentId()) {
            return true;
        }

        return parent::hasProductUrl();
    }

    /**
     * @return string
     */
    public function getProductUrl()
    {
        if ($this->getConfigurableProductParentId()) {
            return $this->getConfigurableProductParent()->getProductUrl();
        }

        return parent::getProductUrl();
    }

    /**
     * @return array|false
     */
    public function getOptionList()
    {
        $options = false;
        if (Mage::getStoreConfigFlag('simpleconfigurable/cart/show_custom_options')) {
            $options = parent::getOptionList();
        }

        if (
            Mage::getStoreConfigFlag('simpleconfigurable/cart/show_config_product_options')
            && $this->getConfigurableProductParentId()
        ) {
            if (!is_array($options)) {
                $options = [];
            }

            $attributes = $this->getConfigurableProductParent()
                ->getTypeInstance()
                ->getUsedProductAttributes();

            foreach ($attributes as $attribute) {
                $options[] = [
                    'label'     => $attribute->getFrontendLabel(),
                    'value'     => $this->getProduct()->getAttributeText($attribute->getAttributeCode()),
                    'option_id' => $attribute->getId(),
                ];
            }
        }

        return $options;
    }

    /**
     * Thumbnail resolution logic:
     * - Not a Simple Configurable item: default behavior.
     * - Configured to show the configurable product's image: use it.
     * - Configured to show the simple product's image: use it, unless it is a
     *   placeholder, in which case fall back to the configurable product's image.
     *
     * @return string
     */
    public function getProductThumbnail()
    {
        if (!$this->getConfigurableProductParentId()) {
            return parent::getProductThumbnail();
        }

        if (!Mage::getStoreConfigFlag('simpleconfigurable/cart/show_configurable_product_image')) {
            $product = $this->getProduct();
            if ($product->getData('thumbnail') && $product->getData('thumbnail') != 'no_selection') {
                return $this->helper('catalog/image')->init($product, 'thumbnail');
            }
        }

        $product = $this->getConfigurableProductParent();
        return $this->helper('catalog/image')->init($product, 'thumbnail');
    }
}
