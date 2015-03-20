<?php

require_once 'abstract.php';

class Eschrade_Shell_FlushFpc extends Mage_Shell_Abstract
{
    
    public function run()
    {
        if ($this->getArg('help') || !$this->getArg('url')) {
            echo $this->usageHelp();
            return;
        }
        $storeCode = $this->getArg('store');
        $store = Mage::getModel('core/store');
        /* @var $store Mage_Core_Model_Store */
        if ($storeCode) {
            $store->load($storeCode);
        } else {
            $store = Mage::app()->getDefaultStoreView();
        }
        if (!$store->getStoreId()) {
            Mage::throwException('Unable to load store');
        }
        Mage::app()->setCurrentStore($store);
        $request = new Mage_Core_Controller_Request_Http($this->getArg('url'));
        $className = (string)Mage::getConfig()->getNode('global/request_rewrite/model');
        
        $urlModel = Mage::getSingleton('core/factory')->getModel($className, array('request' => $request));
        /* @var $urlModel Mage_Core_Model_Url_Rewrite_Request */ 
        $urlModel->rewrite();
        
        $pathInfo = $request->getPathInfo();
        $db = Mage::getSingleton('core/resource')->getConnection();
        /* @var $db Magento_Db_Adapter_Pdo_Mysql */
        $select = $db->select()->from($db->getTableName('core_url_rewrite'), array('product_id', 'category_id'));
        $select->where('target_path = ?', $pathInfo);
        $select->where('store_id = ?', $store->getStoreId());
        $select->limit(1);
        $row = $db->fetchRow($select);
        if (!$row) {
            echo "Unable to find {$this->getArg('url')}\n";
        }
        $tags = null;
        if ($row['category_id']) {
            $category = Mage::getModel('catalog/category')->load($row['category_id']);
            /* @var $category Mage_Catalog_Model_Category */
            $tags = $category->getCacheIdTags();
        } else if ($row['product_id']) {
            $product = Mage::getModel('catalog/product')->load($row['product_id']);
            /* @var $product Mage_Catalog_Model_Product */
            $tags = $product->getCacheIdTags();
        }
        $cache = Enterprise_PageCache_Model_Cache::getCacheInstance();
        $cache->clean($tags);
    }
    
    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f flush-fpc.php [options]
    
  --url <url>                   The URL to flush
  --storecode <code>            The store code of the URL (defaults to the default store)
  help                          This help


USAGE;
    }
}

$shell = new Eschrade_Shell_FlushFpc();
$shell->run();
