<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Checkbox extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeKey() {
		return 'checkbox';
	}
	
	public static function typeLabel() {
		return _M('Checkbox');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'wrapmode' => 'nowrap',
				'width' => 50,
				'search' => false,
		]);
	}
	
    public function isEmpty($record) {
    	 return false;
    }
    
    public static function encodeValue($value, $options = []) {
    	return $value? 1: 0;
    }
    
    public function defaultValue($mode) {
    	return 0;
    }
    
    public static function createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	return isset($rb_obj->display_callback_table[$desc->getId()])? parent::createQFfieldStatic($form, $mode, $default, $rb_obj): false;
    }
    
    public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit) {
    	$field = $this->getQueryId();    	
    	$operator = $crit->getSqlOperator();
    	$value = $crit->getSqlValue();
    
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return $this->getRecordset()->createQuery("$field $operator %s", [$value]);
        }
        if ($operator == '!=') {
            $sql = $value ?
                    "$field IS NULL OR $field!=%b" :
                    "$field IS NOT NULL AND $field!=%b";
        } else {
            $sql = $value ?
                    "$field IS NOT NULL AND $field=%b" :
                    "$field IS NULL OR $field=%b";
        }
        
        return $this->getRecordset()->createQuery($sql, [$value ? true : false]);
    }   
    
    public static function getAjaxTooltip($opts) {
    	return __('Click to switch between checked/unchecked state');
    }
        
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && array_key_exists($desc['id'], $record)) {
    		$ret = $record[$desc['id']]? __('Yes'): __('No');
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    	
    	$el = $form->addElement('advcheckbox', $field, $label, '', ['id' => $field]);
    	$el->setValues(['0','1']);
    	
    	if ($mode !== 'add')
    		$form->setDefaults([$field => $default]);
    }
}
