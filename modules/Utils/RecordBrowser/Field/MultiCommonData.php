<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_MultiCommonData extends Utils_RecordBrowser_Field_CommonData {
	protected $multiselect = true;

    public static function decodeParam($param) {
    	if (is_array($param)) return $param;

    	list($order, $array_id) = explode('::', $param);

    	$order = Utils_CommonDataCommon::validate_order($order);

    	return array(
    			'single_tab'=>'__COMMON__',
    			'select_tabs'=>['__COMMON__'],
    			'array_id'=>$array_id,
    			'order'=>$order,
    			'order_by_key'=>$order //backward compatibility, deprecated
       	);
    }
    
    public function getSqlOrder($direction, $tab_alias='') {
    	$field_sql_id = $this->getSqlId($tab_alias);
    	$sort_order = $this['select']['order'];
	    $ret = false;
	    if ($sort_order == 'position' || $sort_order == 'value') {
	    	$sort_field = ($sort_order == 'position')? 'position': 'value';
	    	$parent_id = Utils_CommonDataCommon::get_id($this['select']['array_id']);
	    	if ($parent_id) {
		    	$ret = " (SELECT $sort_field FROM utils_commondata_tree AS uct WHERE uct.parent_id=$parent_id AND uct.akey=$field_sql_id) " . $direction;
		    }
	    }
 
	    return $ret?: ' ' . $field_sql_id . ' ' . $direction; // key or if position or value failed
    }    

    public function isOrderable() {
    	return false;
    }
    
    public function getQuickjump($advanced = false) {
    	return (!is_array($this->param) || strpos($this->param['array_id'],':')===false);
    }
    
    public function isSearchable($advanced = false) {
    	return (!is_array($this->param) || strpos($this->param['array_id'],':')===false);
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
