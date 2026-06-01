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

        // FIX: Joins index tables to populate getFinalPrice() data properties inside loops cleanly
        $collection->addPriceData();

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

        // Parse the URL to extract just the host (e.g., "www.blueprint3d.co.uk")
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $domain = str_replace('www.', '', $host);

        // Define the Google Namespace URI
        $googleNamespace = 'http://base.google.com/ns/1.0';

        // 2. Initialize XML Root
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="' . $googleNamespace . '"/>');
        $channel = $xml->addChild('channel');

        // Standard RSS Channel Header tags (No namespace)
        $channel->addChild('title', $domain . ' Google Shopping Feed');
        $channel->addChild('link', $baseUrl);

        // 3. Loop products and map attributes
        foreach ($collection as $product) {
            $item = $channel->addChild('item');

            // 1. ALWAYS CALC PRICE FIRST (Before Magento reloads models for URLs)
            $price = number_format((float)$product->getFinalPrice(), 2, '.', '');
            $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();

            // 2. Safe string conversion for Google's explicit availability constraints
            $availabilityString = $product->isAvailable() ? 'in_stock' : 'out_of_stock';

            // 3. Standard RSS Tags required inside <item>
            $item->addChild('title', htmlspecialchars($product->getName()));
            $item->addChild('link', $product->getProductUrl());
            $item->addChild('description', htmlspecialchars($product->getShortDescription() ?? $product->getName()));

            // 4. Google-specific attributes with Namespace URI
            $item->addChild('g:id', $product->getId(), $googleNamespace);
            $item->addChild('g:price', $price . ' ' . $currency, $googleNamespace);
            $item->addChild('g:image_link', $mediaUrl . 'catalog/product' . $product->getImage(), $googleNamespace);
            $item->addChild('g:availability', $availabilityString, $googleNamespace);
            $item->addChild('g:condition', 'new', $googleNamespace);
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