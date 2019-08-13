<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_CommonData extends Utils_RecordBrowser_Recordset_Field {
	protected $multiselect = false;
	
	public static function typeKey() {
		return 'commondata';
	}
	
	public static function typeLabel() {
		return _M('Commondata');
	}
		
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'wrapmode' => 'nowrap',
				'width' => 50,
				'quickjump' => (!is_array($this->param) || strpos($this->param['array_id'],':')===false),
				'search' => (!is_array($this->param) || strpos($this->param['array_id'],':')===false),
		]);
	}
	
    public static function decodeParam($param) {
    	if (is_array($param)) return $param;
    	
    	$param = explode('__',$param);
    	if (isset($param[1])) {
    		$order = Utils_CommonDataCommon::validate_order($param[0]);
    		$array_id = $param[1];
    	} else {
    		$order = 'value';
    		$array_id = $param[0];
    	}
    	return [
    		'array_id'=>$array_id,
    		'order'=>$order,
    		'order_by_key'=>$order
       	];
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
    		 
    	return implode('__', [$order, $array_id]);
    }
    
    public function getSqlOrder($direction) {
    	$sort_order = $this['param']['order'];
	    $ret = false;
	    if ($sort_order == 'position' || $sort_order == 'value') {
	    	$sort_field = ($sort_order == 'position')? 'position': 'value';
	    	$parent_id = Utils_CommonDataCommon::get_id($this['param']['array_id']);
	    	if ($parent_id) {
		    	$ret = " (SELECT $sort_field FROM utils_commondata_tree AS uct WHERE uct.parent_id=$parent_id AND uct.akey={$this->getQueryId()}) " . $direction;
		    }
	    }
 
	    return $ret?: ' ' . $this->getQueryId() . ' ' . $direction; // key or if position or value failed
    }
        
    public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit) {
    	$field = $this->getQueryId();
    	$operator = $crit->getSqlOperator();
    	$value = $crit->getSqlValue();

    	if ($value === null || $value === false || $value === '') {
    		return $this->getRecordset()->createQuery("$field IS NULL OR $field=''");
    	}
    	
    	if ($crit->getKey()->getSubfield()) { // may be empty string for value lookup with field[]
    		$ret = Utils_CommonDataCommon::get_translated_array($this['select']['array_id']);
    		$val_regex = $operator == DB::like() ?
    		'/' . preg_quote($value, '/') . '/i' :
    		'/^' . preg_quote($value, '/') . '$/i';
    		$final_vals = array_keys(preg_grep($val_regex, $ret));
    		if ($operator == DB::like()) {
    			$operator = '=';
    		}
    	} else {
    		$final_vals = [$value];
    	}
    	
    	if ($this->multiselect) {
    		$operator = DB::like();
    	}

    	$sql = [];
    	$vals = [];
    	foreach ($final_vals as $val) {
    		$sql[] = "($field $operator %s AND $field IS NOT NULL)";
    		if ($this->multiselect) {
    			$val = "%\\_\\_{$val}\\_\\_%";
    		}
    		$vals[] = $val;
    	}
    	
    	return $this->getRecordset()->createQuery(implode(' OR ', $sql), $vals);
    }
    
    public function getAjaxTooltipOpts() {
    	return [
    			'param' => $this->getParam()
    	];
    }
    
    public static function getAjaxTooltip($opts) {
    	$ret = __('Select value');

    	if (isset($opts['param']['array_id']))
    		$ret .= ' '.__('from %s table', ['<b>'.str_replace('_', '/', $opts['param']['array_id']).'</b>']);
    	
    	return $ret;
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$arr = explode('::', $desc['param']['array_id']);
    		$path = array_shift($arr);
    		foreach($arr as $v) $path .= '/' . $record[Utils_RecordBrowserCommon::get_field_id($v)];
    		$path .= '/' . $record[$desc['id']];
    		$ret = Utils_CommonDataCommon::get_value($path, true);
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;

    	$param = $desc->getParam();
    		
    	$param = explode('::', $desc['param']['array_id']);
    	foreach ($param as $k => $v) {
    		if (!$k) continue;
    		
    		$param[$k] = self::getFieldId($v);
    	}
    	
    	$form->addElement($desc['type'], $field, $label, $param, ['empty_option' => true, 'order' => $desc['param']['order']], ['id' => $field]);
    	
    	if ($mode !== 'add')
    		$form->setDefaults([$field => $default]);
    }   
}
