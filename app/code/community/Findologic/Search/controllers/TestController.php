<?php
class Findologic_Search_TestController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        $ids = $this->getRequest()->getParam('ids');
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId(MAGE::app()->getStore()->getId())
            ->addAttributeToFilter('entity_id', array('in' => explode(',', $ids)))
            ->addAttributeToSelect('*');
        $collection->getSelect()->order(new Zend_Db_Expr('FIELD(e.entity_id, ' .  $ids .')'));
        $layout = Mage::getSingleton('core/layout');
        $toolbar = $layout->createBlock('search/product_list_toolbar');

        /** @var Mage_Catalog_Block_Product_List $block */
        $block = $layout->createBlock('catalog/product_list');
        $block->setChild('toolbar', $toolbar);
        $block->setTemplate('catalog/product/list.phtml');
        $block->setCollection($collection);
        $toolbar->setCollection($collection);


        //render block object
        echo $block->renderView();

    }
}