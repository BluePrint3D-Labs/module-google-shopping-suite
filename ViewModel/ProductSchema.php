<?php
namespace BluePrint3D\GoogleShoppingSuite\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class ProductSchema implements ArgumentInterface
{
    protected $registry;
    protected $storeManager;

    public function __construct(
        Registry $registry,
        StoreManagerInterface $storeManager
    ) {
        $this->registry = $registry;
        $this->storeManager = $storeManager;
    }

    /**
     * Get the current product from the registry
     */
    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Generate the structured data array
     */
    public function getSchemaData()
    {
        $product = $this->getProduct();
        if (!$product) {
            return [];
        }

        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        $currency = $this->storeManager->getStore()->getCurrentCurrencyCode();

        // Calculate clean final price
        $price = number_format($product->getFinalPrice(), 2, '.', '');

        return [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => htmlspecialchars($product->getName()),
            'image' => [
                $mediaUrl . 'catalog/product' . $product->getImage()
            ],
            'description' => strip_tags($product->getShortDescription() ?? $product->getDescription() ?? ''),
            'sku' => $product->getSku(),
            'offers' => [
                '@type' => 'Offer',
                'url' => $product->getProductUrl(),
                'priceCurrency' => $currency,
                'price' => $price,
                'availability' => $product->isAvailable() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition'
            ]
        ];
    }
}
