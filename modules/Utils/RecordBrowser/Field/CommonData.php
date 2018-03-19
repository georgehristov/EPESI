<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_CommonData extends Utils_RecordBrowser_Field_Instance {
	protected $multiselect = false;
	
    public static function decodeParam($param) {
    	$param = explode('__',$param);
    	if (isset($param[1])) {
    		$order = Utils_CommonDataCommon::validate_order($param[0]);
    		$array_id = $param[1];
    	} else {
    		$order = 'value';
    		$array_id = $param[0];
    	}
    	return array(
    		'array_id'=>$array_id,
    		'order'=>$order,
    		'order_by_key'=>$order
       	);
    }
    public static function encodeParam($param) {
    	if (!is_array($param))
    		$param = array($param);
    		 
    	$order = 'value';
    	if (isset($param['order']) || isset($param['order_by_key'])) {
    		$order = Utils_CommonDataCommon::validate_order(isset($param['order'])? $param['order']: $param['order_by_key']);
    			 
    		unset($param['order']);
    		unset($param['order_by_key']);
    	}
    
    	$array_id = implode('::', $param);
    		 
    	return implode('__', array($order, $array_id));
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
        
    public function handleCrits($operator, $value, $tab_alias='') {
    	list($field, $sub_field) = Utils_RecordBrowser_CritsSingle::parse_subfield($this->getSqlId($tab_alias));

    	if ($value === null || $value === false || $value === '') {
    		return array("$field IS NULL OR $field=''", array());
    	}
    	
    	if ($sub_field !== false) { // may be empty string for value lookup with field[]
    		$ret = Utils_CommonDataCommon::get_translated_array($this['select']['array_id']);
    		$val_regex = $operator == DB::like() ?
    		'/' . preg_quote($value, '/') . '/i' :
    		'/^' . preg_quote($value, '/') . '$/i';
    		$final_vals = array_keys(preg_grep($val_regex, $ret));
    		if ($operator == DB::like()) {
    			$operator = '=';
    		}
    	} else {
    		$final_vals = array($value);
    	}
    	
    	if ($this->multiselect) {
    		$operator = DB::like();
    	}
    	
    	$sql = array();
    	$vals = array();
    	foreach ($final_vals as $val) {
    		$sql[] = "($field $operator %s AND $field IS NOT NULL)";
    		if ($this->multiselect) {
    			$val = "%\\_\\_{$val}\\_\\_%";
    		}
    		$vals[] = $val;
    	}
    	$sql_str = implode(' OR ', $sql);
    	return array($sql_str, $vals);
    }
    
    public function handleCritsRawSql($operator, $value, $tab_alias='') {
    	list($field, ) = Utils_RecordBrowser_CritsSingle::parse_subfield($this->getSqlId($tab_alias));
    	
    	return array($field . " $operator $value", array());
    }

    public function getStyle($add_in_table_enabled = false) {
    	return array(
    			'wrap'=>false,
    			'width'=>50
    	);
    }   

    public function getQuickjump($advanced = false) {
    	return (!is_array($this->param) || strpos($this->param['array_id'],':')===false);
    }
    
    public function isSearchable($advanced = false) {
    	return (!is_array($this->param) || strpos($this->param['array_id'],':')===false);
    }
}
