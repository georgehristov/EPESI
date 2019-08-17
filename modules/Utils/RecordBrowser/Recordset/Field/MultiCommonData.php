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

    public function defaultValue() {
    	return [];
    }
    
    public static function decodeValue($value, $options = []) {
    	return Utils_RecordBrowserCommon::decode_multi($value);
    }
        
    public static function encodeValue($value, $options = []) {
    	return Utils_RecordBrowserCommon::encode_multi($value);
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$values = $record[$desc['id']];
    	
    	$commondata_sep = '/';
    	
    	$array_id = $desc['param']['array_id'];
    	    	
    	$ret = [];
    	foreach (is_array($values)? $values: [$values] as $value) {
    		$tooltip = '';
    		$res = '';
    		
    		$path = explode('/', $value);
	    	
	    	if (count($path) > 1) {
	    		$res .= Utils_CommonDataCommon::get_value($array_id . '/' . $path[0], true);
	    		
	    		if (count($path) > 2) {
	    			$res .= $commondata_sep . '...';
	    			$tooltip = '';
	    			$full_path = $array_id;
	    			foreach ($path as $w) {
	    				$full_path .= '/' . $w;
	    				$tooltip .= ($tooltip? $commondata_sep: '') . Utils_CommonDataCommon::get_value($full_path, true);
	    			}
	    		}
	    		
	    		$res .= $commondata_sep;
	    	}
	    	
	    	if (!$label = Utils_CommonDataCommon::get_value($array_id . '/' . $value, true)) continue;
	    	
	    	$res .= $label;
	    	
	    	$res = Utils_RecordBrowserCommon::no_wrap($res);
	    	
	    	if ($tooltip) $res = '<span '.Utils_TooltipCommon::open_tag_attrs($tooltip, false) . '>' . $res . '</span>';
	    	
	    	$ret[] = $res;
    	}
    	
    	return $ret? implode('<br />', $ret): '---';
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
    
    public function processEdit($values, $existing = []) {
    	$values[$this->getId()] = is_array($values[$this->getId()])? $values[$this->getId()]: [$values[$this->getId()]];
    	
    	//TODO: Georgi Hristov does not take repeating values into consideration
    	if (array_diff($existing[$this->getId()], $values[$this->getId()]) === array_diff($values[$this->getId()], $existing[$this->getId()])) {
    		return false;
    	}
    	
    	return $values;
    }
}
