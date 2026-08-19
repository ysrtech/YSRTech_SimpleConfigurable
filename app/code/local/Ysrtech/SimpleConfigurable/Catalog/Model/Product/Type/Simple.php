<?php

/**
 * Simple Configurable rewrite of the simple product type model.
 *
 * Tracks the id of the configurable parent product a simple product was added to
 * cart through (the "cpid" custom option), so downstream code (cart item renderer,
 * product URL generation, visibility checks) can treat this simple product as a
 * child of that configurable product.
 */
class Ysrtech_SimpleConfigurable_Catalog_Model_Product_Type_Simple extends Mage_Catalog_Model_Product_Type_Simple
{
    /**
     * @param Varien_Object $buyRequest
     * @param Mage_Catalog_Model_Product|null $product
     * @return Mage_Catalog_Model_Product[]|string
     */
    public function prepareForCart(Varien_Object $buyRequest, $product = null)
    {
        $result = parent::prepareForCart($buyRequest, $product);

        if (is_array($result) && $buyRequest->getCpid()) {
            foreach ($result as $resultProduct) {
                $resultProduct->addCustomOption('cpid', $buyRequest->getCpid());
            }
        }

        return $result;
    }

    /**
     * Resolve the configurable parent id this product is associated with, either
     * from an already-set "cpid" custom option, or from the serialized buy request
     * custom option stored on a quote/order item.
     *
     * @return int|null
     */
    private function getCpid()
    {
        $product = $this->getProduct();

        $cpidOption = $product->getCustomOption('cpid');
        if ($cpidOption && $cpidOption->getValue()) {
            return $cpidOption->getValue();
        }

        $buyRequestOption = $product->getCustomOption('info_buyRequest');
        if ($buyRequestOption && $buyRequestOption->getValue()) {
            $buyRequest = @unserialize($buyRequestOption->getValue(), ['allowed_classes' => false]);
            if (is_array($buyRequest) && !empty($buyRequest['cpid'])) {
                return $buyRequest['cpid'];
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    public function hasConfigurableProductParentId()
    {
        return !empty($this->getCpid());
    }

    /**
     * @return int|null
     */
    public function getConfigurableProductParentId()
    {
        return $this->getCpid();
    }
}
