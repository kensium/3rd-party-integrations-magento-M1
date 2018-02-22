<?php

/**
 *
 * @category   Webextend
 * @package    Emarsys_Webextend
 * @copyright  Copyright (c) 2017 Kensium Solution Pvt.Ltd. (http://www.kensiumsolutions.com/)
 */
class Emarsys_Webextend_Model_Observer
{
    /**
     * Returns config object
     *
     * @return Emarsys_Suite2_Model_Config
     */
    protected function _getConfig()
    {
        return Mage::getSingleton('emarsys_suite2/config');
    }

    /**
     * @param $store
     * @return array
     */
    public function fullCatalogCollectionToCsv($store)
    {
        $storeCode = $store->getData("code");
        $storeId = $store->getId();
        $emarsysFieldNames = array();
        $magentoAttributeNames = array();
        $staticExportArray = Mage::helper('webextend')->getstaticExportArray();
        $staticMagentoAttributeArray = Mage::helper('webextend')->getstaticMagentoAttributeArray();
        $exportProductStatus = Mage::getStoreConfig("catalogexport/configurable_cron/webextenproductstatus", $storeId);
        $exportProductTypes = Mage::getStoreConfig("catalogexport/configurable_cron/webextenproductoptions", $storeId);

        //Getting mapped emarsys attribute collection
        $model = Mage::getModel('webextend/emarsysproductattributesmapping');
        $collection = $model->getCollection();
        $collection->addFieldToFilter("store_id", $storeId);
        $collection->addFieldToFilter("emarsys_attribute_code_id", array("neq" => 0));

        if ($collection->getSize()) {
            //need to make sure required mapping fields should be there else we have to manually map.
            foreach ($collection as $col_record) {
                $emarsysFieldName = Mage::getModel('webextend/emarsysproductattributesmapping')
                    ->getEmarsysFieldName($storeId, $col_record->getData('emarsys_attribute_code_id'));
                $emarsysFieldNames[] = $emarsysFieldName;
                $magentoAttributeNames[] = $col_record->getData('magento_attribute_code');
            }
            for ($ik = 0; $ik < count($staticExportArray); $ik++) {
                if (!in_array($staticExportArray[$ik], $emarsysFieldNames)) {
                    $emarsysFieldNames[] = $staticExportArray[$ik];
                    $magentoAttributeNames[] = $staticMagentoAttributeArray[$ik];
                }
            }
        } else {
            // As we does not have any Magento Emarsys Attibutes mapping so we will go with default Emarsys export attributes
            for ($ik = 0; $ik < count($staticExportArray); $ik++) {
                if (!in_array($staticExportArray[$ik], $emarsysFieldNames)) {
                    $emarsysFieldNames[] = $staticExportArray[$ik];
                    $magentoAttributeNames[] = $staticMagentoAttributeArray[$ik];
                }
            }
        }
        $currentPageNumber = 1;
        $pageSize = Mage::helper('emarsys_suite2/adminhtml')->getBatchSize();
        //Product collection with 1000 batch size
        $productCollection = Mage::getModel("webextend/emarsysproductattributesmapping")
            ->getCatalogExportProductCollection($store, $exportProductTypes, $exportProductStatus, $pageSize, $currentPageNumber);

        $lastPageNumber = $productCollection->getLastPageNumber();
        //create CSV file with emarsys field name header
        if ($productCollection->getSize()) {
            $heading = $emarsysFieldNames;
            $localFilePath = BP . "/var";

            $outputFile = "products_" . date('YmdHis', time()) . "_" . $storeCode . ".csv";
            $filePath = $localFilePath . "/" . $outputFile;
            $handle = fopen($filePath, 'w');
            fputcsv($handle, $heading);

        }
        while ($currentPageNumber <= $lastPageNumber) {
            if ($currentPageNumber != 1) {
                $productCollection = Mage::getModel("webextend/emarsysproductattributesmapping")
                    ->getCatalogExportProductCollection($store, $exportProductTypes, $exportProductStatus, $pageSize, $currentPageNumber);
            }
            //iterate the product collection
            if (count($productCollection) > 0) {
                foreach ($productCollection as $product) {
                    try {
                        $productData = Mage::getModel("catalog/product")->setStoreId($storeId)->load($product->getId());
                    } catch (Exception $e) {
                        Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
                    }
                    $catIds = $productData->getCategoryIds();
                    $categoryNames = array();

                    //Get Category Names
                    foreach ($catIds as $catId) {
                        $cateData = Mage::getModel("catalog/category")->setStoreId($storeId)->load($catId);
                        $categoryPath = $cateData->getPath();
                        $categoryPathIds = explode('/', $categoryPath);
                        $childCats = array();
                        if (count($categoryPathIds) > 2) {
                            $pathIndex = 0;
                            foreach ($categoryPathIds as $categoryPathId) {
                                if ($pathIndex <= 1) {
                                    $pathIndex++;
                                    continue;
                                }
                                $childCateData = Mage::getModel("catalog/category")->load($categoryPathId);
                                $childCats[] = $childCateData->getName();
                            }
                            $categoryNames[] = implode(" > ", $childCats);
                        }
                    }

                    //getting Product Attribute Data
                    $attributeData = Mage::helper('webextend')->attributeData($magentoAttributeNames, $productData, $categoryNames);
                    fputcsv($handle, $attributeData);
                }
                //$currentPageNumber = $currentPageNumber + 1;
                $currentPageNumber++;
            } else {
                break;
            }
        }
        return array($outputFile,$filePath);
    }

