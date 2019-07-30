<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Integer extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Integer');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'search' => false,
		]);
	}
	
    public function handleCrits($operator, $value, $tab_alias='') {
    	$field = $this->getSqlId($tab_alias);
    	 
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %d AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('Enter a numeric value in the text field');
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    		
   		$form->addElement('text', $field, $label, ['id' => $field]);
   		$form->addRule($field, __('Only integer numbers are allowed.'), 'regex', '/^\-?[0-9]*$/');
   		if ($mode !== 'add')
   			$form->setDefaults([$field => $default]);
    }   
}
