<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Currency extends Utils_RecordBrowser_Field_Instance {
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$label = $this->getTooltip($this->getLabel());
    	$desc = $this;    	
        
        $form->addElement('currency', $field, $label, (isset($desc['param']) && is_array($desc['param']))?$desc['param']:array(), array('id' => $field));
        if ($mode !== 'add')
            $form->setDefaults(array($field => $default));
        // set element value to persist currency over soft submit
        if ($form->isSubmitted() && $form->exportValue('submited') == false) {
            $default = $form->exportValue($field);
            $form->getElement($field)->setValue($default);
        }
    }
    
    public function defaultDisplay($record, $nolink=false) {
    	$ret = '';
    	if (isset($record[$this->getId()]) && $record[$this->getId()]!=='') {
    		$val = Utils_CurrencyFieldCommon::get_values($record[$this->getId()]);
            $ret = Utils_CurrencyFieldCommon::format($val[0], $val[1]);
    	}
    	 
    	return $ret;
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
    
    public function getQuickjump($advanced = false) {
    	return true;
    }
    
    public function isSearchable($advanced = false) {
    	return false;
    }
}