    /**
     * Catalog Export Function which will call from Cron
     */
    public function catalogExport()
    {
        Mage::helper('emarsys_suite2')->log("Catalog Full Export has been initiated ==>", $this);
        try {
            set_time_limit(0);
            $staticExportArray = Mage::helper('webextend')->getstaticExportArray();
            $staticMagentoAttributeArray = Mage::helper('webextend')->getstaticMagentoAttributeArray();
            $allStores = Mage::app()->getStores();

            foreach ($allStores as $store) {
                $websiteId = $store->getData("website_id");
                $this->_getConfig()->setWebsite($websiteId);
                $storeId = $store->getData('store_id');
                Mage::helper('emarsys_suite2')->log("Catalog Full Export has been initiated for Website ID = " .$websiteId. " And Store ID = ". $storeId, $this);
                //Getting Configuration of SmartInsight and Webextend Export
                $hostname = Mage::getStoreConfig('emarsys_suite2_smartinsight/ftp/host', $storeId);
                $username = Mage::getStoreConfig('emarsys_suite2_smartinsight/ftp/user', $storeId);
                $password = Mage::getStoreConfig('emarsys_suite2_smartinsight/ftp/password', $storeId);
                $ftpSsl = Mage::getStoreConfig('emarsys_suite2_smartinsight/ftp/ssl', $storeId);
                $exportProductStatus = Mage::getStoreConfig("catalogexport/configurable_cron/webextenproductstatus", $storeId);
                $exportProductTypes = Mage::getStoreConfig("catalogexport/configurable_cron/webextenproductoptions", $storeId);
                if ($this->_getConfig()->getfullCatalogExportEnabled()) {
                    if ($this->_getConfig()->getCatalogExportApiEnable() == 1) {
                        Mage::helper('emarsys_suite2')->log(" Full Catalog Export API Enabled.", $this);
                        if (!empty($this->_getConfig()->getCatalogExportApiMerchantId()) && !empty($this->_getConfig()->getCatalogExportApiToken())) {
                            try {
                                $output = $this->fullCatalogCollectionToCsv($store);
                                if (!empty($output[0]) && !empty($output[1])) {
                                    Mage::helper('emarsys_suite2')->log('File name '.$output[0].' File Path'.$output[1], $this);
                                    $params = array();
                                    $params['filepath'] = $output[1];
                                    $params['merchant_id']  = $this->_getConfig()->getCatalogExportApiMerchantId();
                                    $params['token']  = $this->_getConfig()->getCatalogExportApiToken();
                                    $params['filename'] = $outPut[0];
                                    $exportResult =  Mage::getSingleton(
                                        'emarsys_suite2/apiexport',
                                        array(
                                            'merchant_id'  => $params['merchant_id'],
                                            'token'  => $params['token'],
                                        )
                                    )->fullCatalogExportApi($params, $params['filepath']);

                                    if ($exportResult) {
                                        Mage::helper('emarsys_suite2')->log("Full Catalog Export Completed Successfully Using API For Store ID: " . $storeId, $this);
                                    } else {
                                        Mage::helper('emarsys_suite2')->log("Full Catalog Export FAILED Using API For Store ID: " . $storeId, $this);
                                    }
                                }
                            } catch (Exception $e) {
                                Mage::helper('emarsys_suite2')->log('Error in Exporting the file using API: '.$e->getMessage(), $this);
                            }
                        } else {
                            Mage::helper('emarsys_suite2')->log('Error : Please check the API credentials', $this);
                        }
                    } else {
                        if ($hostname != '' && $username != '' && $password != '') {
                            if ($ftpSsl == 1) {
                                $ftpConnection = @ftp_ssl_connect($hostname);
                            } else {
                                $ftpConnection = @ftp_connect($hostname);
                            }
                            //Login to FTP
                            $ftpLogin = @ftp_login($ftpConnection, $username, $password);
                            if ($ftpLogin) {
                                try {
                                    $outPut = $this->fullCatalogCollectionToCsv($store);
                                    $outputFile = $outPut[0];
                                    $filePath = $outPut[1];
                                } catch (Exception $e) {
                                    Mage::helper('emarsys_suite2')->log('Error : In Exporting the file using FTP' . $e->getMessage(), $this);
                                }
                                Mage::helper('webextend')->moveToFTP($websiteId, $outputFile, $ftpConnection, $filePath);
                            } else {
                                Mage::helper('emarsys_suite2')->log("Unable to connect FTP for Store ID: " . $storeId, $this);
                            }
                        }
                    }
                } //CatalogfullExport Enable check
            }
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
        }
    }
    
