<?php

use Findologic_Search_Helper_XmlBasic as XmlBasic;
use Findologic_Search_Helper_XmlGroup as XmlGroup;

class Findologic_Search_Helper_Xml
{
    /*
     * string
     */
    private $shopKey;

    /**
     * Builds xml document.
     *
     * @param array $params
     * @param array $products
     * @return string Xml file
     */
    public function buildXmlDoc($params, $products)
    {
        $this->shopKey = $params['shopkey'];

        $findologic = new SimpleXMLElement('<findologic/>');
        $findologic->addAttribute('version', '1.0');
        $items = $findologic->addChild('items');
        $items->addAttribute('start', $params['start']);
        $items->addAttribute('count', $params['count']);
        $items->addAttribute('total', $params['total']);

        /* @var Findologic_Search_Helper_XmlBasic $xmlBasic */
        $xmlBasic = Mage::helper('findologic/xmlBasic');
        /** @var Findologic_Search_Helper_TrackingScripts $helper */
        $helper = Mage::helper('findologic/trackingScripts');

        /* @var $product Mage_Catalog_Model_Product */
        foreach ($products as $product) {
            $item = $items->addChild('item');
            $item->addAttribute('id', $product->getId());
            $xmlBasic->setData($params, $product);
            $this->renderItem($item, $product, $helper, $xmlBasic);
        }

        return $findologic->asXML();
    }

    /**
     * Renders one product.
     *
     * @param SimpleXMLElement $item
     * @param Mage_Catalog_Model_Product $product
     * @param Findologic_Search_Helper_TrackingScripts $helper
     * @param Findologic_Search_Helper_XmlBasic $xmlBasic
     */
    private function renderItem(SimpleXMLElement $item, Mage_Catalog_Model_Product $product, $helper, $xmlBasic)
    {
        $xmlBasic->renderOrderNumber($item->addChild('allOrdernumbers'), $product);

        $xmlBasic->renderName($item->addChild('names'), $product);

        $xmlBasic->renderSummary($item->addChild('summaries'), $product);

        $xmlBasic->renderDescription($item->addChild('descriptions'), $product);

        $xmlBasic->renderPrice($item->addChild('prices'), $product);

        $xmlBasic->renderUrl($item->addChild('urls'), $product);

        $xmlBasic->renderImages($item->addChild('allImages'), $product);

        $xmlBasic->renderAttributes($item->addChild('allAttributes'), $product);

        $xmlBasic->renderKeywords($item->addChild('allKeywords'), $product);

        // By default product visibility can be set only for different stores, not for user group, so we render all groups.
        $userGroups = $item->addChild('usergroups');
        $groups = Mage::getModel('customer/group')->getCollection();
        /** @var Mage_Customer_Model_Group $group */
        foreach($groups as $group) {
            if ($group->getId() > 0) {
                $this->appendCData($userGroups->addChild('usergroup'), $helper->userGroupToHash($this->shopKey, $group->getCustomerGroupCode()));
            }
        }

        $xmlBasic->renderSalesFrequency($item->addChild('salesFrequencies'), $product);

        $xmlBasic->renderDateAdded($item->addChild('dateAddeds'), $product);

        $xmlBasic->renderAllProperties($item->addChild('allProperties'), $product);
    }
    
    private function appendCData(SimpleXMLElement $node, $text)
    {
        $domNode = dom_import_simplexml($node);
        $domNode->appendChild($domNode->ownerDocument->createCDATASection($this->utf8Replace($text)));
    }

    private function utf8Replace($text)
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', trim($text));
    }
}
