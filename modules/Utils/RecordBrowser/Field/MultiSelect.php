<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_MultiSelect extends Utils_RecordBrowser_Field_Select {
	protected $multiselect = true;
	
	public function isOrderPossible() {
		return false;
	}	

	public function getQuickjump($advanced = false) {
		return true;
	}
	
	public function isSearchPossible($advanced = false){
		return true;
	}
	
	public function defaultValue() {
		return [];
	}
		
	public static function decodeValue($value, $htmlspecialchars = true) {
		return Utils_RecordBrowserCommon::decode_multi($value);
	}
		
	public static function encodeValue($value) {
		return Utils_RecordBrowserCommon::encode_multi($value);
	}
}
