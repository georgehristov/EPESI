<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Checkbox extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Checkbox');
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
    
    public static function encodeValue($value) {
    	return $value? 1: 0;
    }
    
    public static function createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	return isset($rb_obj->display_callback_table[$desc->getId()])? parent::createQFfieldStatic($form, $mode, $default, $rb_obj): false;
    }
    
    public function handleCrits($operator, $value, $tab_alias='') {
    	$field = $this->getSqlId($tab_alias);
    
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
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
        return array($sql, array($value ? true : false));
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
