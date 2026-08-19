<?php

/**
 * Frontend observers.
 */
class YSRTech_SimpleConfigurable_Model_Observer
{
    /**
     * Re-opens the parent configurable product when a customer edits a cart line that holds
     * one of its associated simple products.
     *
     * Simple Configurable adds the child simple product to the cart rather than the parent, and
     * those children are normally not visible individually - so core's checkout/cart/configure,
     * which renders the product on the line itself, would land the customer on a product page
     * they cannot use. This renders the parent instead, pre-filled from the line's buy request.
     *
     * This is deliberately an observer rather than a CartController override.
     * Mage_Core_Controller_Varien_Action::preDispatch() builds this event's name from the
     * ROUTE (getFullActionName()), not from the controller class, so it fires whichever module
     * ends up owning checkout/cart - core, or any extension that injected its own CartController
     * ahead of it (OneStepCheckout and similar are common). A controller override would instead
     * have to win a router ordering fight against those modules, and would silently lose it on
     * any store where one of them is installed.
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function configureCartItemWithParent(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Front_Action $action */
        $action = $observer->getEvent()->getControllerAction();

        if (!Mage::helper('ysrtech_simpleconfigurable')->isModuleActiveOnStore()) {
            return;
        }

        $id = (int) $action->getRequest()->getParam('id');
        if (!$id) {
            return;
        }

        $quoteItem = Mage::getSingleton('checkout/cart')->getQuote()->getItemById($id);
        if (!$quoteItem) {
            return;
        }

        $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')
            ->getParentIdsByChild($quoteItem->getProduct()->getId());
        if (empty($parentIds)) {
            // Not one of ours - let the core action render the line's own product.
            return;
        }

        // From here on we own the response, so core's configureAction must not also run.
        $action->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);

        try {
            $params = new Varien_Object();
            $params->setCategoryId(false);
            $params->setConfigureMode(true);
            $params->setBuyRequest($quoteItem->getBuyRequest());

            Mage::helper('catalog/product_view')->prepareAndRender(reset($parentIds), $action, $params);
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('checkout/session')
                ->addError(Mage::helper('checkout')->__('Cannot configure product.'));
            $action->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
        }
    }
}
