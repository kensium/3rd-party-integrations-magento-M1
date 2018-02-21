<?php
set_time_limit(0);
//ini_set('memory_limit','4096M');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'app/Mage.php';
Mage::app('admin');

try {
	Mage::getSingleton('emarsys_suite2/api_order')->export();
	echo "Orders exported successfully\n";
} catch (Exception $e) {
	echo "ERROR: " . $e->getMessage();
}
