<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 FINDOLOGIC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom
 * the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
 * OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
class FINDOLOGIC_OnSiteSearch_Model_ShopkeyValidation extends Mage_Core_Model_Config_Data
{
    /**
     * Saves object data.
     *
     * @return FINDOLOGIC_OnSiteSearch_Model_ShopkeyValidation
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
