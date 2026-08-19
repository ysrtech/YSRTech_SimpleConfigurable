<?php

/**
 * Simple Configurable rewrite: allow an externally supplied product (the selected
 * child of a configurable product) to be used when rendering the "additional
 * information" attributes block, without disturbing the globally registered product.
 */
class Ysrtech_SimpleConfigurable_Catalog_Block_Product_View_Attributes extends Mage_Catalog_Block_Product_View_Attributes
{
    /**
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function setProduct($product)
    {
        $this->_product = $product;
        return $this;
    }
}
