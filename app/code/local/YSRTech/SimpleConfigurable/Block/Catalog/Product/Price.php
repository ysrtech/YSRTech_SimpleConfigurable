<?php

/**
 * Simple Configurable rewrite of the product price block.
 *
 * A configurable's price is delegated to its associated products, so a single figure is
 * misleading whenever those products differ in price. This block renders the span instead -
 * "$107.00 - $130.00" - for both the regular price and, when a special price or catalog price
 * rule applies, the sale price. Where the associated products all cost the same there is no
 * range and the price renders exactly as core does.
 *
 * The range is written into the already-rendered price HTML rather than through a template of
 * its own, so it applies to every theme and to listings, the RSS feeds and anywhere else the
 * price block is used (Mage_Rss_Block_Catalog_Abstract::getPriceHtml() renders through this
 * same class). Elements are located by the ids core gives them - product-price-<id> and
 * old-price-<id> - which is also what the JS updates once a shopper picks an option.
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

    /**
     * Lowest and highest price across the associated products, as [regular, final] pairs.
     *
     * Prefers in-stock children, matching how the rest of the module prices a configurable, and
     * falls back to all of them when nothing is salable so the page still shows something.
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array{regular: float[], final: float[]}|null null when there is nothing to compare
     */
    public function getConfigurablePriceRange($product)
    {
        $priceModel = $product->getPriceModel();
        if (!is_callable([$priceModel, 'getChildProducts'])) {
            return null;
        }

        $children = $priceModel->getChildProducts($product, true);
        if (!$children) {
            $children = $priceModel->getChildProducts($product, false);
        }
        if (count($children) < 2) {
            return null;
        }

        $regular = [];
        $final   = [];
        foreach ($children as $child) {
            $regular[] = (float) $child->getPrice();
            $final[]   = (float) $child->getFinalPrice();
        }

        return [
            'regular' => [min($regular), max($regular)],
            'final'   => [min($final), max($final)],
        ];
    }

    /**
     * @param float[] $range
     * @return string|null null when both ends are the same, i.e. there is no range to show
     */
    protected function _formatRange(array $range)
    {
        [$low, $high] = $range;
        if (abs($high - $low) < 0.00001) {
            return null;
        }

        $helper = Mage::helper('core');

        return $helper->currency($low, true, false) . ' - ' . $helper->currency($high, true, false);
    }

    /**
     * Replaces the money text inside the element carrying $id.
     *
     * Two shapes occur, depending on whether a special price is in play:
     *   <span class="regular-price" id="product-price-5"><span class="price">$107.00</span></span>
     *   <span class="price" id="old-price-5">$60.00</span>
     * The nested one is tried first: matching the outer span against a lazy </span> would close
     * on the inner tag and leave the markup unbalanced.
     *
     * @param string $html
     * @param string $id
     * @param string $replacement
     * @return string
     */
    protected function _replacePrice($html, $id, $replacement)
    {
        $quoted = preg_quote($id, '#');

        // A callback rather than a replacement string: formatted money starts with a currency
        // symbol, and "$107.00" in a replacement would be read as backreference $1 followed by
        // "07.00".
        $swap = function (array $m) use ($replacement) {
            return $m[1] . $replacement . $m[3];
        };

        $nested = '#(<span[^>]*\bid="' . $quoted . '"[^>]*>\s*<span[^>]*class="[^"]*\bprice\b[^"]*"[^>]*>)([^<]*)(</span>)#s';
        if (preg_match($nested, $html)) {
            return preg_replace_callback($nested, $swap, $html, 1);
        }

        $direct = '#(<span[^>]*\bid="' . $quoted . '"[^>]*>)([^<]*)(</span>)#s';

        return preg_replace_callback($direct, $swap, $html, 1);
    }

    /**
     * The two ranges as display strings, for reuse by the JS config.
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array{final: string|null, regular: string|null}
     */
    public function getFormattedPriceRanges($product)
    {
        $range = $this->getConfigurablePriceRange($product);
        if (!$range) {
            return ['final' => null, 'regular' => null];
        }

        return [
            'final'   => $this->_formatRange($range['final']),
            'regular' => $this->_formatRange($range['regular']),
        ];
    }

    protected function _toHtml()
    {
        if (!in_array($this->getTemplate(), self::$_priceFromTemplates, true)) {
            return parent::_toHtml();
        }

        $product = $this->getProduct();
        if (!is_object($product) || !$product->isConfigurable()) {
            return parent::_toHtml();
        }

        $html = parent::_toHtml();
        if ($html === '') {
            return $html;
        }

        $ranges = $this->getFormattedPriceRanges($product);
        $suffix    = $product->getId() . $this->getIdSuffix();
        $finalText = $ranges['final'];
        $regText   = $ranges['regular'];
        if ($finalText === null && $regText === null) {
            return $html;
        }

        // product-price-<id> holds the sale price when one applies and the regular price
        // otherwise, which is exactly the figure the final-price range describes.
        if ($finalText !== null) {
            $html = $this->_replacePrice($html, 'product-price-' . $suffix, $finalText);
        }
        // old-price-<id> only exists when a special price or catalog rule is active.
        if ($regText !== null) {
            $html = $this->_replacePrice($html, 'old-price-' . $suffix, $regText);
        }

        return $html;
    }
}
