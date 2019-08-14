<?php

class Findologic_Search_Helper_XmlBasic extends Mage_Core_Helper_Abstract
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
    private $rootCategoryId;

    /**
     * @var Mage_Catalog_Model_Product
     */
    private $product;

    /**
     * @var array[Mage_Catalog_Model_Product]
     */
    private $variations;

    private static $customerGroups = array();
    private static $baseUrl;
    private static $mediaHelper;

    /**
     * Static function that initializes all static fields to enchance performance
     */
    private static function initStaticFields()
    {
        if (empty(self::$customerGroups)) {
            /* @var $collection Mage_Customer_Model_Resource_Group_Collection */
            $collection = Mage::getResourceModel('customer/group_collection');
            foreach ($collection as $group) {
                self::$customerGroups[$group->getId()] = $group->getCustomerGroupCode();
            }

            self::$baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            self::$mediaHelper = Mage::getModel('catalog/product_media_config');
        }
    }

    /**
     * Sets data needed for all the methods. This method must be called before all other methods.
     *
     * @param array $params
     * @param Mage_Catalog_Model_Product $product
     * @return Findologic_Search_Helper_XmlBasic
     */
    public function setData($params, Mage_Catalog_Model_Product $product)
    {
        self::initStaticFields();
        $this->shopKey = $params['shopkey'];
        $this->storeId = $params['storeId'];
        $this->rootCategoryId = Mage::app()->getStore($this->storeId)->getRootCategoryId();
        $this->product = $product;
        $this->variations = array();
        if ($product && $product->isConfigurable()) {
            $variations = $product->getTypeInstance(true)->getUsedProducts(null, $product);
            $variationIds = array();
            foreach ($variations as $variation) {
                if ($variation->isAvailable()) {
                    $variationIds[] = $variation->getId();
                }
            }

            $collection = Mage::getResourceModel('catalog/product_collection')
                ->setStoreId($params['storeId'])
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', array('in' => $variationIds));

            foreach ($collection as $variation) {
                $this->variations[] = $variation;
            }
        }

        return $this;
    }

    /**
     * Renders order numbers.
     *
     * @param SimpleXMLElement $allOrderNumbers
     */
    public function renderOrderNumber(SimpleXMLElement $allOrderNumbers)
    {
        $orderNumbers = $allOrderNumbers->addChild('ordernumbers');
        $this->appendCData($orderNumbers->addChild('ordernumber'), $this->product->getSku());

        /* @var $variation Mage_Catalog_Model_Product */
        foreach ($this->variations as $variation) {
            if ($variation->isAvailable()) {
                $this->appendCData($orderNumbers->addChild('ordernumber'), $variation->getSku());
            }
        }
    }

    /**
     * Renders name.
     *
     * @param SimpleXMLElement $names
     */
    public function renderName(SimpleXMLElement $names)
    {
        $this->appendCData($names->addChild('name'), $this->product->getName());
    }

    /**
     * Renders summary (short description).
     *
     * @param SimpleXMLElement $summaries
     */
    public function renderSummary(SimpleXMLElement $summaries)
    {
        $this->appendCData($summaries->addChild('summary'), $this->product->getShortDescription());
    }

    /**
     * Renders long description.
     *
     * @param SimpleXMLElement $descriptions
     */
    public function renderDescription(SimpleXMLElement $descriptions)
    {
        $this->appendCData($descriptions->addChild('description'), $this->product->getDescription());
    }

    /**
     * Renders price.
     *
     * @param SimpleXMLElement $prices
     */
    public function renderPrice(SimpleXMLElement $prices)
    {
        $specialPrice = $this->getSpecialPrice();
        $price = $specialPrice > 0 ? $specialPrice : $this->product->getPrice();

        $groupPrices = $this->getGroupPrices($this->product);
        /** @var Mage_Catalog_Model_Product $variation */
        foreach ($this->variations as $variation) {
            $price = min($price, ($variation->getSpecialPrice() ?: $variation->getPrice()));
            $groupPrices = array_merge_recursive($groupPrices, $this->getGroupPrices($variation));
        }

        $this->appendCData($prices->addChild('price'), sprintf('%.2f', $price));

        foreach ($groupPrices as $code => $priceArray) {
            $priceNode = $prices->addChild('price');
            $this->appendCData($priceNode, sprintf('%.2f', min($priceArray)));
            $priceNode->addAttribute('usergroup', $code);
        }
    }

    private function getGroupPrices(Mage_Catalog_Model_Product $product)
    {
        /** @var Findologic_Search_Helper_TrackingScripts $helper */
        $helper = Mage::helper('findologic/trackingScripts');

        $attribute = $product->getResource()->getAttribute('group_price');
        $attribute->getBackend()->afterLoad($this->product);
        $groupPrices = $product->getData('group_price');

        $result = array();
        if ($groupPrices) {
            foreach ($groupPrices as $group) {
                $userGroup = self::$customerGroups[$group['cust_group']];
                $result[$helper->userGroupToHash($this->shopKey, $userGroup)] = array($group['price']);
            }
        }

        return $result;
    }

    /**
     * Render URL.
     *
     * @param SimpleXMLElement $urls
     */
    public function renderUrl(SimpleXMLElement $urls)
    {
        $this->appendCData($urls->addChild('url'), $this->product->getProductUrl());
    }

    /**
     * Renders all images.
     *
     * @param SimpleXMLElement $allImages
     */
    public function renderImages(SimpleXMLElement $allImages)
    {
        $productId = $this->product->getId();
        $productImageModel = Mage::getModel('findologic/ProductImage');

        $productImages = $productImageModel->getProductImagesByProductId($productId);
        $baseImageUrl = $productImageModel->getBaseImageUrlByProductId($productId);

        $images = $allImages->addChild('images');

        if ($baseImageUrl != '') {
            $this->appendCData($images->addChild('image'), $baseImageUrl);
        }

        if (count($productImages) > 0) {
            foreach ($productImages as $productImage) {
                if (self::$mediaHelper->getMediaUrl($productImage['value']) != $baseImageUrl) {
                    $this->appendCData($images->addChild('image'), self::$mediaHelper->getMediaUrl($productImage['value']));
                }
            }
        } else {
            $image = $this->product->getImage();
            if ($image && $image != 'no_selection') {
                $this->appendCData($images->addChild('image'), Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $this->product->getImage());
            } else {
                $this->appendCData($images->addChild('image'), Mage::getSingleton('catalog/product_media_config')->getBaseMediaUrl()
                    . '/placeholder/' . Mage::getStoreConfig('catalog/placeholder/small_image_placeholder'));
            }
        }
    }

    /**
     * Renders all attributes (categories, categories url).
     *
     * @param SimpleXMLElement $allAttributes
     */
    public function renderAttributes(SimpleXMLElement $allAttributes)
    {
        $attributesNode = $allAttributes->addChild('attributes');
        $this->renderCategories($attributesNode);

        $this->addAttributes($attributesNode);

        $this->removeEmptyNode($attributesNode, $attributesNode);
    }

    /**
     * Renders categories and category urls as attributes.
     *
     * @param SimpleXMLElement $attributesNode
     * @param array [Mage_Catalog_Model_Category] $catCollection
     */
    private function renderCategories($attributesNode)
    {
        $catCollection = $this->getCategories();

        if (count($catCollection) === 0) {
            return;
        }

        $catNode = $attributesNode->addChild('attribute');

        $this->appendCData($catNode->addChild('key'), 'cat');
        $valuesNode = $catNode->addChild('values');

        $catUrlNode = $attributesNode->addChild('attribute');
        $this->appendCData($catUrlNode->addChild('key'), 'cat_url');
        $valuesCatUrl = $catUrlNode->addChild('values');

        /** @var Findologic_Search_Helper_TrackingScripts $helper */
        $helper = Mage::helper('findologic/trackingScripts');

        /** @var Mage_Catalog_Model_Category $cat */
        foreach ($catCollection as $cat) {
            $cat = $helper->getCategory($cat->getId());

            if ($cat->getIsActive()) {
                $catPath = $helper->getCategoryPath($cat->getPath(), '_', false, false);
                if ($catPath) {
                    $this->appendCData($valuesNode->addChild('value'), $catPath);
                    $cat_url = parse_url($cat->getUrl());
                    $valuesCatUrl->addChild('value', $cat_url['path']);
                }
            }
        }

        $this->removeEmptyNode($valuesNode, $catNode);
        $this->removeEmptyNode($valuesCatUrl, $catUrlNode);
    }

    /**
     * Filter categories by rootCategoryId
     *
     * @return array $collection
     */
    private function getCategories()
    {
        $collection = Mage::getResourceModel('catalog/category_collection')
            ->joinField('product_id',
                'catalog/category_product',
                'product_id',
                'category_id = entity_id',
                null)
            ->addFieldToFilter('product_id', (int)$this->product->getId())
            ->addFieldToFilter('path', array('like'=> "1/$this->rootCategoryId/%"));
        return $collection;
    }

    /**
     * Adds all configurable attributes as SimpleXMLElement array.
     */
    private function addAttributes(SimpleXMLElement $attributesNode)
    {
        /* @var Mage_Eav_Model_Entity_Attribute $attribute */
        /* @var $variation Mage_Catalog_Model_Product */
        $result = array();
        $this->addAttributesForProduct($result, $this->product);

        foreach ($this->variations as $variation) {
            $this->addAttributesForProduct($result, $variation);
        }

        if ($this->variations) {
            $productAttributes = $this->product->getTypeInstance()->getConfigurableAttributes();
            foreach ($productAttributes as $attribute) {
                $attributeCode = $attribute->getProductAttribute()->getAttributeCode();
                $values = array();
                foreach ($this->variations as $variation) {
                    if ($variation->isAvailable()) {
                        $attributeValue = trim($variation->getAttributeText($attributeCode));
                        if ($attributeValue && array_search($attributeValue, $values) === false) {
                            $values[] = $attributeValue;
                        }
                    }
                }

                if (count($values)) {
                    $result[$attributeCode] = $values;
                }
            }
        }

        foreach ($result as $attrKey => $attrValues) {
            if (!$attrValues) {
                continue;
            }

            $attributeNode = $attributesNode->addChild('attribute');
            $this->appendCData($attributeNode->addChild('key'), $attrKey);
            $valuesNode = $attributeNode->addChild('values');
            foreach ($attrValues as $value) {
                $this->appendCData($valuesNode->addChild('value'), $value);
            }
        }
    }

    /**
     * @param array $result
     * @param Mage_Catalog_Model_Product $product
     */
    private function addAttributesForProduct(&$result, $product)
    {
        /* @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attributes = $product->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getIsFilterable() || $attribute->getIsFilterableInSearch()) {
                $code = $attribute->getAttributeCode();
                $value = trim($product->getAttributeText($code));
                if (!array_key_exists($code, $result)) {
                    $result[$code] = array();
                }

                if ($value && array_search($value, $result[$code]) === false) {
                    $result[$code][] = $value;
                }
            }
        }
    }

    /**
     * Renders all keywords (meta keywords).
     *
     * @param SimpleXMLElement $allKeywords
     */
    public function renderKeywords(SimpleXMLElement $allKeywords)
    {
        $metaKeywords = $this->product->getMetaKeyword();
        if ($metaKeywords) {
            $metaKeywords = explode(',', $metaKeywords);
            $keywords = $allKeywords->addChild('keywords');

            if (count($metaKeywords)) {
                foreach ($metaKeywords as $keyword) {
                    $keyword = trim($keyword);
                    if ($keyword !== '') {
                        $this->appendCData($keywords->addChild('keyword'), $keyword);
                    }
                }
            } else {
                $keywords->addChild('keyword');
            }
        }
    }

    /**
     * Renders date added.
     *
     * @param SimpleXMLElement $dateAdded
     */
    public function renderDateAdded(SimpleXMLElement $dateAdded)
    {
        $format = 'Y-m-d H:i:s';
        /* @var $date DateTime */
        $date = DateTime::createFromFormat($format, $this->product->getCreatedAt());

        $this->appendCData($dateAdded->addChild('dateAdded'), $date->format(DATE_ATOM));
    }

    /**
     * Render sales frequencies.
     *
     * @param SimpleXMLElement $salesFrequencies
     */
    public function renderSalesFrequency(SimpleXMLElement $salesFrequencies)
    {
        $prefix = Mage::getConfig()->getTablePrefix();

        // Get all orders filter by product_id
        $groupOrders = Mage::getResourceModel('sales/order_item_collection')
            ->addAttributeToFilter('product_id', (int)$this->product->getId());

        // get only orders from customers belonging to a group
        $groupOrders->getSelect()
            ->joinLeft(
                array('sfo' => $prefix . 'sales_flat_order'),
                'order_id=' . 'sfo.entity_id',
                array('sfo.customer_group_id', 'sfo.increment_id')
            )
            ->join(
                array('cg' => $prefix . 'customer_group'),
                'sfo.customer_group_id=' . 'cg.customer_group_id',
                array('cg.customer_group_code')
            )
            ->where('sfo.customer_group_id > 0');

        $groupOrders = $groupOrders->load();

        $salesFrequenciesCountPerGroup = array();
        $userGroupArr = array();
        $salesFrequenciesCount = 0;

        /** @var Findologic_Search_Helper_TrackingScripts $helper */
        $helper = Mage::helper('findologic/trackingScripts');

        /* @var $order Mage_Sales_Model_Order */
        foreach ($groupOrders as $order) {
            $userGroup = $order->getCustomerGroupCode();
            $userGroupHash = $helper->userGroupToHash($this->shopKey, $userGroup);

            $num = array_search($userGroupHash, $userGroupArr);
            if ($num !== false) {
                $salesFrequenciesCountPerGroup[$userGroupHash] += 1;
            } else {
                $userGroupArr[] = $userGroupHash;
                $salesFrequenciesCountPerGroup[$userGroupHash] = 1;
            }

            $salesFrequenciesCount++;
        }

        $orders = Mage::getResourceModel('sales/order_item_collection')
            ->addAttributeToFilter('product_id', (int)$this->product->getId());

        // Condition: custom group "NOT LOGGED IN" and Group doesn't exists anymore (if group is deleted).
        $orders->getSelect()
            ->join(
                array('sfo' => $prefix . 'sales_flat_order'), 'order_id=' . 'sfo.entity_id', array('sfo.customer_group_id', 'sfo.increment_id')
            )
            ->joinLeft(
                array('cg' => $prefix . 'customer_group'), 'sfo.customer_group_id=' . 'cg.customer_group_id', array('cg.customer_group_code')
            )
            ->where('cg.customer_group_code is null OR sfo.customer_group_id = 0');

        $salesFrequenciesCount += $orders->getSize();

        if ($salesFrequenciesCount > 0) {
            $this->appendCData($salesFrequencies->addChild('salesFrequency'), $salesFrequenciesCount);
        }

        if (count($salesFrequenciesCountPerGroup) > 0) {
            foreach ($salesFrequenciesCountPerGroup as $key => $value) {
                $salesNode = $salesFrequencies->addChild('salesFrequency');
                $this->appendCData($salesNode, $value);
                $salesNode->addAttribute('usergroup', $key);
            }
        }
    }

    /**
     * Renders all properties.
     *
     * @param SimpleXMLElement $allProperties
     */
    public function renderAllProperties(SimpleXMLElement $allProperties)
    {
        $properties = $allProperties->addChild('properties');

        $quantity = (int)$this->product->getStockItem()->getQty() + (int)$this->product->getQty();
        foreach ($this->variations as $variation) {
            $quantity += (int)$variation->getStockItem()->getQty() + (int)$variation->getQty();
        }

        $productProperties = array(
            'base price' => sprintf('%.2f', $this->product->getPrice()),
            'special price' => sprintf('%.2f', $this->getSpecialPrice()),
            'quantity' => $quantity,
        );

        foreach ($productProperties as $key => $propertyValue) {
            $property = $properties->addChild('property');
            $this->appendCData($property->addChild('key'), $key);
            $this->appendCData($property->addChild('value'), $propertyValue);
        }
    }

    /**
     * @return float
     */
    private function getSpecialPrice()
    {
        $curDate = strtotime(date('Y-m-d'));
        $dateTo = strtotime($this->product->getSpecialToDate());
        $dateFrom = strtotime($this->product->getSpecialFromDate());
        $specialPrice = $this->product->getPrice();
        $priceRule = Mage::getModel('catalogrule/rule')->calcProductPriceRule($this->product, $this->product->getPrice())?Mage::getModel('catalogrule/rule')->calcProductPriceRule($this->product, $this->product->getPrice()):$this->product->getPrice();

        if ($curDate <= $dateTo && $curDate >= $dateFrom) {
            $specialPrice = $this->product->getSpecialPrice();
        }

        if ($specialPrice < $priceRule) {
            return $specialPrice;
        }

        return $priceRule < $this->product->getPrice() ? $priceRule : 0;
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

    private function removeEmptyNode($node, $relatedNode)
    {
        if (!$node->children()->asXML()) {
            $dom = dom_import_simplexml($relatedNode);
            $dom->parentNode->removeChild($dom);
        }
    }
}
