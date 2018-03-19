<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_LongText extends Utils_RecordBrowser_Field_Instance {
	    
    public function format($text) {
    	$ret = htmlspecialchars($text);
		$ret = str_replace("\n",'<br>',$ret);
		$ret = Utils_BBCodeCommon::parse($ret);
        return $ret;
    }
    
    public function getQuickjump($advanced = false) {
    	return $advanced;
    }
    
    public function isSearchable($advanced = false) {
    	return $advanced;
    }
    
    public static function encodeValue($value) {
    	return Utils_BBCodeCommon::optimize($value);
    }
}
