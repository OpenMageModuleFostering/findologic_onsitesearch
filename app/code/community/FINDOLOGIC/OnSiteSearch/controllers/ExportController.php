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
use FINDOLOGIC_OnSiteSearch_Helper_Xml as Xml;

class FINDOLOGIC_OnSiteSearch_ExportController extends Mage_Core_Controller_Front_Action
{
    /**
     * @var string
     */
    private $shopKey;

    /**
     * @var int
     */
    private $storeId;

    /**
     * @var int
     */
    private $count;

    /**
     * @var int
     */
    private $start;

    /**
     * @var int
     */
    private $total;

    /**
     * Exports data using shop key.
     */
    public function indexAction()
    {
        $request = $this->getRequest();
        $this->shopKey = $request->getParam('shopkey', false);
        $this->start = $request->getParam('start', false);
        $this->count = $request->getParam('count', false);
        $this->storeId = $this->getStoreId($this->shopKey);

        $this->validateInput();

        $products = $this->getAllValidProducts();
        $xml = $this->createXml($products);

        if ($request->getParam('validate', false)) {
            $this->validateXml($xml);
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
        die;
    }

    /**
     * Validates whether all input parameters are supplied.
     *
     */
    private function validateInput()
    {
        $message = '';
        if (!$this->shopKey) {
            $message = 'Parameter "shopkey" is missing! ';
        }

        if (!$this->storeId) {
            $message .= 'Parameter "shopkey" is not configured for any store! ';
        }

        if ($this->start === false || $this->start < 0) {
            $message .= 'Parameter "start" is missing or less than 0! ';
        }

        if (!$this->count || $this->count < 01) {
            $message .= 'Parameter "count" is missing or less than 1!';
        }

        if ($message) {
            die($message);
        }
    }

    private function validateXml($xml)
    {
        // Enable user error handling
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $path = 'https://raw.githubusercontent.com/FINDOLOGIC/xml-export/master/src/main/resources/findologic.xsd';
        if (!$dom->schemaValidate($path)) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $return = "<br/>\n";
                switch ($error->level) {
                    case LIBXML_ERR_WARNING:
                        $return .= "<b>Warning $error->code</b>: ";
                        break;
                    case LIBXML_ERR_ERROR:
                        $return .= "<b>Error $error->code</b>: ";
                        break;
                    case LIBXML_ERR_FATAL:
                        $return .= "<b>Fatal Error $error->code</b>: ";
                        break;
                }

                $return .= trim($error->message);
                if ($error->file) {
                    $return .= " in <b>$error->file</b>";
                }

                echo $return . " on line <b>$error->line</b>\n";
            }

            die;
        }
    }

    /**
     * Get store id for specified shop key.
     * @param string $shopKey
     * @return boolean|integer store id if found; otherwise, false.
     */
    private function getStoreId($shopKey)
    {
        $stores = Mage::app()->getStores();
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $keyValue = Mage::getStoreConfig('findologic/findologic_group/shopkey', $storeId);

            if ($keyValue === $shopKey) {
                return $storeId;
            }
        }

        return false;
    }

    /**
     * Get all products that passed validation for current selected store.
     *
     * @return array $productsCollection
     */
    private function getAllValidProducts()
    {
        /* @var $productsCollection Mage_Catalog_Model_Resource_Product_Collection */
        $productsCollection = Mage::getResourceModel('catalog/product_collection');
        $productsCollection
            ->setStoreId($this->storeId)
            ->addStoreFilter($this->storeId)
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', array('in' => array(
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            )))
            ->joinField(
                'qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id = entity_id',
                'is_in_stock = 1',
                'left'
            );

        $productsCollection->getSelect()
            ->where("IF(e.type_id != '" . Mage_Catalog_Model_Product_Type::TYPE_SIMPLE . "', at_qty.qty = 0, at_qty.qty > 0)");

        // condition missing: An article only found in a hidden category should not be exported. Performance issue with this.

        $this->total = $productsCollection->getSize();

        $productsCollection->getSelect()
            ->limit($this->count, $this->start);

        return $productsCollection;
    }

    /**
     * Create XML file.
     * @param array $products
     */
    private function createXml($products)
    {
        $xml = new Xml();
        return $xml->buildXmlDoc(
            array(
                'storeId' => $this->storeId,
                'shopkey' => $this->shopKey,
                'count' => $this->count,
                'start' => $this->start,
                'total' => $this->total,
            ),
            $products
        );
    }
}
