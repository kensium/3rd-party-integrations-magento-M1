<?php

class Emarsys_Suite2_Model_Api_Payload_Creditmemo extends Emarsys_Suite2_Model_Api_Payload_Order
{
    /**
     * Order
     * 
     * @var Mage_Sales_Model_Order
     */
    protected $_order;
    
    /**
     * Order creditmemo
     * 
     * @var Mage_Sales_Model_Order_Creditmemo
     */
    
    protected $_creditmemo;
    /**
     * @inheritdoc
     */
    public function __construct(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $this->_creditmemo = $creditmemo;
        $this->_order = $creditmemo->getOrder();
        $this->_order->setRefundDate($creditmemo->getCreatedAt());
        $this->_refundMode = true;
        parent::__construct($this->_order);
    }
    
    protected function _generateItems()
    {
        $data = array();
        $refundTotal = 0;
        $storeId = $this->_creditmemo->getStoreId();
        $useBaseCurrency = $this->useBaseCurrency($storeId);

        $date = $this->_creditmemo->getCreatedAt();
        $utcDate = gmdate("Y-m-d\TH:i:s\Z", $date);

        foreach ($this->_creditmemo->getAllItems() as $item) {
            /* @var $item Mage_Sales_Model_Order_Creditmemo_Item */
            $useBaseCurrency = $this->useBaseCurrency($storeId);
            if ($rowTotal = $useBaseCurrency ? $item->getBaseRowTotalInclTax() : $item->getRowTotalInclTax()) {
                /* @var $item Mage_Sales_Model_Order_Creditmemo_Item */

                if($useBaseCurrency){
                    $discountAmount = $item->getBaseDiscountAmount();
                }else{
                    $discountAmount = $item->getDiscountAmount();
                }
                $data[] = array(
                    'item'          => $item->getSku(),
                    'price'         => $this->_formatPrice($useBaseCurrency ? $item->getBasePriceInclTax() : $item->getPriceInclTax()),
                    'order'         => $this->_order->getIncrementId(),
                    'timestamp'     => $utcDate,
                    'customer'      => $this->_getCustomerId(),
                    'quantity'      => $item->getQty(),
                    'f_c_sales_amount'=> $this->_formatPrice(-($rowTotal - $discountAmount)),
                    'i_website_id'    => $this->_getConfig()->getWebsiteId(),
                );
                $refundTotal += $rowTotal;
            }
        }

        if ($useBaseCurrency ? (float)$this->_creditmemo->getBaseAdjustment() : (float)$this->_creditmemo->getAdjustment()) {
            $data[] = array(
                    'item'          => 0,
                    'price'         => $this->_formatPrice($useBaseCurrency ? $this->_creditmemo->getBaseAdjustment() : $this->_creditmemo->getAdjustment()),
                    'order'         => $this->_order->getIncrementId(),
                    'timestamp'     => $this->_creditmemo->getCreatedAt(),
                    'customer'      => $this->_getCustomerId(),
                    'quantity'      => 1,
                    'f_c_sales_amount'=> $this->_formatPrice($useBaseCurrency ? -$this->_creditmemo->getBaseAdjustment() : -$this->_creditmemo->getAdjustment()),
                    'i_website_id'    => $this->_getConfig()->getWebsiteId(),
                );
        }

        return $data;
    }
}