<?php
class Emarsys_Suite2email_Model_Observer extends Emarsys_Suite2_Model_Observer
{
    /**
     * Triggered after save commit, sends order and customer to Suite
     * @param Varien_Event_Observer $observer
     * @throws Mage_Core_Exception
     */
    public function orderSaveAfter(Varien_Event_Observer $observer)
    {
        try {
            if (!$this->_isEnabled()) {
                return $this;
            }
            $order = $observer->getData('order');
            /* Multiwebsite Support*/
            $storeId = $order->getStoreId();
            $websiteId = Mage::getModel('core/store')->load($storeId)->getWebsiteId();
            $website = Mage::app()->getWebsite($websiteId);
            $websiteCode = $website->getData('code');
            /* Multiwebsite Support*/
            if(!Mage::getStoreConfig('emarsys_suite2_smartinsight/settings/enabled', $storeId)) {
                return $this;
            }
            // Don't export orders that were already exported
            if (Mage::getModel('emarsys_suite2/flag_order', $order)->getIsExported()) {
                return $this;
            }
            // Do not export guest orders unless backend setting allow this
            if ($order->getCustomerIsGuest()
                && !Mage::getStoreConfig('emarsys_suite2_smartinsight/settings/guest_export', $order->getStoreId())
            ) {
                return;
            }
            Varien_Profiler::start('EmarsysSuite2::orderSaveAfter');
            /* @var $order Mage_Sales_Model_Order */
            // if order is paid, queue it up and export customer //
            if (in_array($order->getStatus(), Mage::helper('suite2email')->getOrderStatuses($websiteCode))) {
                Mage::getModel('emarsys_suite2/queue')->addEntity($order);
            }
            if (($customerId = $order->getCustomerId()) &&
                ($customer = Mage::getModel('customer/customer')->load($customerId)) &&
                ($customer->getId())
            ) {
                // add customer to observer and forward event further to customerSaveAfter
                $observer->setCustomer($customer);
                $this->customerSaveAfter($observer);
            }
            Varien_Profiler::stop('EmarsysSuite2::orderSaveAfter');
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
        }
    }
    /**
     * Check Emarsys events through cron
     * @throws Exception
     */
    public function checkEmarsysEvents()
    {
        try {
            //get emarsys events and store it into array
            $websites = Mage::app()->getWebsites();
            foreach($websites as $_websiteId) {
                $websiteId = $_websiteId->getData('website_id');
                Mage::getSingleton('emarsys_suite2/config')->setWebsite($websiteId);
                Mage::helper('emarsys_suite2')->log('Emarsys Event Schema Stared updating for WebsiteId =>'.$websiteId);
                $apiEvents = Mage::getModel('emarsys_suite2/api_event')->getEvents();
                $eventArray = array();
                Mage::helper('emarsys_suite2')->log('Emarsys Event Schema Array for WebsiteId('.$websiteId.') ===>');
                foreach ($apiEvents as $key => $value) {
                    $eventArray[$key] = $value;
                    Mage::helper('emarsys_suite2')->log($key.'=>'.$eventArray[$key]);
                }
                //Delete unwanted events exist in database
                $collection = Mage::getModel('suite2email/emarsysevents')->getCollection()->addFieldToFilter('website_id',array('eq'=>$websiteId));
                foreach ($collection as $coll) {
                    if (!array_key_exists($coll->getEventId(), $eventArray)) {
                        $model = Mage::getModel('suite2email/emarsysevents');
                        $model->load($coll->getId());
                        $model->delete();
                        Mage::helper('emarsys_suite2')->log('Deleted unwanted event exist in database, EventId =>'.$coll->getData('event_id').' WebSiteId =>'.$coll->getData('website_id'));
                    }
                }
                //Update & create new events found in Emarsys
                foreach ($eventArray as $key => $value) {
                    $collection = Mage::getModel('suite2email/emarsysevents')->getCollection()
                        ->addFieldToFilter("event_id", $key)
                        ->addFieldToFilter("website_id", $websiteId);
                    $firstEvent = $collection->getFirstItem();
                    if ($collection->getSize() && $firstEvent->getId()) {
                        $model = Mage::getModel('suite2email/emarsysevents')->load($firstEvent->getId());
                        $model->setEmarsysEvent($value);
                        $model->setWebsiteId($websiteId);
                        $model->save();
                        Mage::helper('emarsys_suite2')->log('Event exist in database got updated, EventId =>'.$firstEvent->getData('event_id').' WebSiteId =>'.$firstEvent->getData('website_id'));
                    } else {
                        $model = Mage::getModel('suite2email/emarsysevents');
                        $model->setEventId($key);
                        $model->setEmarsysEvent($value);
                        $model->setWebsiteId($websiteId);
                        $model->save();
                        Mage::helper('emarsys_suite2')->log('Event not exist in database got Created, EventId =>'.$firstEvent->getData('event_id').' WebSiteId =>'.$firstEvent->getData('website_id'));
                    }
                }
            }
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
        }
    }
    /**
     * Conditionnal Rewrite  Mage_Core_Model_Email_Template if
     * Store Configuration node  'emarsys_suite2_transmail/settings/enabled' is yes
     *
     * @param Varien_Event_Observer $observer
     */
    public function rewriteCoreEmailTemplate(Varien_Event_Observer $observer)
    {
        try {
            if (Mage::getStoreConfig('emarsys_suite2_transmail/settings/enabled')) {
                Mage::getConfig()->setNode(
                    'global/models/core/rewrite/email_template',
                    'Emarsys_Suite2email_Model_Email_Template'
                );
            }
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
        }
    }
}