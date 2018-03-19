<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Text extends Utils_RecordBrowser_Field_Instance {
	
    public function getQuickjump($advanced = false) {
    	return true;
    }
        
    public static function decodeValue($value, $htmlspecialchars = true) {
    	return $htmlspecialchars? htmlspecialchars($value): $value;
    }
}
