<?php

/**
 *  @method Emarsys_Suite2_Model_Adapter_Http_Curl getAdapter()
 */
class Emarsys_Suite2_Model_Apiexport extends Varien_Http_Client
{
    protected $_apiUrl;
    protected $_merchantId;
    protected $_token;

    const MIN_CATALOG_RECORDS_COUNT = 5;

    const API_ERROR_CONNECTION = 'Connection error';
    const API_ERROR_RESPONSE_INVALID = 'Invalid response';
    const DEBUG_KEY = 'EMARSYS_DEBUG_INFO';

    /**
     * @param $apiCall
     * @param $apiMethod
     * @param $data
     */
    protected function _debug($apiCall, $apiMethod, $data)
    {
        if ($this->_debug) {
            Mage::getSingleton('emarsys_suite2/debug')->debug($apiCall, $apiMethod, $data);
        }
    }

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
     * @param null $params
     */
    public function __construct($params)
    {
        $this->_apiUrl = $this->_getConfig()->getSmartInsightApiUrl() . $params['merchant_id'] . $this->_getConfig()->getProductApiUrlKey();
        $this->_merchantId = $params['merchant_id'];
        $this->_token = $params['token'];
        $this->_debug = Mage::registry(self::DEBUG_KEY);
        return parent::__construct($this->_apiUrl);
    }

    /**
     * API URLs for sales order and Product
     * @return string
     */
    public function getCatalogApiUrl()
    {
        return $this->_getConfig()->getSmartInsightApiUrl() . $this->_merchantId . $this->_getConfig()->getProductApiUrlKey();
    }

    /**
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function testFullCatalogExportApi($params)
    {
        //print_r($params); exit;
        $emarsysFieldNames = array();
        $staticExportArray = Mage::helper('webextend')->getstaticExportArray();
        for ($ik = 0; $ik < count($staticExportArray); $ik++) {
            if (!in_array($staticExportArray[$ik], $emarsysFieldNames)) {
                $emarsysFieldNames[] = $staticExportArray[$ik];

            }
        }

        $io = new Varien_Io_File();
        $test_file = 'emarsys_test.csv';
        $data = Mage::getBaseDir('var') . '/' . $test_file;
        if ($io->fileExists($data)) {
            $io->rm($data);
        }
        $io->cd(Mage::getBaseDir('var'));
        $io->streamOpen($test_file, 'a');
        $emptyHeader = $emarsysFieldNames;
        $io->streamWriteCsv($emptyHeader, ',', '"');
        $sampleData = $this->sampleDataCatalogFullExport();
        for ($i = 0; $i < count($sampleData); $i++) {
            $io->streamWriteCsv($sampleData[$i], ',', '"');
        }
        $io->streamClose();

        $apiUrl = $this->getCatalogApiUrl();
        return $this->apiExport($apiUrl, $data, true);
    }

    /**
     * @param $params
     * @param $filepath
     * @return mixed
     */
    public function fullCatalogExportApi($params, $filepath)
    {
        $apiUrl = $this->getCatalogApiUrl();
        return $this->apiExport($apiUrl, $filepath);
    }

    /**
     * Sample Data for Catalog full export test connection.
     * @return array
     */
    public function sampleDataCatalogFullExport()
    {
        $returnArr = array();
        for ($k = 1; $k <= self::MIN_CATALOG_RECORDS_COUNT; $k++) {
            $returnArr[] = array(
                'test_product_item_' . $k,
                'true',
                'test_product_title_' . $k,
                Mage::getBaseUrl(),
                Mage::getBaseUrl(),
                'test_category_' . $k,
                '00.00'
            );
        }
        return $returnArr;
    }

    /**
     * Get API Headers
     *
     * @param $token
     * @return array|bool
     */
    public function getApiHeaders($token)
    {
        if (!isset($token)) {
            $token = $this->_token;
        }
        if ($token) {
            $headers = array();
            $headers[] = "Authorization: bearer " . $token;
            $headers[] = "Content-type: text/csv";
            $headers[] = "Accept: text/plain";
            //$headers[] = "Content-Encoding: gzip";
            return $headers;
        }
        Mage::helper('emarsys_suite2')->log('API token is missing in header.');
        return false;
    }

