<?php

class Findologic_Search_Model_Observer {
    
    public function onAfterGetSearchResult(Varien_Event_Observer $eventData)
    {
        // need to get proper number of items if possible
    }

    public function onCheckoutOnepageControllerSuccess(Varien_Event_Observer $event)
    {
        $data = $event->getData();
        Mage::helper('findologic/trackingScripts')->setLastOrderId(reset($data['order_ids']));
    }
}