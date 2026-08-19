<?php

/**
 * Simple Configurable rewrite of the product price indexer resource model.
 *
 * When a configurable product's own price-affecting data is saved, this also
 * reindexes all of its associated (child) products, since Simple Configurable's
 * pricing is entirely derived from the children rather than the parent.
 * When a simple/virtual child product is saved, all of its sibling children
 * (products sharing the same configurable parent) are reindexed together with
 * the parent.
 */
class Ysrtech_SimpleConfigurable_Catalog_Model_Resource_Product_Indexer_Price extends Mage_Catalog_Model_Resource_Product_Indexer_Price
{
    /**
     * Get an array of child_id => type_id pairs for the given parent product id.
     *
     * @param int $parentId
     * @return array
     */
    private function getChildIdsByParent($parentId)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()
            ->from(['p' => $this->getTable('catalog/product')], ['entity_id'])
            ->join(
                ['pr' => $this->getTable('catalog/product_relation')],
                'pr.child_id = p.entity_id',
                ['p.type_id'],
            )
            ->where('pr.parent_id = ?', $parentId);

        return $read->fetchPairs($select);
    }

    /**
     * Get the product type id of a product, resolved via the product_relation table
     * (i.e. of a product that is itself a parent).
     *
     * @param int $id
     * @return string|null
     */
    private function getProductTypeById($id)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()
            ->from(['pr' => $this->getTable('catalog/product_relation')], ['parent_id'])
            ->join(
                ['p' => $this->getTable('catalog/product')],
                'pr.parent_id = p.entity_id',
                ['p.type_id'],
            )
            ->where('pr.parent_id = ?', $id);
        $data = $read->fetchRow($select);

        return $data['type_id'] ?? null;
    }

    /**
     * Process product save.
     *
     * @param Mage_Index_Model_Event $event
     * @return $this
     */
    public function catalogProductSave(Mage_Index_Model_Event $event)
    {
        $productId = $event->getEntityPk();
        $data = $event->getNewData();

        if (!isset($data['reindex_price'])) {
            return $this;
        }

        $this->clearTemporaryIndexTable();
        $this->_prepareWebsiteDateTable();

        $indexer = $this->_getIndexer($data['product_type_id']);
        $processIds = [$productId];

        if ($indexer->getIsComposite()) {
            if ($this->getProductTypeById($productId) == 'configurable') {
                $children = $this->getChildIdsByParent($productId);
                $processIds = array_merge($processIds, array_keys($children));
                // Ignore tier/group price data for the configurable product itself
                $tierPriceIds = array_keys($children);
            } else {
                $tierPriceIds = $productId;
            }
            $this->_copyRelationIndexData($productId);
            $this->_prepareTierPriceIndex($tierPriceIds);
            $this->_prepareGroupPriceIndex($tierPriceIds);
            $indexer->reindexEntity($productId);
        } else {
            $parentIds = $this->getProductParentsByChild($productId);

            if ($parentIds) {
                $processIds = array_merge($processIds, array_keys($parentIds));
                $siblingIds = [];
                foreach (array_keys($parentIds) as $parentId) {
                    $childIds = $this->getChildIdsByParent($parentId);
                    $siblingIds = array_merge($siblingIds, array_keys($childIds));
                }
                if (count($siblingIds) > 0) {
                    $processIds = array_unique(array_merge($processIds, $siblingIds));
                }

                $this->_copyRelationIndexData(array_keys($parentIds), $productId);
                $this->_prepareTierPriceIndex($processIds);
                $this->_prepareGroupPriceIndex($processIds);
                $indexer->reindexEntity($productId);

                $parentByType = [];
                foreach ($parentIds as $parentId => $parentType) {
                    $parentByType[$parentType][$parentId] = $parentId;
                }

                foreach ($parentByType as $parentType => $entityIds) {
                    $this->_getIndexer($parentType)->reindexEntity($entityIds);
                }
            } else {
                $this->_prepareTierPriceIndex($productId);
                $this->_prepareGroupPriceIndex($productId);
                $indexer->reindexEntity($productId);
            }
        }

        $this->_copyIndexDataToMainTable($processIds);

        return $this;
    }
}
