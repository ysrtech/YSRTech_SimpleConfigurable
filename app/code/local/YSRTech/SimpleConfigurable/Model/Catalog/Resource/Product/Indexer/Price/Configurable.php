<?php

/**
 * Simple Configurable rewrite of the configurable product price indexer.
 *
 * Calculates the configurable product's indexed price directly from its
 * cheapest in-stock associated (child) product, instead of the configurable
 * product's own price plus per-attribute price modifiers.
 *
 * Uses a subquery/group-by trick (MySQL picks the first row of each GROUP BY
 * group) to ensure the row with the lowest final price (in-stock rows first)
 * "wins" for every other column too.
 * @see https://dev.mysql.com/doc/refman/8.0/en/example-maximum-column-group-row.html
 */
class YSRTech_SimpleConfigurable_Model_Catalog_Resource_Product_Indexer_Price_Configurable extends Mage_Catalog_Model_Resource_Product_Indexer_Price_Configurable
{
    /**
     * @return bool
     */
    protected function _isManageStock()
    {
        return Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);
    }

    /**
     * Simple Configurable does not use per-attribute price modifiers on
     * configurable products; all pricing comes directly from the children.
     *
     * @return $this
     */
    protected function _applyConfigurableOption()
    {
        return $this;
    }

    /**
     * @param array|null $entityIds
     * @return $this
     */
    protected function _prepareFinalPriceData($entityIds = null)
    {
        $this->_prepareDefaultFinalPriceTable();

        $write  = $this->_getWriteAdapter();
        $select = $write->select()
            ->from(['e' => $this->getTable('catalog/product')], [])
            ->joinLeft(
                ['l' => $this->getTable('catalog/product_super_link')],
                'l.parent_id = e.entity_id',
                [],
            )
            ->join(
                ['ce' => $this->getTable('catalog/product')],
                'ce.entity_id = l.product_id',
                [],
            )
            ->join(
                ['pi' => $this->getIdxTable()],
                'ce.entity_id = pi.entity_id',
                [],
            )
            ->join(
                ['cw' => $this->getTable('core/website')],
                'pi.website_id = cw.website_id',
                [],
            )
            ->join(
                ['csg' => $this->getTable('core/store_group')],
                'csg.website_id = cw.website_id AND cw.default_group_id = csg.group_id',
                [],
            )
            ->join(
                ['cs' => $this->getTable('core/store')],
                'csg.default_store_id = cs.store_id AND cs.store_id != 0',
                [],
            )
            ->join(
                ['cis' => $this->getTable('cataloginventory/stock')],
                '',
                [],
            )
            ->joinLeft(
                ['cisi' => $this->getTable('cataloginventory/stock_item')],
                'cisi.stock_id = cis.stock_id AND cisi.product_id = ce.entity_id',
                [],
            )
            ->where('e.type_id = ?', $this->getTypeId());

        $productStatusExpr = $this->_addAttributeToSelect($select, 'status', 'ce.entity_id', 'cs.store_id');

        if ($this->_isManageStock()) {
            $stockStatusExpr = new Zend_Db_Expr(
                'IF(cisi.use_config_manage_stock = 0 AND cisi.manage_stock = 0, 1, cisi.is_in_stock)',
            );
        } else {
            $stockStatusExpr = new Zend_Db_Expr(
                'IF(cisi.use_config_manage_stock = 0 AND cisi.manage_stock = 1, cisi.is_in_stock, 1)',
            );
        }
        $isInStockExpr = new Zend_Db_Expr("IF({$stockStatusExpr}, 1, 0)");
        $isValidChildProductExpr = new Zend_Db_Expr("{$productStatusExpr}");

        $select->columns([
            'entity_id'         => new Zend_Db_Expr('e.entity_id'),
            'customer_group_id' => new Zend_Db_Expr('pi.customer_group_id'),
            'website_id'        => new Zend_Db_Expr('cw.website_id'),
            'tax_class_id'      => new Zend_Db_Expr('pi.tax_class_id'),
            'orig_price'        => new Zend_Db_Expr('pi.price'),
            'price'             => new Zend_Db_Expr('pi.final_price'),
            'min_price'         => new Zend_Db_Expr('pi.final_price'),
            'max_price'         => new Zend_Db_Expr('pi.final_price'),
            'tier_price'        => new Zend_Db_Expr('pi.tier_price'),
            'base_tier'         => new Zend_Db_Expr('pi.tier_price'),
            'group_price'       => new Zend_Db_Expr('pi.group_price'),
            'base_group_price'  => new Zend_Db_Expr('pi.group_price'),
        ]);

        if ($entityIds !== null) {
            $select->where('e.entity_id IN(?)', $entityIds);
        }

        // Inner select order needs to be:
        // 1) In-stock rows first (out-of-stock child prices are ignored unless ALL children are out of stock)
        // 2) Final price ascending
        // 3) Price ascending, in case all final prices are NULL (gives the lowest price when all children are out of stock)
        $sortExpr = new Zend_Db_Expr("{$isInStockExpr} DESC, pi.final_price ASC, pi.price ASC");
        $select->order($sortExpr);

        // Add additional external limitation
        Mage::dispatchEvent('prepare_catalog_product_index_select', [
            'select'        => $select,
            'entity_field'  => new Zend_Db_Expr('e.entity_id'),
            'website_field' => new Zend_Db_Expr('cw.website_id'),
            'store_field'   => new Zend_Db_Expr('cs.store_id'),
        ]);

        // MySQL's GROUP BY picks the first row of each group, and the subselect above
        // is ordered exactly so that "first row" is the one we want to win.
        $outerSelect = $write->select()
            ->from(['inner' => $select], 'entity_id')
            ->group(['inner.entity_id', 'inner.customer_group_id', 'inner.website_id']);

        $outerSelect->columns([
            'customer_group_id',
            'website_id',
            'tax_class_id',
            'orig_price',
            'price',
            'min_price',
            'max_price' => new Zend_Db_Expr('MAX(inner.max_price)'),
            'tier_price',
            'base_tier',
            'group_price',
            'base_group_price',
        ]);

        $query = $outerSelect->insertFromSelect($this->_getDefaultFinalPriceTable());
        $write->query($query);

        // Allow external listeners to modify prices further (e.g. catalog price rules)
        $select = $write->select()
            ->join(
                ['wd' => $this->_getWebsiteDateTable()],
                'i.website_id = wd.website_id',
                [],
            );
        Mage::dispatchEvent('prepare_catalog_product_price_index_table', [
            'index_table'       => ['i' => $this->_getDefaultFinalPriceTable()],
            'select'            => $select,
            'entity_id'         => 'i.entity_id',
            'customer_group_id' => 'i.customer_group_id',
            'website_id'        => 'i.website_id',
            'website_date'      => 'wd.website_date',
            'update_fields'     => ['price', 'min_price', 'max_price'],
        ]);

        return $this;
    }
}
