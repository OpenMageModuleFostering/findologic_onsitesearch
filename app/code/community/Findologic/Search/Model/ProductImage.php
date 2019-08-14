<?php

class Findologic_Search_Model_ProductImage
{

    /**
     * Get all images from Media Gallery for the given product id.
     *
     * @param int $productId
     * @return mixed
     */
    public function getProductImagesByProductId($productId)
    {
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');

        $mediaGallery = $resource->getTableName('catalog/product') . '_media_gallery';
        $mediaGalleryValue = $mediaGallery . '_value';

        $query = "SELECT g.value FROM $mediaGallery g
                    LEFT JOIN $mediaGalleryValue AS v ON v.value_id = g.value_id
                    WHERE g.entity_id = (:productId) AND v.disabled = 0 ORDER BY v.position";

        $results = $readConnection->query($query, ['productId' => $productId]);

        return $results;
    }

    /**
     * If base image does exist for the particular product id, this method returns image url.
     * If base image does not exist for the particular product id, this method returns an empty string.
     *
     * @param int $productId
     * @return string
     */
    public function getBaseImageUrlByProductId($productId)
    {
        $product =  Mage::getModel('catalog/product')->load($productId);
        $productMediaConfig = Mage::getModel('catalog/product_media_config');
        $baseImageUrl = '';

        if ($product->getImage() != 'no_selection') {
            $baseImageUrl = $productMediaConfig->getMediaUrl($product->getImage());
        }

        return $baseImageUrl;
    }


}
