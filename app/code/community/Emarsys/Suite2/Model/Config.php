<?php

class Emarsys_Suite2_Model_Config extends Varien_Object
{
    const SYNC_LAST_UPDATE_OPTIN_CONTACT_EXPORT = 'luexim';
    const SYNC_DAILY_IMPORT_TO_EXPORT = 'imex';
    const SYNC_DAILY_EXPORT_TO_IMPORT = 'exim';
    const XPATH_EMARSYS_SIEXPORT_API_ENABLED = 'emarsys_suite2_smartinsight/api/enableapi';
    const XPATH_EMARSYS_SIEXPORT_MERCHANT_ID = 'emarsys_suite2_smartinsight/api/merchant_id';
    const XPATH_EMARSYS_SIEXPORT_TOKEN = 'emarsys_suite2_smartinsight/api/token';
    const XPATH_EMARSYS_SIEXPORT_API_URl = 'emarsys_suite2_smartinsight/api/smartinsight_api_url';
    const XPATH_EMARSYS_SIEXPORT_API_URl_KEY = 'emarsys_suite2_smartinsight/api/smartinsight_order_api_url_key';
    const XPATH_EMARSYS_CATALOG_EXPORT_API_URl_KEY = 'emarsys_suite2_smartinsight/api/smartinsight_product_api_url_key';

    const XML_PATH_EMARSYS_FULL_CATALOG_EXPORT_ENABLE = 'catalogexport/configurable_cron/fullcatalogexportenabled';
    const XML_PATH_EMARSYS_CATALOGEXPORT_API_ENABLE   = 'catalogexport/api/enableapi';
    const XML_PATH_EMARSYS_CATALOGEXPORT_API_MERCHANT_ID   = 'catalogexport/api/merchant_id';
    const XML_PATH_EMARSYS_CATALOGEXPORT_API_TOKEN   = 'catalogexport/api/token';

    protected $_storeId = 0;
    
    protected function _construct()
    {
        if (!Mage::app()->getStore()->isAdmin()) {
            $this->setStore(Mage::app()->getStore());
        } else {
            $this->setStore(0);
        }

        parent::_construct();
    }
    
    public function getDebug()
    {
        return Mage::getStoreConfig('emarsys_suite2/settings/debug', $this->_storeId);
    }
    
    public function setStore($store)
    {
        $this->_storeId = (is_object($store) ? $store->getId() : $store);
        $this->unsetData();
        $this->_addConfigNode('');
        $this->_addConfigNode('smartinsight');
        return $this;
    }
    
    protected function _addConfigNode($code)
    {
        $suffix = (strlen($code) ? '_' . $code : '');
        
        $nodePath = 'stores/' . Mage::app()->getStore($this->_storeId)->getCode() . '/emarsys_suite2' . $suffix;

        foreach (Mage::app()->getConfig()->getNode($nodePath) as $node) {
            foreach ($node as $nodeKey => $nodeValues) {
                foreach ($nodeValues as $fieldName => $fieldValue) {
                    $this->setData(trim($code . '_' . $nodeKey . '_' . $fieldName, '_'), (string)$fieldValue);
                }
            }
        }
    }
    
    public function setWebsite($website)
    {
        /* @var $website Mage_Core_Model_Website */
        $storeId = (is_object($website) ?
            $website->getDefaultStore()->getId() :
            Mage::app()->getWebsite($website)->getDefaultStore()->getId()
        );
        if (($storeId == $this->_storeId) && ($website !== 0)) {
            return $this;
        } else {
            $this->setStore($storeId);
        }

        return $this;
    }
    
    public function getWebsiteId()
    {
        return Mage::app()->getStore($this->_storeId)->getWebsiteId();
    }
    
    public function getStoreId()
    {
        return $this->_storeId;
    }
    
