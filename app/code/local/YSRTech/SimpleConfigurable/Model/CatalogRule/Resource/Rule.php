<?php

/**
 * Simple Configurable rewrite of the catalog price rule resource model.
 *
 * Fixes a core issue where {@see Mage_CatalogRule_Model_Rule::applyAllRulesToProduct()}
 * calls {@see applyToProduct()} once per active rule for the same product. When one
 * of several rules no longer validates for that product, core's "else" branch deletes
 * ALL rule_product_price rows for the product - including rows that were just
 * inserted moments earlier by OTHER rules that DID validate.
 *
 * That delete is unnecessary anyway: every call path that reaches applyToProduct()
 * always calls applyAllRules($product) afterwards (either immediately, in
 * Mage_CatalogRule_Model_Rule::applyToProduct(), or once after the full per-rule
 * loop, in applyAllRulesToProduct()), which fully rebuilds rule_product_price for
 * the product from the authoritative catalogrule_product eligibility table. So this
 * override simply skips the interim delete rather than scoping it, leaving the
 * final, correct state to be produced by that rebuild.
 */
class YSRTech_SimpleConfigurable_Model_CatalogRule_Resource_Rule extends Mage_CatalogRule_Model_Resource_Rule
{
    /**
     * Apply rule to product
     *
     * @param Mage_CatalogRule_Model_Rule $rule
     * @param Mage_Catalog_Model_Product $product
     * @param array $websiteIds
     * @return $this
     */
    public function applyToProduct($rule, $product, $websiteIds)
    {
        if (!$rule->getIsActive()) {
            return $this;
        }

        $ruleId    = $rule->getId();
        $productId = $product->getId();

        $write = $this->_getWriteAdapter();
        $write->beginTransaction();
        try {
            if ($this->_isProductMatchedRule($ruleId, $product)) {
                $this->cleanProductData($ruleId, [$productId]);
            }
            if ($this->validateProduct($rule, $product, $websiteIds)) {
                $this->insertRuleData($rule, $websiteIds, [
                    $productId => array_combine(array_values($websiteIds), array_values($websiteIds)),
                ]);
            }
            // Intentionally NOT deleting from catalogrule/rule_product_price here when
            // the rule fails validation - see class docblock above.

            $write->commit();
        } catch (Exception $e) {
            $write->rollBack();
            throw $e;
        }
        return $this;
    }
}
