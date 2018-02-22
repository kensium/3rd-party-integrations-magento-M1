<?php

class Emarsys_Suite2_Model_System_Config_Maxrecord
{
    /**
     * Returns options array
     * 
     * @return array
     */
    public function toOptionArray()
    {
        $maxExportRecord = Mage::getStoreConfig('emarsys_suite2_smartinsight/api/max_records_per_export_option');
        $maxExportRecordArray = explode(',', $maxExportRecord);
        $val = array();
        $result = array(array('value' => 0, 'label' => "All Records"));
        foreach ($maxExportRecordArray as $item) {
            $val['value'] = $item;
            $val['label'] = $item;

            $result[] = $val;
        }

        return $result;
    }
}
