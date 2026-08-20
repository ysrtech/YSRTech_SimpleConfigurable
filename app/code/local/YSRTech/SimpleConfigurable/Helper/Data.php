<?php

class YSRTech_SimpleConfigurable_Helper_Data extends Mage_Core_Helper_Abstract
{
    /** Swatch data already worked out this request, keyed by product and store. */
    protected $_swatchData = [];

    /** Parent ids whose children this request has already loaded. */
    protected $_preloadedChildren = [];

    /** Formatted money, keyed by store and amount - Zend_Currency is slow per call. */
    protected $_formatted = [];

    /**
     * Whether Simple Configurable is enabled for the given store
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isModuleActiveOnStore($storeId = null)
    {
        if ($storeId === 'undefined') {
            return false;
        }

        return Mage::getStoreConfigFlag('simpleconfigurable/general/enabled', $storeId);
    }

    /**
     * Load the children of many configurables at once.
     *
     * A listing asking each tile for its own children runs a child collection per product, and
     * a catalogue-rule lookup per child on top; on a few hundred tiles that costs seconds. One
     * collection answers the whole page, and the loaded children are handed to the price model
     * so the from-price, the swatches and anything else asking share them.
     *
     * Optional: without it getConfigurableSwatchData() still works one product at a time.
     *
     * @param iterable $products
     * @return $this
     */
    public function preloadConfigurableSwatchData($products)
    {
        $parents = [];
        foreach ($products as $product) {
            if (
                $product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
                && !isset($this->_preloadedChildren[$product->getId()])
            ) {
                $parents[$product->getId()] = $product;
            }
        }
        if (!$parents) {
            return $this;
        }

        $resource = Mage::getSingleton('core/resource');
        $links    = $resource->getConnection('core_read')->fetchAll(
            $resource->getConnection('core_read')->select()
                ->from($resource->getTableName('catalog/product_super_link'), ['parent_id', 'product_id'])
                ->where('parent_id IN (?)', array_keys($parents))
        );

        $childIds = [];
        $byParent = [];
        foreach ($links as $link) {
            // A configurable is never its own associated product; such rows do occur.
            if ($link['parent_id'] == $link['product_id']) {
                continue;
            }
            $childIds[$link['product_id']] = $link['product_id'];
            $byParent[$link['parent_id']][] = $link['product_id'];
        }

        // Every option attribute used anywhere on the page, so one select covers all parents.
        $select = ['price', 'special_price', 'special_from_date', 'special_to_date', 'status', 'tax_class_id'];
        foreach ($parents as $parent) {
            foreach ($parent->getTypeInstance(true)->getUsedProductAttributes($parent) as $attribute) {
                $select[] = $attribute->getAttributeCode();
            }
        }

        $storeId = Mage::app()->getStore()->getId();
        $loaded  = [];
        if ($childIds) {
            /**
             * Deliberately no addPriceData(): it inner-joins the price index, which holds no row
             * for an out-of-stock product unless the store shows those, so every sold-out size
             * would vanish from the swatches instead of being drawn as unavailable.
             */
            $collection = Mage::getResourceModel('catalog/product_collection')
                ->setStoreId($storeId)
                ->addIdFilter(array_values($childIds))
                ->addAttributeToSelect(array_unique($select))
                ->addFilterByRequiredOptions();
            Mage::getSingleton('cataloginventory/stock')->addItemsToProducts($collection);

            /**
             * Prices still come from the price model, exactly as on the product page, so the two
             * cannot drift apart. This event is what makes that affordable here: the catalogue
             * rule observer looks up every product's rule price in one query, instead of one per
             * child as each is asked for its final price.
             */
            Mage::dispatchEvent('prepare_catalog_product_collection_prices', [
                'collection' => $collection,
                'store_id'   => $storeId,
            ]);

            foreach ($collection as $child) {
                $loaded[$child->getId()] = $child;
            }
        }

        foreach ($parents as $parentId => $parent) {
            $children = [];
            foreach ($byParent[$parentId] ?? [] as $childId) {
                if (isset($loaded[$childId])) {
                    $children[] = $loaded[$childId];
                }
            }
            $this->_preloadedChildren[$parentId] = true;

            $priceModel = $parent->getPriceModel();
            if (is_callable([get_class($priceModel), 'primeChildProducts'])) {
                $priceModel::primeChildProducts($parentId, $children);
            }
        }

        return $this;
    }

