<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_MultiCommonData extends Utils_RecordBrowser_Field_CommonData {
	protected $multiselect = true;
	
	public function __construct($desc = null) {
		parent::__construct($desc);
		
		//backward compatibility for creating form elements
		$this->type = 'multiselect';
	}
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$param = $this->getParam();
    	$label = $this->getTooltip($this->getLabel(), $param['array_id']);
       
        if (empty($param['array_id']))
        		trigger_error("Commondata array id not set for field: $field", E_USER_ERROR);
        
        $select_options = Utils_CommonDataCommon::get_translated_tree($param['array_id'], $param['order']);
        if (!is_array($select_options))
        	$select_options = array();
            
        $form->addElement('multiselect', $field, $label, $select_options, ['id' => $field]);

        if ($mode !== 'add')
            $form->setDefaults([$field => $default]);
    }
    
    public function defaultDisplay($record, $nolink=false) {
    	$ret = '---';
    	
    	$desc = $this;
    	$param = $this->param;
    	if (isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$val = $record[$desc['id']];
    		$commondata_sep = '/';
    		if ((is_array($val) && empty($val)) || !$param['array_id']) return $ret;
    	
    		if (!is_array($val)) $val = array($val);
    	
    		$ret = '';
    		foreach ($val as $v) {
    			$ret .= ($ret!=='')? '<br>': '';
    			
		    	$array_id = $param['array_id'];
		    	$path = explode('/', $v);
		    	$tooltip = '';
		    	$res = '';
		    	if (count($path) > 1) {
		    		$res .= Utils_CommonDataCommon::get_value($array_id . '/' . $path[0], true);
		    		if (count($path) > 2) {
		    			$res .= $commondata_sep . '...';
		    			$tooltip = '';
		    			$full_path = $array_id;
		    			foreach ($path as $w) {
		    				$full_path .= '/' . $w;
		    				$tooltip .= ($tooltip? $commondata_sep: '').Utils_CommonDataCommon::get_value($full_path, true);
		    			}
		    			}
		    			$res .= $commondata_sep;
		    		}
		   		$label = Utils_CommonDataCommon::get_value($array_id . '/' . $v, true);
		   		if (!$label) continue;
		   		$res .= $label;    				
		   		$res = Utils_RecordBrowserCommon::no_wrap($res);
		   		if ($tooltip) $res = '<span '.Utils_TooltipCommon::open_tag_attrs($tooltip, false) . '>' . $res . '</span>';
    		
		   		$ret .= $res;
    		}
    	}
    	return $ret;
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
