<?php

/**
 * Simple Configurable rewrite: route the ajax gallery/image popup to this module's
 * own ajax controller (which knows how to resolve the configurable parent + selected
 * child) instead of the default catalog/product/gallery route.
 */
class Ysrtech_SimpleConfigurable_Catalog_Block_Product_View_Media extends Mage_Catalog_Block_Product_View_Media
{
    /**
     * @param Mage_Catalog_Model_Product_Attribute_Media_Gallery_Entry|Varien_Object|null $image
     * @return string
     */
    public function getGalleryUrl($image = null)
    {
        $params = [
            'id'  => $this->getProduct()->getId(),
            'pid' => $this->getProduct()->getCpid(),
        ];

        if ($image) {
            $params['image'] = $image->getValueId();
        }

        return $this->getUrl('*/*/gallery', $params);
    }
}
