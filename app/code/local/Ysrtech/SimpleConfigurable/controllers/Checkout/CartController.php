<?php

require_once 'Mage/Checkout/controllers/CartController.php';

/**
 * Rewrites cart "configure" action so that editing a cart item that belongs to
 * a Simple Configurable child product re-opens the parent configurable product's
 * option selector instead of the (non-visible) simple product page.
 */
class Ysrtech_SimpleConfigurable_Checkout_CartController extends Mage_Checkout_CartController
{
    public function configureAction()
    {
        $id = (int) $this->getRequest()->getParam('id');
        $quoteItem = null;

        if ($id) {
            $quoteItem = $this->_getCart()->getQuote()->getItemById($id);
        }

        if (!$quoteItem) {
            $this->_redirect('checkout/cart');
            return;
        }

        try {
            $params = new Varien_Object();
            $params->setCategoryId(false);
            $params->setConfigureMode(true);
            $params->setBuyRequest($quoteItem->getBuyRequest());

            $productId = $quoteItem->getProduct()->getId();
            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')
                ->getParentIdsByChild($productId);
            if (!empty($parentIds)) {
                $productId = $parentIds[0];
            }

            Mage::helper('catalog/product_view')->prepareAndRender($productId, $this, $params);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_redirect('checkout/cart');
        }
    }
}