    public function newSubscriberEmailAddress(Varien_Event_Observer $observer)
    {
        try {
            $event = $observer->getEvent();
            $subscriber = $event->getSubscriber();
            Mage::getSingleton('core/session')->setWebExtendCustomerEmail($subscriber->getSubscriberEmail());
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log('WebExtend_newSubscriberEmailAddress_Exception: ' . $e->getMessage(), $this);
        }
    }
    
    public function newCustomerEmailAddress(Varien_Event_Observer $observer)
    {
        try {
            $event = $observer->getEvent();
            $customer = $event->getCustomer();
            Mage::getSingleton('core/session')->setWebExtendCustomerEmail($customer->getEmail());
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log('WebExtend_newCustomerEmailAddress_Exception: ' . $e->getMessage(), $this);
        }
    }
    
    public function newOrderEmailAddress(Varien_Event_Observer $observer){
        try {
            $orderIds = $observer->getEvent()->getOrderIds();
            if (empty($orderIds) || !is_array($orderIds)) {
                return;
            }
            foreach($orderIds as $_orderId){
                $order = Mage::getModel('sales/order')->load($_orderId);
                Mage::getSingleton('core/session')->setWebExtendCustomerEmail($order->getCustomerEmail());
                if($order->getCustomerId()) {
                    Mage::getSingleton('core/session')->setWebExtendCustomerId($order->getCustomerId());
                }
            }
            Mage::getSingleton('core/session')->setWebExtendNewOrderIds($orderIds);
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log('WebExtend_newOrderEmailAddress_Exception: ' . $e->getMessage(), $this);
        }
    }

    public function hookToControllerActionPreDispatch(Varien_Event_Observer $observer)
    {
        try {
            if($observer->getEvent()->getControllerAction()->getRequest()->getParams() && $observer->getEvent()->getControllerAction()->getRequest()->getParam('email')) {
                Mage::getSingleton('core/session')->setWebExtendCustomerEmail($observer->getEvent()->getControllerAction()->getRequest()->getParam('email'));
            }

            if($observer->getEvent()->getControllerAction()->getRequest()->getPost() && $observer->getEvent()->getControllerAction()->getRequest()->getPost('email')) {
                Mage::getSingleton('core/session')->setWebExtendCustomerEmail($observer->getEvent()->getControllerAction()->getRequest()->getPost('email'));
            }
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log('WebExtend_hookToControllerActionPreDispagtch_Exception: ' . $e->getMessage(), $this);
        }
    }
}
