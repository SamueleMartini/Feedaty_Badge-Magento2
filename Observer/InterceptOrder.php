<?php
namespace Feedaty\Badge\Observer;

use Feedaty\Badge\Model\Config\Source\WebService;
use \Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\UrlInterface;
use \Magento\Catalog\Helper\Image;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Framework\App\Config\ScopeConfigInterface;

class InterceptOrder implements ObserverInterface
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
    * @var \Magento\Catalog\Helper\Image
    */
    protected $imageHelper;

    /**
    * Constructor
    * 
    */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Image $imageHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
    }


    /**
    * Function execute
    *
    * @param $observer
    */
    public function execute(\Magento\Framework\Event\Observer $observer){


            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

            $order = $observer->getEvent()->getOrder();

            $order_id = $order->getIncrementId();

            $verify = 0;

            $orderopt = $this->scopeConfig->getValue('feedaty_global/feedaty_sendorder/sendorder', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            foreach (($order->getAllStatusHistory()) as $orderComment) {
                if($orderComment->getStatus() === $orderopt) $verify++;
            }

           if ($order->getStatus() == $orderopt && $verify <= 1) {

                $baseurl_store = $this->storeManager->getStore($order->getStore_id())->getBaseUrl(UrlInterface::URL_TYPE_LINK);

                $objproducts = $order->getAllItems();

                unset($fd_products);
                
                foreach ($objproducts as $itemId => $item) {
                    unset($tmp);

                    if (!$item->getParentItem()) {
                        $fd_oProduct = $objectManager->get('Magento\Catalog\Model\Product')->load((int) $item->getProductId());

                        if ($fd_oProduct->getTypeId() == \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) {

                            $selectionCollection = $fd_oProduct->getTypeInstance(true)->getSelectionsCollection(
                                $fd_oProduct->getTypeInstance(true)->getOptionsIds($fd_oProduct), $fd_oProduct
                            );
                            foreach($selectionCollection as $option) {
                                $bundleproduct = $objectManager->get('Magento\Catalog\Model\Product')->load($option->product_id);

                                $tmp['Id'] = $bundleproduct->getProductId();

                                //get the product url
                                $tmp['Url'] = $fd_oProduct->getUrlModel()->getUrl($fd_oProduct);

                                if ($fd_oProduct->getImage() != "no_selection"){
                                    $store = $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore();
                                    $tmp['ImageUrl'] = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $fd_oProduct->getImage();
                                }
                                else
                                    $tmp['ImageUrl'] = "";
                                //$tmp['sku'] = $item->getSku();
                                $tmp['Name'] = $bundleproduct->getName();
                                $tmp['Brand'] = $bundleproduct->getBrand();
                                if (is_null($tmp['Brand'])) $bundleproduct['Brand']  = "";
                                $fd_products[] = $tmp;
                            }
                        } else {
                            $tmp['Id'] = $item->getProductId();
                            $tmp['Url'] = $fd_oProduct->getUrlModel()->getUrl($fd_oProduct);

                            //get the image url
                            if ($fd_oProduct->getImage() != "no_selection") {
                                $store = $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore();
                                $tmp['ImageUrl'] = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $fd_oProduct->getImage();
                            }
                            else
                                $tmp['ImageUrl'] = "";

                            $tmp['Name'] = $item->getName();
                            $tmp['Brand'] = $item->getBrand();
                            if (is_null($tmp['Brand'])) $tmp['Brand']  = "";

                            //$tmp['Price'] = $item->getPrice();
                            $fd_products[] = $tmp;
                        }
                    }
                }

                $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');

                // Formatting the array to be sent
                $tmp_order['OrderId'] = $order->getId();
                $tmp_order['OrderDate'] = date("Y-m-d H:i:s");
                $tmp_order['CustomerEmail'] = $order->getCustomerEmail();
                $tmp_order['CustomerId'] = $order->getCustomerEmail();
                $tmp_order['Platform'] = "Magento ".$productMetadata->getVersion();
                $tmp_order['Products'] = $fd_products;

                $fd_data['merchantCode'] = $this->scopeConfig->getValue('feedaty_global/feedaty_preferences/feedaty_code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

                $fd_data['orders'][] = $tmp_order;

                // Sending request to feedaty server
                WebService::send_order($fd_data);

                //TODOcustom : provare i prodotti bundle
                
            }
        }
}