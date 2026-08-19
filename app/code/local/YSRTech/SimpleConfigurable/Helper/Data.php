<?php

class YSRTech_SimpleConfigurable_Helper_Data extends Mage_Core_Helper_Abstract
{
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
}
