<?php

/**
 * Simple Configurable rewrite of the configurable product view type block.
 *
 * Augments the standard super-attribute JS config with a "childProducts" map
 * (keyed by product id) carrying whichever of price/name/description/short
 * description/attributes/image are configured to update dynamically, so the
 * storefront JS can swap them in without a full page reload.
 */
class YSRTech_SimpleConfigurable_Block_Catalog_Product_View_Type_Configurable extends Mage_Catalog_Block_Product_View_Type_Configurable
{
    /**
     * @return string
     */
    public function getJsonConfig()
    {
        $storeId = $this->getProduct()->getStoreId();

        if (!Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore($storeId)) {
            return parent::getJsonConfig();
        }

        $config = Zend_Json::decode(parent::getJsonConfig());

        $changeName             = Mage::getStoreConfigFlag('simpleconfigurable/product_page/change_name', $storeId);
        $changeDescription      = Mage::getStoreConfigFlag('simpleconfigurable/product_page/change_description', $storeId);
        $changeShortDescription = Mage::getStoreConfigFlag('simpleconfigurable/product_page/change_short_description', $storeId);
        $changeAttributes       = Mage::getStoreConfigFlag('simpleconfigurable/product_page/change_attributes', $storeId);
        $changeImage            = Mage::getStoreConfigFlag('simpleconfigurable/product_page/change_image', $storeId);
        $changeImageFancy       = Mage::getStoreConfigFlag('simpleconfigurable/product_page/change_image_fancy', $storeId);
        $showPriceRanges        = Mage::getStoreConfigFlag('simpleconfigurable/product_page/show_price_ranges_in_options', $storeId);

        $childProducts = [];
        foreach ($this->getAllowProducts() as $product) {
            $data = [
                'price'      => $this->_registerJsPrice($this->_convertPrice($product->getPrice())),
                'finalPrice' => $this->_registerJsPrice($this->_convertPrice($product->getFinalPrice())),
            ];

            if ($changeName) {
                $data['productName'] = $product->getName();
            }
            if ($changeDescription) {
                $data['description'] = $product->getDescription();
            }
            if ($changeShortDescription) {
                $data['shortDescription'] = $product->getShortDescription();
            }
            if ($changeAttributes) {
                $attributesBlock = $this->getLayout()->createBlock('catalog/product_view_attributes')
                    ->setProduct($product)
                    ->setTemplate('catalog/product/view/attributes.phtml');
                $data['productAttributes'] = $attributesBlock->toHtml();
            }
            if ($changeImage) {
                $imageHelper = $this->helper('catalog/image')->init($product, 'image');
                $data['imageUrl'] = (string) $imageHelper;
            }

            $childProducts[$product->getId()] = $data;
        }

        // The parent config's per-attribute-option "price" values are meaningless for
        // Simple Configurable (all pricing/content comes from the selected child), so
        // they are stripped to avoid the core JS applying them on top of ours.
        foreach ($config['attributes'] as &$attribute) {
            foreach ($attribute['options'] as &$option) {
                unset($option['price'], $option['oldPrice']);
            }
            unset($option);
        }
        unset($attribute);

        // The parent's own values, used whenever the JS resets to "nothing selected". Without
        // them the update helpers read undefined off the config and write the string "undefined"
        // into the page. Each is emitted only when its feature is on, matching $childProducts.
        $parent = $this->getProduct();
        if ($changeName) {
            $config['productName'] = $parent->getName();
        }
        if ($changeDescription) {
            $config['description'] = $parent->getDescription();
        }
        if ($changeShortDescription) {
            $config['shortDescription'] = $parent->getShortDescription();
        }
        if ($changeAttributes) {
            $config['productAttributes'] = $this->getLayout()
                ->createBlock('catalog/product_view_attributes')
                ->setProduct($parent)
                ->setTemplate('catalog/product/view/attributes.phtml')
                ->toHtml();
        }
        if ($changeImage) {
            $config['imageUrl'] = (string) Mage::helper('catalog/image')->init($parent, 'image');
        }

        // Display strings for the min-max spans, so the JS can put them back when a shopper
        // clears their selection and the exact child price no longer applies.
        $priceBlock = $this->getLayout()->createBlock('catalog/product_price');
        if (is_callable([$priceBlock, 'getFormattedPriceRanges'])) {
            $ranges = $priceBlock->getFormattedPriceRanges($parent);
            $config['priceRange']    = $ranges['final'];
            $config['oldPriceRange'] = $ranges['regular'];
        }

        // When set, add to cart is left exactly as core does it: the form keeps posting the
        // configurable's own id together with the chosen super_attribute values, so Magento
        // builds a configurable cart item carrying the parent SKU.
        $config['addParentToCart'] = Mage::getStoreConfigFlag('simpleconfigurable/cart/add_configurable_parent', $storeId);

        $config['childProducts']          = $childProducts;
        $config['priceFromLabel']         = $this->__('Price From:');
        $config['ajaxBaseUrl']             = Mage::getUrl('scp/ajax/');
        $config['showPriceRangesInOptions'] = $showPriceRanges;
        $config['rangeToLabel']            = $this->__(' - ');

        if ($changeImageFancy) {
            $mediaBlock = $this->getLayout()->createBlock('catalog/product_view_media')
                ->setTemplate('catalog/product/view/media.phtml');
            $config['imageZoomer'] = $mediaBlock->toHtml();
        }

        return Zend_Json::encode($config);
    }
}