    /**
     * @param $apiUrl
     * @param $file_path
     * @param bool $testConnection
     * @return mixed
     */
    public function apiExport($apiUrl, $file_path, $testConnection = false)
    {
        if (!empty($apiUrl) && !empty($file_path)) {
            $result = 0;
            Mage::helper('emarsys_suite2')->log("API URL: " . $apiUrl . " And File Path: " . $file_path);
            $data = file_get_contents($file_path);
            $response = $this->post($apiUrl, $data);
            if ($response->getStatus() == 200 || ($response->getStatus() == 400 && $testConnection)) {
                $result = 1;
            }
            return Mage::helper('core')->jsonEncode(array('result' => $result, 'resultBody' => nl2br($response->getBody())));
        } else {
            Mage::helper('emarsys_suite2')->log('apiExport Failed. API URL or CSV File Not Found.');
            $res = array('result' => 0, 'resultBody' => 'apiExport Failed. API URL or CSV File Not Found.');
            return Mage::helper('core')->jsonEncode($res);
        }
    }

    /**
     * @param $apiCall
     * @param string $method
     * @param string $data
     * @param bool $jsonDecode
     * @return string
     * @throws Exception
     * @throws Zend_Http_Client_Exception
     */
    protected function _request($apiCall, $method = Zend_Http_Client::GET, $data = '', $jsonDecode = false)
    {
        $this->_debug($apiCall, $method, $data);
        Mage::helper('emarsys_suite2')->log('REST Tx: ' . $method . ' ' . $this->_apiUrl . '/' . $apiCall . ' ==> data: ' . Mage::helper('core')->jsonEncode($data));
        $this->setUri($this->_apiUrl);
        $this->setHeaders(
            $this->getApiHeaders($this->_token)
        );

        try {
            if ($method == "GET" && !(empty($data))) {
                $this->setParameterGet($data);
            } else {
                if (!empty($data)) {
                    $this->setRawData($data);
                }
            }

            $responseObject = $this->request($method);
            $response = $responseObject->getBody();
            Mage::helper('emarsys_suite2')->log('REST Rx: ' . $method . ' ' . $this->_apiUrl . '/' . $apiCall . ' ==> data: ' . $response);
            if ($jsonDecode) {
                try {
                    $response = Mage::helper('core')->jsonDecode($response);
                } catch (Exception $e) {
                    Mage::log('JSON Decode failed. Got response headers from API:');
                    Mage::log($responseObject->getHeadersAsString(true, '. '), $this);
                    throw $e;
                }

                if ($response['replyCode'] !== 0) {
                    throw new Exception($response['replyText']);
                }
            }
            $response = $responseObject;
        } catch (Exception $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * @param $apiCall
     * @param string $data
     * @return string
     * @throws Exception
     *
     */
    public function post($apiCall, $data = '')
    {
        return $this->_request($apiCall, Zend_Http_Client::POST, $data);
    }

    /**
     * @return string
     */
    public function getSmartInsightApiUrl()
    {
        $orderApiUrl = $this->_getConfig()->getSmartInsightApiUrl() . $this->_merchantId . $this->_getConfig()->getOrderApiUrlKey();
        return $orderApiUrl;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function testSIExportApi()
    {
        $io = new Varien_Io_File();
        $test_file = 'test.csv';
        $data = Mage::getBaseDir('var') . '/' . $test_file;
        if ($io->fileExists($data)) {
            $io->rm($data);
        }
        $io->cd(Mage::getBaseDir('var'));
        $io->streamOpen($test_file, 'a');
        $emptyFileHeader = Mage::getSingleton('emarsys_suite2/api_order')->getSalesOrderHeader();
        $io->streamWriteCsv($emptyFileHeader, ',', '"');
        $sampleData = $this->getSampleData();
        $io->streamWriteCsv($sampleData, ',', '"');
        $io->streamClose();

        $this->_apiUrl = $apiUrl = $this->getSmartInsightApiUrl();
        return $this->apiExport($apiUrl, $data, true);
    }

    /**
     * Get Sales Order Sample Data for Test Connection Button.
     *
     * @return array
     */
    public function getSampleData()
    {
        return array(
            'test_product_item_1',
            '0.0',
            '00000',
            '2016-06-06T14:02:00Z',
            'sample@data.com',
            '0',
            '00.00',
            '0',
        );
    }


    /**
     * API URL for SI Export
     * @return string
     */
    public function fullSIExportApi($params)
    {
        $data = $params['filepath'];
        $this->_apiUrl = $apiUrl = $this->getSmartInsightApiUrl();
        $result = $this->apiExport($apiUrl, $data);
        if ($result === true) {
            return 1;
        } elseif ($result === false) {
            Mage::helper('emarsys_suite2')->log('Test Connection failed. Unknown API error.');
            return "Test Connection failed. Unknown API error. ";
        } else {
            Mage::helper('emarsys_suite2')->log($result);
            if (empty($result)) {
                return "Test Connection failed. Unknown API error. ";
            }
            return $result;
        }
    }
}
