<?php

/**
 * Simple Configurable rewrite of the configurable product stock status indexer.
 *
 * Ignores the configurable product's own stock status entirely; a configurable
 * product is considered in stock if ANY of its associated (child) products is
 * enabled and in stock. Also does not filter children by required custom options.
 */
class Ysrtech_SimpleConfigurable_CatalogInventory_Model_Resource_Indexer_Stock_Configurable extends Mage_CatalogInventory_Model_Resource_Indexer_Stock_Configurable
{
    /**
     * @param int|array|null $entityIds
     * @param bool $usePrimaryTable
     * @return Varien_Db_Select
     */
    protected function _getStockStatusSelect($entityIds = null, $usePrimaryTable = false)
    {
        $adapter  = $this->_getWriteAdapter();
        $idxTable = $usePrimaryTable ? $this->getMainTable() : $this->getIdxTable();
        $select  = $adapter->select()
            ->from(['e' => $this->getTable('catalog/product')], ['entity_id']);
        $this->_addWebsiteJoinToSelect($select, true);
        $select->columns('cw.website_id')
            ->join(
                ['cis' => $this->getTable('cataloginventory/stock')],
                '',
                ['stock_id'],
            )
            ->joinLeft(
                ['l' => $this->getTable('catalog/product_super_link')],
                'l.parent_id = e.entity_id',
                [],
            )
            ->join(
                ['le' => $this->getTable('catalog/product')],
                'le.entity_id = l.product_id',
                [],
            )
            ->joinLeft(
                ['cisi' => $this->getTable('cataloginventory/stock_item')],
                'cisi.stock_id = cis.stock_id AND cisi.product_id = le.entity_id',
                [],
            )
            ->joinLeft(
                ['i' => $idxTable],
                'i.product_id = le.entity_id AND cw.website_id = i.website_id AND cis.stock_id = i.stock_id',
                [],
            )
            ->columns(['qty' => new Zend_Db_Expr('0')])
            ->where('cw.website_id != 0')
            ->where('e.type_id = ?', $this->getTypeId())
            ->group(['e.entity_id', 'cw.website_id', 'cis.stock_id']);

        $this->_addProductWebsiteJoinToSelect($select, 'cw.website_id', 'le.entity_id');

        $psExpr = $this->_addAttributeToSelect($select, 'status', 'le.entity_id', 'cs.store_id');
        $psCond = $adapter->quoteInto($psExpr . ' = ?', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        if ($this->_isManageStock()) {
            $statusExpr = $adapter->getCheckSql(
                'cisi.use_config_manage_stock = 0 AND cisi.manage_stock = 0',
                1,
                'cisi.is_in_stock',
            );
        } else {
            $statusExpr = $adapter->getCheckSql(
                'cisi.use_config_manage_stock = 0 AND cisi.manage_stock = 1',
                'cisi.is_in_stock',
                1,
            );
        }

        $stockStatusExpr = new Zend_Db_Expr("MAX(LEAST(IF({$psCond}, 1, 0), {$statusExpr}))");

        $select->columns([
            'status' => $stockStatusExpr,
        ]);

        if ($entityIds !== null) {
            $select->where('e.entity_id IN(?)', $entityIds);
        }

        return $select;
    }
}
