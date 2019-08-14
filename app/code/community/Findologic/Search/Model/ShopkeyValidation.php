<?php

class Findologic_Search_Model_ShopkeyValidation extends Mage_Core_Model_Config_Data
{
    /**
     * Saves object data.
     *
     * @return Findologic_Search_Model_ShopkeyValidation
     */
    public function save()
    {
        $shopKey = trim($this->getValue());
        if ($shopKey) {
            $code = Mage::getSingleton('adminhtml/config_data')->getStore();
            $currentId = Mage::getModel('core/store')->load($code)->getId();

            $stores = Mage::app()->getStores();

            foreach ($stores as $store) {
                $storeId = $store->getId();
                $keyValue = Mage::getStoreConfig('findologic/findologic_group/shopkey', $storeId);

                if ($keyValue === $shopKey && $currentId !== $storeId) {
                    Mage::throwException('Shop key already exists! Each store view must have its own shop key.');
                }
            }

            $this->setValue(trim($shopKey));
        }

        return parent::save();
    }
}
