<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Currency extends Utils_RecordBrowser_Field_Instance {
	
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
