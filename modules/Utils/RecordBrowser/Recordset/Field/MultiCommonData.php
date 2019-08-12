<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_MultiCommonData extends Utils_RecordBrowser_Recordset_Field_CommonData {
	protected $multiselect = true;

	public static function typeKey() {
		return 'multicommondata';
	}
	
	public static function typeLabel() {
		return _M('Multi Commondata');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'order' => false,
				'width' => 100,
		]);
	}
	
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
    
    public function getSqlOrder($direction) {
    	$field_sql_id = $this->getQueryId();
    	$sort_order = $this['param']['order'];
	    $ret = false;
	    if ($sort_order == 'position' || $sort_order == 'value') {
	    	$sort_field = ($sort_order == 'position')? 'position': 'value';
	    	$parent_id = Utils_CommonDataCommon::get_id($this['param']['array_id']);
	    	if ($parent_id) {
		    	$ret = " (SELECT $sort_field FROM utils_commondata_tree AS uct WHERE uct.parent_id=$parent_id AND uct.akey=$field_sql_id) " . $direction;
		    }
	    }
 
	    return $ret?: ' ' . $field_sql_id . ' ' . $direction; // key or if position or value failed
    }    

    public function defaultValue($mode) {
    	return [];
    }
    
    public static function decodeValue($value, $options = []) {
    	return Utils_RecordBrowserCommon::decode_multi($value);
    }
        
    public static function encodeValue($value, $options = []) {
    	return Utils_RecordBrowserCommon::encode_multi($value);
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	return Utils_RecordBrowser_Recordset_Field_Select::defaultDisplayCallback($record, $nolink, $desc, $tab);
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;

    	$param = $desc->getParam();
    		
    	if (empty($param['array_id']))
    		trigger_error("Commondata array id not set for field: $field", E_USER_ERROR);
    			
    	$select_options = Utils_CommonDataCommon::get_translated_tree($param['array_id'], $param['order']);
    	if (!is_array($select_options))
    		$select_options = [];
    				
    	$form->addElement('multiselect', $field, $label, $select_options, ['id' => $field]);
    			
    	if ($mode !== 'add')
    		$form->setDefaults([$field => $default]);
    }   
}
