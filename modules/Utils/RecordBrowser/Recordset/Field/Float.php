<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Float extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeKey() {
		return 'float';
	}
	
	public static function typeLabel() {
		return _M('Float');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'search' => false,
		]);
	}
	
    public function handleCrits($field, $operator, $value) {
    	$field = $this->getQueryId();
    	 
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return ["$field $operator %s", [$value]];
        }
        $vals = [];
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %f AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return [$sql, $vals];
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('Enter a numeric value in the text field');
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    		
    	$form->addElement('text', $field, $label, ['id' => $field]);
    	$form->addRule($field, __('Only numbers are allowed.'), 'numeric');
    	if ($mode !== 'add')
    		$form->setDefaults([$field => $default]);
    }   
}
