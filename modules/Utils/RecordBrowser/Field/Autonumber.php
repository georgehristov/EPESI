<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Autonumber extends Utils_RecordBrowser_Field_Instance {
	
	public function getQuickjump($advanced = false) {
    	return true;
    }
    
    public static function encodeParam($param) {
    	return implode('__', [$param['prefix'], $param['pad_length'], $param['pad_mask']]);
    }
    
    public static function decodeParam($param) {
    	if(is_array($param)) return $param;
    	
    	$parsed = explode('__', $param, 4);
    	if (!is_array($parsed) || count($parsed) != 3)
    		trigger_error("Not well formed autonumber parameter: $param", E_USER_ERROR);
    	list($prefix, $pad_length, $pad_mask) = $parsed;
    	
    	return compact('prefix', 'pad_length', 'pad_mask');
    }
    
    public function processAddedValue($value, $record) {
    	$value = self::formatStr($this['param'], $record['id']);
    	Utils_RecordBrowserCommon::update_record($this->getTab(), $record['id'], [$this->getId() => $value], false, null, true);
    	
    	return $value;
    }
    
    protected static function formatStr($param, $id) {
    	$param = self::decodeParam($param);
    	if ($id === null)
    		$param['pad_mask'] = '?';
    	return $param['prefix'] . str_pad($id, $param['pad_length'], $param['pad_mask'], STR_PAD_LEFT);
    }
}
