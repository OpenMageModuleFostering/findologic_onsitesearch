<?php

class Findologic_Search_Helper_TrackingScripts extends Mage_Core_Helper_Abstract
{
    /**
     * @var array
     */
    private $data;

    /**
     * Used for caching categories retrieved from database.
     * @var array
     */
    private static $categories = array();

    /**
     * Renders needed scripts for current page.
     *
     * @param Mage_Core_Controller_Request_Http $request
     *
     * @return string
     */
    public function renderScripts(Mage_Core_Controller_Request_Http $request)
    {
        $findologic = $request->get('findologic');
        $result = '';

        if ($findologic != 'off' && $this->getShopKey()) {
            // Inserting js files for every page
            $result .= $this->headJs();
        }

        return $result;
    }

    /**
     * Returns tracking js script for head (all pages).
     *
     * @return string
     */
    private function headJs()
    {
        $headJs = sprintf(
            '<script type=\'text/javascript\'>
            (function() {
                var mainUrl = \'https://cdn.findologic.com/static/%s/main.js?usergrouphash=%s\';
                var loader = document.createElement(\'script\');
                loader.type = \'text/javascript\';
                loader.async = true;
                loader.src = \'https://cdn.findologic.com/static/loader.min.js\';
                var s = document.getElementsByTagName(\'script\')[0];
                loader.setAttribute(\'data-fl-main\', mainUrl);
                s.parentNode.insertBefore(loader, s);
            })();
            </script>', $this->data['PLACEHOLDER_1'], $this->data['PLACEHOLDER_2']);

        return $headJs;
    }

    /**
     * @return string Reads plugin configuration and returns shop key for current shop.
     */
    private function getShopKey()
    {
        return Mage::getStoreConfig('findologic/findologic_group/shopkey', (int) Mage::app()->getStore()->getId());
    }

    /**
     * Gets category path as string separated by provided separator.
     * ** Helper function used in other helpers. **
     *
     * @param string $categoryPathIds Identifiers separated by slash. Example: 154/6584/653/8
     * @param string $separator Separator for generated path between category names
     * @param bool $wrap Indicates whether to wrap each category name with quotes

     * @return string
     */
    public function getCategoryPath($categoryPathIds, $separator = ', ', $wrap = true, $addSlashes = true)
    {
        $holder = array();
        $categoryIds = explode('/', $categoryPathIds);
        $n = 0;
        foreach ($categoryIds as $catId) {
            // skip 'root' category and add up to 5 categories.
            if (($n > 1 && $n <= 6)) {
                $category = $this->getCategory($catId);
                $categoryName = $addSlashes ? addslashes($category->getName()) : $category->getName();
                $holder[] = $wrap ? '"' . $categoryName . '"' : $categoryName;
            }

            $n++;
        }

        return implode($separator, $holder);
    }

    /**
     * Gets category from local cache.
     *
     * @param int $id Category identifier
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory($id)
    {
        if (!array_key_exists($id, self::$categories)) {
            self::$categories[$id] = Mage::getResourceModel('catalog/category_collection')
                            ->addAttributeToSelect(array('is_active', 'name'))
                            ->addFieldToFilter('entity_id', $id)
                            ->getFirstItem();
        }

        return self::$categories[$id];
    }

    /**
     * Returns hash key merged from shop key and user group.
     * ** Helper function used in other helpers. **
     *
     * @param string $shopKey
     * @param string $userGroup
     *
     * @return string
     */
    public function userGroupToHash($shopKey, $userGroup)
    {
        return base64_encode($shopKey ^ $userGroup);
    }
}
