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
class FINDOLOGIC_OnSiteSearch_Helper_TrackingScripts extends Mage_Core_Helper_Abstract
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
            $result .= $this->addHeadJs();

            $module = $request->getModuleName();
            $controller = $request->getControllerName();
            $action = $request->getActionName();

            if (Mage::registry('current_product')) {
                $result .= $this->productJs($request);
            } else if ($module == 'checkout' && $controller == 'onepage' && $action == 'success') {
                $result .= $this->checkoutJs($request);
            } else if ($module == 'checkout' && $controller == 'cart' && $action == 'index') {
                $result .= $this->cartJs();
            //} else if (Mage::registry('current_category')) {
            //    $result .= $this->categoryJs();
            }
        }

        return $result;
    }

    /**
     * Sets last order ID used in tracking of orders. Called for event observer. @see FINDOLOGIC_OnSiteSearch_Model_Observer
     *
     * @param int $orderId
     */
    public function setLastOrderId($orderId)
    {
        $this->data['LAST_ORDER_ID'] = $orderId;
    }

    /**
     * Returns tracking js script for head (all pages).
     *
     * @return string
     */
    private function headJs()
    {
        $this->getConfData();

        $headJs = sprintf(
            '<script type="text/javascript">
            (function() {
                var flDataMain = "https://cdn.findologic.com/autocomplete/%s/autocomplete.js?usergrouphash=%s";
                var flAutocomplete = document.createElement(\'script\'); 
                flAutocomplete.type = \'text/javascript\'; 
                flAutocomplete.async = true;
                flAutocomplete.src = "https://cdn.findologic.com/autocomplete/require.js";
                var s = document.getElementsByTagName(\'script\')[0];
                flAutocomplete.setAttribute(\'data-main\', flDataMain);
                s.parentNode.insertBefore(flAutocomplete, s);
            })();
            </script>', $this->data['PLACEHOLDER_1'], $this->data['PLACEHOLDER_2']);

        return $headJs;
    }

    /**
     * Returns additional tracking js script for head (all pages).
     *
     * @return string
     */
    private function addHeadJs()
    {
        $addHeadJs = sprintf(
            '<script type="text/javascript">
                var _paq = _paq || [];
                (function(){ var u=(("https:" == document.location.protocol) ? "https://tracking.findologic.com/" : "http://tracking.findologic.com/");
                _paq.push([\'setSiteId\', \'%s\']);
                _paq.push([\'setTrackerUrl\', u+\'tracking.php\']);
                _paq.push([\'trackPageView\']);
                _paq.push([\'enableLinkTracking\']);
                var d=document, g=d.createElement(\'script\'), s=d.getElementsByTagName(\'script\')[0]; g.type=\'text/javascript\'; g.defer=true; g.async=true; g.src=u+\'tracking.js\';
                s.parentNode.insertBefore(g,s); })();
            </script>', $this->data['HASHED_SHOPKEY']
        );

        return $addHeadJs;
    }

    /**
     * Returns tracking js script for category page.
     *
     * @return string
     */
    private function categoryJs()
    {
        $currentCategory = Mage::getModel('catalog/layer')->getCurrentCategory();
        $categoryJs = sprintf(
             '<script type="text/javascript">
                _paq.push([\'setEcommerceView\',
                    productSku = false, 
                    productName = false, 
                    category = [%s]
                ]);
                _paq.push([\'trackPageView\']);
            </script>', $this->getCategoryPath($currentCategory->getPath())
        );

        return $categoryJs;
    }

    /**
     * Returns tracking js script for checkout page.
     *
     * @return string
     */
    private function checkoutJs()
    {
        $lastOrderId = $this->data['LAST_ORDER_ID'];
        /* @var $order Mage_Sales_Model_Order */
        $order = Mage::getModel('sales/order')->load($lastOrderId);
        if (!$order->getId()) {
            return '';
        }

        $items = $order->getItemsCollection();
        $checkoutJs = '<script type="text/javascript">';
        foreach ($items as $item) {
            $checkoutJs .= $this->getItemScript($item);
        }

        $checkoutJs .= sprintf(
                '
                _paq.push([\'trackEcommerceOrder\', "%s", %.2f, %.2f, %.2f, %.2f, %.2f ]);
                _paq.push([\'trackPageView\']);
            </script>', $order->getId(), $order->getGrandTotal(), $order->getSubtotal(), $order->getTaxAmount(), $order->getShippingAmount(), $order->getDiscountAmount()
        );

        return $checkoutJs;
    }

    /**
     * Returns tracking js script for cart page.
     *
     * @return string
     */
    private function cartJs()
    {
        /* @var $cart Mage_Checkout_Helper_Cart */
        $cartHelper = Mage::helper('checkout/cart');
        /* @var $cart Mage_Checkout_Model_Cart  */
        $cart = $cartHelper->getCart();
        $items = $cart->getItems();
        $cartJs = '<script type="text/javascript">';
        foreach ($items as $item) {
            if ($item->getSpecialPrice() > 0 || $item->getPrice() > 0) {
                $cartJs .= $this->getItemScript($item);
            }
        }

        $cartJs .= sprintf('
                _paq.push([\'trackEcommerceCartUpdate\', %.2f]);
                _paq.push([\'trackPageView\']);
            </script>',
            $cart->getQuote()->getGrandTotal()
        );

        return $cartJs;
    }

    /**
     * Returns tracking js script for product page.
     *
     * @return string
     */
    private function productJs($request)
    {
        $product_id = (int) $request->getParam('id');
        $this->setProductData($product_id);

        $productJs = sprintf(
            '<script type="text/javascript">
                _paq.push([\'setEcommerceView\',
                    "%s",
                    "%s",
                    [%s],
                    %s
                ]);
                _paq.push([\'trackPageView\']);
            </script>', $this->data['PRODUCT_ORDERNUMBER'], addslashes($this->data['PRODUCT_TITLE']), $this->data['PRODUCT_CATEGORY_X'], $this->data['PRODUCT_PRICE']
        );

        return $productJs;
    }

    private function getItemScript($item)
    {
        return sprintf('
                _paq.push([\'addEcommerceItem\',
                    "%s", 
                    "%s", 
                    [%s],
                    %.2f, 
                    %d 
                ]);', 
                $item->getSku(),
                addslashes($item->getName()),
                $this->getCategoryPath($this->getFirstCategoryPath($item->getProduct()->getCategoryCollection())),
                $item->getSpecialPrice() ?: $item->getPrice(),
                $item->getQtyOrdered() ?: $item->getQty()
            );
    }

    /**
     * Get configuration data to start tracking
     */
    private function getConfData()
    {
        /** @var Mage_Customer_Model_Customer $customer */
        /** @var Mage_Customer_Model_Session $session */
        $session = Mage::getSingleton('customer/session');
        $customer = $session->getCustomer();
        $userGroup = Mage::getModel('customer/group')->load((int)$customer->getGroupId())->getCustomerGroupCode();
        $shopKey = $this->getShopKey();
        $userGroupHash = $this->userGroupToHash($shopKey, $userGroup);

        $this->data['PLACEHOLDER_1'] = strtoupper(md5($shopKey));
        $this->data['PLACEHOLDER_2'] = $session->isLoggedIn() ? $userGroupHash : '';
        $this->data['HASHED_SHOPKEY'] = strtoupper(md5($shopKey));
    }

    /**
     * @return string Reads plugin configuration and returns shop key for current shop.
     */
    private function getShopKey()
    {
        return Mage::getStoreConfig('findologic/findologic_group/shopkey', (int) Mage::app()->getStore()->getId());
    }

    /**
     * Sets data for product tracking.
     *
     * @param integer $product_id
     */
    private function setProductData($product_id)
    {
        /* @var $product Mage_Catalog_Model_Product */
        $product = Mage::getModel('catalog/product')->load($product_id);

        $this->data['PRODUCT_ORDERNUMBER'] = $product->getSku();
        $this->data['PRODUCT_TITLE'] = $product->getName();
        $this->data['PRODUCT_CATEGORY_X'] = $this->getFirstCategoryPath($product->getCategoryCollection());
        $this->data['PRODUCT_PRICE'] = sprintf('%.2f', $product->getSpecialPrice() ?: $product->getPrice());
    }

    /**
     * Gets path of first active category from supplied array of categories.
     *
     * @param array $categories
     * @return string Path for category
     */
    private function getFirstCategoryPath($categories)
    {
        foreach ($categories as $cat) {
            $cat = $this->getCategory($cat->getId());
            if ($cat->getIsActive()) {
                return $this->getCategoryPath($cat->getPath());
            }
        }

        return '';
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
