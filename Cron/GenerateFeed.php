<?php
namespace BluePrint3D\GoogleShoppingSuite\Cron;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;

class GenerateFeed
{
    protected $productCollectionFactory;
    protected $filesystem;
    protected $storeManager;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        // 1. Get visible, enabled products
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'price', 'short_description', 'image', 'url_key'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [Visibility::VISIBILITY_BOTH, Visibility::VISIBILITY_IN_CATALOG]]);

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

        // 2. Initialize XML Writer
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(); // e.g., "https://www.example.co.uk/"

        // Parse the URL to extract just the host (e.g., "www.example.co.uk")
        $host = parse_url($baseUrl, PHP_URL_HOST);

        // Clean up "www." if it exists so you get a clean domain name
        $domain = str_replace('www.', '', $host); // Results in "example.co.uk"

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"/>');
        $channel = $xml->addChild('channel');

        // Set the dynamic title using the domain variable
        $channel->addChild('title', $domain . ' Google Shopping Feed');
        $channel->addChild('link', $baseUrl);

        // 3. Loop products and map attributes
        foreach ($collection as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', $product->getId());
            $item->addChild('g:title', htmlspecialchars($product->getName()));
            $item->addChild('g:link', $product->getProductUrl());

            // Critical part: Getting the final price dynamically
            $price = number_format($product->getFinalPrice(), 2, '.', '');
            $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();
            $item->addChild('g:price', $price . ' ' . $currency); // Outputs e.g., "14.99 GBP"

            $item->addChild('g:image_link', $mediaUrl . 'catalog/product' . $product->getImage());
            $item->addChild('g:availability', $product->isAvailable() ? 'in_stock' : 'out_of_stock');
            $item->addChild('g:condition', 'new');
        }

        // 4. Save file to pub/media/feeds/google.xml
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $filePath = 'feeds/google.xml';

        // Ensure directory exists
        $mediaDirectory->create('feeds');

        // Write the XML contents
        $mediaDirectory->writeFile($filePath, $xml->asXML());
    }
}
