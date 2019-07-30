<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Currency extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Currency');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'quickjump' => true,
				'search' => false,
		]);
	}
	
    public function getSqlOrder($direction, $tab_alias='') {
    	$field_sql_id = $this->getSqlId($tab_alias);
    	
    	if (DB::is_mysql()) {
	    	$field_sql_id = "CAST($field_sql_id as DECIMAL(64,5))";
	    } elseif (DB::is_postgresql()) {
	    	$field_sql_id = "CAST(COALESCE(NULLIF(split_part($field_sql_id, '__', 1),''),'0') as DECIMAL)";
	    }
        return ' ' . $field_sql_id . ' ' . $direction;
    }
    
    public function handleCrits($operator, $value, $tab_alias='') {
    	$field = $this->getSqlId($tab_alias);
    	 
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $field_as_int = DB::is_postgresql() ?
                "CAST(split_part($field, '__', 1) AS DECIMAL)" :
                "CAST($field AS DECIMAL(64,5))";
            $value_with_cast = DB::is_postgresql() ?
                "CAST(%s AS DECIMAL)" :
                "CAST(%s AS DECIMAL(64,5))";
            $sql = "($field_as_int $operator $value_with_cast $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('Enter the amount in text field and select currency');
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$val = Utils_CurrencyFieldCommon::get_values($record[$desc['id']]);
    		$ret = Utils_CurrencyFieldCommon::format($val[0], $val[1]);
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    		
    	$form->addElement('currency', $field, $label, (isset($desc['param']) && is_array($desc['param']))? $desc['param']:[], ['id' => $field]);
    	
    	if ($mode !== 'add')
    		$form->setDefaults([$field => $default]);
    	
    	// set element value to persist currency over soft submit
    	if ($form->isSubmitted() && $form->exportValue('submited') == false) {
    		$default = $form->exportValue($field);
    		$form->getElement($field)->setValue($default);
    	}
    }
}
