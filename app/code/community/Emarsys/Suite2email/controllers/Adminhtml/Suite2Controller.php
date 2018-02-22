<?php

/**
 *
 * @category   Suite2email
 * @package    Emarsys_Suite2email
 * @copyright  Copyright (c) 2016 Kensium Solution Pvt.Ltd. (http://www.kensiumsolutions.com/)
 */
require_once("Emarsys/Suite2/controllers/Adminhtml/Suite2Controller.php");

class Emarsys_Suite2email_Adminhtml_Suite2Controller extends Emarsys_Suite2_Adminhtml_Suite2Controller
{
    /**
     * Queues 2 years order export
     */
    public function exportAllOrdersAction()
    {
        try {
            set_time_limit(0);
            $pageNum = 1;
            try {
                $result = $this->_queueOrdersBatch($pageNum);
                if ($result) {
                    Mage::helper('emarsys_suite2/adminhtml')->scheduleCronjob('orders');
                    printf(1);
                } else {
                    printf ("Error: No paid orders found");
                }
            } catch (Exception $e) {
                Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
                printf("Error: {$e->getMessage()}");
            }
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
        }
    }

    /**
     * Batch queueing
     *
     * @param type $pageNum
     *
     * @return boolean
     */
    protected function _queueOrdersBatch($pageNum)
    {
        $returnVal = false;
        try {
            /* Multiwebsite Support*/
            foreach (Mage::app()->getWebsites() as $website) {
                $websiteCode = $website->getData('code');
                $_website = Mage::getModel('core/website')->load($website->getId());
                $websiteStoreIds = $_website->getStoreIds();
                $orderIds = array();
                if (Mage::getStoreConfig('emarsys_suite2_smartinsight/settings/enabled', current($websiteStoreIds))) {
                    $pageSize = Mage::helper('emarsys_suite2/adminhtml')->getBatchSize();
                    /* @var $collection Mage_Sales_Model_Resource_Order_Collection */
                    $collection = Mage::getResourceModel('sales/order_collection')
                        ->addFieldToFilter('created_at', array('gteq' => new Zend_Db_Expr('CURRENT_DATE - INTERVAL 2 YEAR')))
                        ->addFieldToFilter('status', array('IN' => Mage::helper('suite2email')->getOrderStatuses($websiteCode)))
                        ->addFieldToFilter('store_id', array('IN' => $websiteStoreIds));

                    $collection->setPageSize($pageSize);

                    if ($collection->count()) {
                        $pageCount = $collection->getLastPageNumber();
                        $currentPage = 1;

                        do {
                            $collection->setCurPage($currentPage);
                            $collection->load();
                            $_orderIds = $collection->getColumnValues('entity_id');
                            $orderIds = array_merge($orderIds, $_orderIds);
                            Mage::getSingleton('emarsys_suite2/queue')->addCollection($collection);
                            $currentPage++;
                            //clear collection and free memory
                            $collection->clear();

                        } while ($currentPage <= $pageCount);
                    }
                    if (count($orderIds) > 0) {
                        // Queue collection
                        $collection = Mage::getResourceModel('sales/order_creditmemo_collection')
                            ->addFieldToFilter('created_at', array('gteq' => new Zend_Db_Expr('CURRENT_DATE - INTERVAL 2 YEAR')))
                            ->addFieldToFilter('state', Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED)
                            ->addFieldToFilter('store_id', array('IN' => $websiteStoreIds))
                            ->addFieldToFilter('order_id', array('IN' => $orderIds));

                        Mage::getSingleton('emarsys_suite2/queue')->addCollection($collection);
                        $returnVal = true;
                    }
                }
            }
        }catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
        }
        return $returnVal;
    }
}
