<?php

/**
 * Simple Configurable rewrite of the product price block.
 *
 * For configurable products where the cheapest and most expensive associated
 * products differ in price, injects a "Price From:" label into the rendered
 * price HTML. Applies both on the storefront product price template and the
 * RSS feed price template (Mage_Rss_Block_Catalog_Abstract::getPriceHtml()
 * renders prices through this same block class).
 */
class YSRTech_SimpleConfigurable_Block_Catalog_Product_Price extends Mage_Catalog_Block_Product_Price
{
    /**
     * @var string[]
     */
    private static $_priceFromTemplates = [
        'catalog/product/price.phtml',
        'catalog/rss/product/price.phtml',
    ];

    protected function _toHtml()
    {
        if (in_array($this->getTemplate(), self::$_priceFromTemplates, true)) {
            $product = $this->getProduct();

            if (is_object($product) && $product->isConfigurable()) {
                $extraHtml = '<span class="label" id="configurable-price-from-'
                    . $product->getId() . $this->getIdSuffix()
                    . '"><span class="configurable-price-from-label">';

                if ($product->getMaxPossibleFinalPrice() != $product->getFinalPrice()) {
                    $extraHtml .= $this->__('Price From:');
                }
                $extraHtml .= '</span></span>';

                $priceHtml = parent::_toHtml();

                $htmlToInsertAfter = '<div class="price-box">';
                $insertPos = strpos($priceHtml, $htmlToInsertAfter);
                if ($priceHtml !== '' && $insertPos !== false) {
                    return substr_replace($priceHtml, $extraHtml, $insertPos + strlen($htmlToInsertAfter), 0);
                }

                return $priceHtml;
            }
        }

        return parent::_toHtml();
    }
}