    /**
     * Returns contacts sync mode
     * 
     * @return string
     */
    public function getSyncMode()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/settings/mode', $this->_storeId);
    }
    
    /**
     * Returns Emarsys key identifier
     * 
     * @return string
     */
    public function getEmarsysCustomerKeyId()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/field_mapping/key_id', $this->_storeId);
    }

    /**
     * Returns Emarsys key identifier
     *
     * @return string
     */
    public function getEmarsysEmailKeyId()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/field_mapping/email', $this->_storeId);
    }

    /**
     * Returns Emarsys Opt In field identifier
     * 
     * @return string
     */
    public function getEmarsysOptInFieldId()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/field_mapping/optin_id', $this->_storeId);
    }


    /**
     * Return true if Key_id is Email
     * @return mixed
     */
    public function isEmailKeyId()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/settings/email_as_id', $this->_storeId);
    }
    
    /**
     * Returns Emarsys Opt In field identifier
     * 
     * @return string
     */
    public function getEmarsysOptInTrue()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/field_mapping/optin_true', $this->_storeId);
    }
    
    /**
     * Returns Emarsys Opt In field identifier
     * 
     * @return string
     */
    public function getEmarsysOptInFalse()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/field_mapping/optin_false', $this->_storeId);
    }
    
    /**
     * Returns Emarsys key identifier
     * 
     * @return string
     */
    public function getEmarsysSubscriberKeyId()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/field_mapping/subscriber_key_id', $this->_storeId);
    }
    
    /**
     * Returns true if customers export is enabled for website
     * 
     * @return string
     */
    public function isOrdersExportEnabled()
    {
        return Mage::getStoreConfig('emarsys_suite2_smartinsight/settings/enabled', $this->_storeId);
    }
    
    /**
     * Returns true if customers export is enabled for website
     * 
     * @return string
     */
    public function isCustomersExportEnabled()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/settings/customerslist_export_enabled', $this->_storeId);
    }

    /**
     * Returns true if subscribers export is enabled for website
     * 
     * @return string
     */
    public function isSubscribersExportEnabled()
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/settings/subscriberslist_export_enabled', $this->_storeId);
    }
    
    public function getContactsSyncOrder()
    {
        return self::SYNC_LAST_UPDATE_OPTIN_CONTACT_EXPORT;
    }

    /**
     * Returns mapping array
     * 
     * @return array
     */
    public function getMapping()
    {
        $result = array('is_subscribed' => $this->getEmarsysOptInFieldId());
        $mapping = explode("\n", Mage::getStoreConfig('emarsys_suite2_contacts_sync/field_mapping/mapping', $this->_storeId));
        foreach ($mapping as $map) {
            $map = trim($map, "\n\r");
            if ($map && ($map = explode(':', $map)) && (count($map) == 2)) {
                $result[$map[0]] = $map[1];
            }
        }

        return $result;
    }
    
    /**
     * Returns true if module is enabled for website
     * 
     * @return string
     */
    public function isEnabled()
    {
        return Mage::getStoreConfig('emarsys_suite2/settings/enabled', $this->_storeId);
    }
    
    /**
     * Returns notification URL including secret key
     * 
     * @return type
     */
    public function getExportsNotificationUrl($websiteId = 0, $isTimeBased = false)
    {
        $storeId = $this->_storeId;
        $useSecureUrl = $this->useSecureUrl($storeId);
        if($useSecureUrl){
            $security = true;
        }else{
            $security = false;
        }
        $oldEntryPoint = Mage::registry('custom_entry_point');
        if ($oldEntryPoint) {
            Mage::unregister('custom_entry_point');
        }

        Mage::register('custom_entry_point', 'index.php');
        $url = Mage::getUrl(
            'emarsys_suite2/index/sync',
            array(
                '_secure'=>$security,
                '_store' => $this->_storeId,
                '_query' => array('secret' => Mage::getStoreConfig('emarsys_suite2_contacts_sync/settings/notification_secret', $this->_storeId), 'website_ids' => implode(',', $websiteId)),
            )
        );
        if($isTimeBased){
            $url = Mage::getUrl(
                'emarsys_suite2/index/sync',
                array(
                    '_store' => $this->_storeId,
                    '_query' => array('secret' => Mage::getStoreConfig('emarsys_suite2_contacts_sync/settings/notification_secret', $this->_storeId), 'website_ids' => implode(',', $websiteId), 'timebased' => 1),
                    '_secure'=>$security
                )
            );
        }
        Mage::unregister('custom_entry_point');
        if ($oldEntryPoint) {
            Mage::register('custom_entry_point', $oldEntryPoint);
        }

        return $url;
    }
    
    /**
     * Saves value in config
     */
    public function setValue($key, $value)
    {
        Mage::getConfig()->saveConfig('emarsys_suite2/storage/' . $key, $value, 'websites', $this->getWebsiteId());
        Mage::getConfig()->cleanCache();
    }
    
    /**
     * Saves value in config
     */
    public function getValue($key)
    {
        return Mage::getStoreConfig('emarsys_suite2/storage/' . $key, $this->_storeId);
    }

    public function useSecureUrl($storeId)
    {
        return Mage::getStoreConfig('emarsys_suite2_contacts_sync/settings/emarsys_secure_url',$storeId);
    }



    /** Smart Insight Merchant Id
     * @param null $storeId
     * @return mixed
     */
    public function isSIAPIExportEnabled()
    {
        return Mage::getStoreConfig(self::XPATH_EMARSYS_SIEXPORT_API_ENABLED, $this->_storeId);
    }

    /** Smart Insight Merchant Id
     * @param null $storeId
     * @return mixed
     */
    public function getSIExportMerchantId()
    {
        return Mage::getStoreConfig(self::XPATH_EMARSYS_SIEXPORT_MERCHANT_ID, $this->_storeId);
    }

    /** Smart Insight API token
     * @param null $storeId
     * @return mixed
     */

    public function getSIExportToken()
    {
        return Mage::getStoreConfig(self::XPATH_EMARSYS_SIEXPORT_TOKEN, $this->_storeId);
    }

    /** Smart Insight API url
     * @param null $storeId
     * @return mixed
     */
    public function getSmartInsightApiUrl()
    {
        return Mage::getStoreConfig(self::XPATH_EMARSYS_SIEXPORT_API_URl, $this->_storeId);
    }

    /** Smart Insight Order API url key
     * @param null $storeId
     * @return mixed
     */
    public function getOrderApiUrlKey()
    {
        return Mage::getStoreConfig(self::XPATH_EMARSYS_SIEXPORT_API_URl_KEY, $this->_storeId);
    }

    /**Smart Insight Product API url key
     * @param null $storeId
     * @return mixed
     */
    public function getProductApiUrlKey()
    {
        return Mage::getStoreConfig(self::XPATH_EMARSYS_CATALOG_EXPORT_API_URl_KEY, $this->_storeId);
    }

    /**
     * @return boolean
     */
    public function getCatalogExportApiEnable()
    {
        return Mage::getStoreConfig(self::XML_PATH_EMARSYS_CATALOGEXPORT_API_ENABLE, $this->_storeId);
    }

    /**
     * @return mixed
     */
    public function getCatalogExportApiMerchantId()
    {
        return Mage::getStoreConfig(self::XML_PATH_EMARSYS_CATALOGEXPORT_API_MERCHANT_ID, $this->_storeId);
    }

    /**
     * @return mixed
     */
    public function getCatalogExportApiToken()
    {
        return Mage::getStoreConfig(self::XML_PATH_EMARSYS_CATALOGEXPORT_API_TOKEN, $this->_storeId);
    }

    /**
     * @return boolean
     */
    public function getfullCatalogExportEnabled()
    {
        return Mage::getStoreConfig(self::XML_PATH_EMARSYS_FULL_CATALOG_EXPORT_ENABLE, $this->_storeId);
    }

    /**
     * @return boolean
     */
    public function getMaxRecordsPerExportCount()
    {
        return Mage::getStoreConfig('emarsys_suite2_smartinsight/api/max_records_per_export', $this->_storeId);
    }

}