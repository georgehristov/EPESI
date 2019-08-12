<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Integer extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeKey() {
		return 'integer';
	}
	
	public static function typeLabel() {
		return _M('Integer');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'search' => false,
		]);
	}
	
	public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit)
	{
		if ($crit->getValue()->isRawSql()) {
			return $this->getRawSQLQuerySection($crit);
		}
		
		$field = $this->getQueryId();
		$operator = $crit->getSQLOperator();
		$value = $crit->getSQLValue();
		
		if ($operator == DB::like()) {
			if (DB::is_postgresql()) $field .= '::varchar';
			return $this->getRecordset()->createQuery("$field $operator %s", [$value]);
		}
		$vals = [];
		if ($value === '' || $value === null || $value === false) {
			$sql = "$field IS NULL";
		} else {
			$sql = "$field $operator %d AND $field IS NOT NULL";
			$vals[] = $value;
		}

		return $this->getRecordset()->createQuery($sql, $vals);
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
