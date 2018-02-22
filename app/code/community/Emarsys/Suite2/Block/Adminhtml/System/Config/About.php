<?php
/**
 *
 * @category   Suite2email
 * @package    Emarsys_Suite2email
 * @copyright  Copyright (c) 2016 Kensium Solution Pvt.Ltd. (http://www.kensiumsolutions.com/)
 */

/**
 * Class Emarsys_Suite2_Block_Adminhtml_System_Config_About
 */
class Emarsys_Suite2_Block_Adminhtml_System_Config_About extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    CONST RELEASE_URL = 'https://github.com/emartech/3rd-party-integrations-magento-M1/releases/';
    CONST NOTIFICATION_TITLE = 'Emarsys Connector Version LATEST_VERSION is now available';
    CONST NOTIFICATION_DESCRIPTION = 'This update addressing various reported issues / enhancements. <a href="RELEASE_URL" target="_blank">Click Here</a> for more information';
    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        try {
            $html = '<div style="background: #EAF0EE; border:1px solid #CCCCCC; margin-bottom:10px; padding:15px; min-hight:150px; display: block;">';
            $currentVersion = Mage::getConfig()->getNode('modules/Emarsys_Suite2/version');
            $latestAvailableEmarsysVersion = 'N/A';
            $notification = '';
            $curlData = $this->getCurrentVersion();
            $emarsysLatestVersionDetails = json_decode($curlData, TRUE);

            if (!empty($emarsysLatestVersionDetails)) {
                if (isset($emarsysLatestVersionDetails['tag_name'])) {
                    $latestAvailableEmarsysVersion = $emarsysLatestVersionDetails['tag_name'];
                    //version_compare Returns 0 if both are equal, 1 if A > B, and -1 if B < A.
                    switch(version_compare($currentVersion,$latestAvailableEmarsysVersion)){
                        case 0:
                            $notification = "<div style='color:green; text-align:center; padding:15px; border:1px solid #ddd; background:#fff; font-size:16px; margin:8px 0;'>Congratulations, you are using the latest version of this Extension </div> ";
                            break;
                        case -1:
                            $this->checkEmarsysNotifications($latestAvailableEmarsysVersion, $currentVersion);
                            break;
                        default:
                            $notification = "<div style='color:red; text-align:center; padding:15px; border:1px solid red; background:#fff; font-size:16px; margin:8px 0;'>Sorry, Something went wrong with module version.</div> ";
                    }
                } elseif (isset($emarsysLatestVersionDetails['message'])) {
                    $notification = "<div style='color:red; text-align:center; padding:15px; border:1px solid red; background:#fff; font-size:16px; margin:8px 0;'>Sorry, No Latest Release Found.</div> ";
                    Mage::helper('emarsys_suite2')->log($emarsysLatestVersionDetails['message'], $this);
                } else {
                    $notification = "<div style='color:red; text-align:center; padding:15px; border:1px solid red; background:#fff; font-size:16px; margin:8px 0;'>Sorry, No Latest Release Found.</div> ";
                }
            }
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
            $notification = "<div style='color:red; text-align:center; padding:15px; border:1px solid red; background:#fff; font-size:16px; margin:8px 0;'>Error: " . $e->getMessage() . "</div> ";
        }

        $html .= $notification;
        $html .= "<div style='padding: 10px;  width: 49%; border: 1px solid #ddd; background: #fff;margin: 10px 10px; margin-right: 10px; margin-left: 0; float: left;box-sizing: border-box;'>Installed Emarsys Extension Version: <span style='font-weight:bold;font-size: 16px;color: #f77812;'> " . $currentVersion . '</span></div>';
        $html .= "<div style='float: left;padding: 10px;width: 49.5%;border: 1px solid #ddd;background: #fff;margin: 10px 0;box-sizing: border-box;'>Latest Emarsys Extension Version: <span style='font-weight:bold;font-size: 16px; color: #f98721;'>" . $latestAvailableEmarsysVersion . '</span></div>';
        $html .= "<div style='width: 100%; display: block; text-align: center; padding: 10px;'>" . '<p><a style="cursor:pointer" href="'. self::RELEASE_URL . '" style="cursor:pointer;font-size: 15px;padding-left: 2px;" target="_blank">Click Here</a> to readmore about the latest release.</p></div>';
        $html .= " </div> ";
        $html .= "<script type='text/javascript'> window.onload = function () { if (document.getElementById('row_emarsys_suite2_about_info_api_url')) {document.getElementById('row_emarsys_suite2_about_info_api_url').style.display='none';}}</script>";
        return $html;
    }

    /**
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getCurrentVersion()
    {
        $apiUrl = Mage::getStoreConfig('emarsys_suite2_about/info/api_url');
        $result = json_encode(array('message' => 'Sorry! Unable to fetch the latest version.'));
        try {
            $ch = curl_init();
            $timeout = 500;
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
            $result = curl_exec($ch);
            if (curl_errno($ch)) {
                $error = Mage::helper('emarsys_suite2')->__('curl_errno: ' . curl_errno($ch) . 'curl_error : ' . curl_error($ch) );
                curl_close($ch);
                Mage::throwException($error);
            }
            curl_close($ch);
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
            $result = json_encode(array('message' => $e->getMessage()));
        }

        return $result;
    }

    /**
     * @param $latetVersion
     * @param $currentVersion
     */
    public function checkEmarsysNotifications($latetVersion, $currentVersion)
    {
        try {
            if ($latetVersion > $currentVersion) {
                $title = str_replace('LATEST_VERSION', $latetVersion, self::NOTIFICATION_TITLE);
                $description = str_replace('RELEASE_URL', self::RELEASE_URL, self::NOTIFICATION_DESCRIPTION);
                $dateAdded = date('Y-m-d H:i:s', strtotime('-7 days'));
                $notificationCollection = Mage::getModel('adminnotification/inbox')->getCollection()
                                    ->addFieldToFilter('title', $title)
                                    ->addFieldToFilter('date_added', array('gteq' => $dateAdded))
                                    ->setOrder('date_added', 'DESC');
                //echo $notificationCollection->getSelect(); exit;
                if (count($notificationCollection) == 0) {
                    Mage::getModel('adminnotification/inbox')->add(Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE, $title, $description, self::RELEASE_URL, true);
                } else {
                    foreach ($notificationCollection as $_adminNotification) {
                        $notification = Mage::getModel('adminnotification/inbox')->load($_adminNotification->getNotificationId());
                        if($notification->getNotificationId()) {
                            $notification->setIsRead(0);
                            $notification->setIsRemove(0);
                            $notification->save();
                            break;
                        } 
                    }
                }
            }
        } catch (Exception $e) {
            Mage::helper('emarsys_suite2')->log($e->getMessage(), $this);
        }
    }
}
