<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Float extends Utils_RecordBrowser_Field_Instance {
	
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
            $sql = "$field $operator %f AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }
    
    public function isSearchPossible($advanced = false) {
    	return false;
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('Enter a numeric value in the text field');
    }
}