    /**
     * @param float $amount
     * @return string
     */
    protected function _formatPrice($amount)
    {
        $key = Mage::app()->getStore()->getId() . ':' . $amount;
        if (!isset($this->_formatted[$key])) {
            $this->_formatted[$key] = Mage::helper('core')->currency($amount, true, false);
        }

        return $this->_formatted[$key];
    }

    /**
     * Everything a template needs to draw configurable swatches and let the skin's
     * configurable-price.js move the price as they are picked:
     *
     *   attributes - as getConfigurableAttributesAsArray() returns them
     *   stock      - attribute id => value index => is anything saleable at that value
     *   children   - child id => values, saleability, and price both as a number to
     *                compare on and as the string the server formatted, so the browser
     *                never formats currency itself and cannot disagree with the page
     *
     * The product view and the category listing both render from this, so a change to
     * how a swatch prices happens in one place.
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getConfigurableSwatchData($product)
    {
        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            return ['attributes' => [], 'stock' => [], 'children' => []];
        }

        $key = $product->getId() . '-' . $product->getStoreId();
        if (isset($this->_swatchData[$key])) {
            return $this->_swatchData[$key];
        }

        $type       = $product->getTypeInstance(true);
        $attributes = $type->getConfigurableAttributesAsArray($product);

        /**
         * getUsedProducts() does not select special_price or its date range, so asking those
         * items for a final price quietly returns the plain one and every child looks
         * undiscounted. The price model's collection selects what pricing needs along with the
         * option values, so one load answers both questions - which matters on a listing, where
         * the alternative is two child collections per tile.
         */
        $priceModel = $product->getPriceModel();
        $children   = is_callable([$priceModel, 'getChildProducts'])
            ? $priceModel->getChildProducts($product, false)
            : $type->getUsedProducts(null, $product);

        $priceData = [];
        $stock     = [];

        foreach ($children as $child) {
            $salable = $child->isSaleable();

            $values = [];
            foreach ($attributes as $attribute) {
                $value = $child->getData($attribute['attribute_code']);
                $values[$attribute['attribute_id']] = (string) $value;
                if (!isset($stock[$attribute['attribute_id']][$value])) {
                    $stock[$attribute['attribute_id']][$value] = false;
                }
                if ($salable) {
                    $stock[$attribute['attribute_id']][$value] = true;
                }
            }

            $price = (float) $child->getPrice();
            $final = (float) $child->getFinalPrice();

            $priceData[$child->getId()] = [
                'values'     => $values,
                'salable'    => $salable,
                'price'      => $price,
                'finalPrice' => $final,
                'priceText'  => $this->_formatPrice($price),
                'finalText'  => $this->_formatPrice($final),
            ];
        }

        /**
         * Nothing to narrow when every child costs the same: picking a size cannot move a price
         * that has only one value, so the browser is handed no prices at all rather than a copy
         * of one figure per child. On a catalogue where most products are one price across the
         * size run that is the bulk of the payload, and all of the work behind it.
         */
        if (!$this->_hasDistinctPrices($priceData)) {
            $priceData = [];
        }

        return $this->_swatchData[$key] = [
            'attributes' => $attributes,
            'stock'      => $stock,
            'children'   => $priceData,
        ];
    }

    /**
     * @param array $priceData
     * @return bool whether the children differ in price at all
     */
    protected function _hasDistinctPrices(array $priceData)
    {
        $first = null;
        foreach ($priceData as $child) {
            $pair = [$child['price'], $child['finalPrice']];
            if ($first === null) {
                $first = $pair;
            } elseif ($pair !== $first) {
                return true;
            }
        }

        return false;
    }
}
