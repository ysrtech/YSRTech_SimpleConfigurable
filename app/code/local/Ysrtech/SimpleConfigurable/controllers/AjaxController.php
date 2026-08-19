<?php

require_once 'Mage/Catalog/controllers/ProductController.php';

/**
 * Ajax controller used to render swapped-in product data (options, image gallery)
 * when a shopper changes the selected configuration on a Simple Configurable product.
 */
class Ysrtech_SimpleConfigurable_AjaxController extends Mage_Catalog_ProductController
{
    /**
     * Ajax action returning rendered custom-options HTML for the selected child product
     */
    public function coAction()
    {
        if (!$this->_initProduct()) {
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Ajax action returning rendered gallery/image HTML for the selected child product
     */
    public function imageAction()
    {
        if (!$this->_initProduct()) {
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Ajax action returning rendered image gallery popup HTML for the selected child product
     */
    public function galleryAction()
    {
        if (!$this->_initProduct()) {
            return;
        }
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Resolve and register the child product (and its configurable parent) requested via ajax
     *
     * @return bool
     */
    protected function _initProduct()
    {
        $categoryId = (int) $this->getRequest()->getParam('category', false);
        $productId  = (int) $this->getRequest()->getParam('id');
        $parentId   = (int) $this->getRequest()->getParam('pid');

        if (!$parentId || !$productId) {
            return false;
        }

        $parent = Mage::getModel('catalog/product')->load($parentId);
        if (!$parent->getId() || !Mage::helper('catalog/product')->canShow($parent)) {
            return false;
        }

        $childIds = $parent->getTypeInstance()->getUsedProductIds();
        if (!in_array($productId, $childIds)) {
            return false;
        }

        $product = Mage::getModel('catalog/product')->load($productId);
        if (!$product->getId()) {
            return false;
        }

        if ($categoryId) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            Mage::register('current_category', $category);
        }

        Mage::register('current_product', $product);
        Mage::register('product', $product);

        $product->setCpid($parentId);

        return true;
    }
}
