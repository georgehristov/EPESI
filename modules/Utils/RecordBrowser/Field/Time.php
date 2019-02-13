<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Time extends Utils_RecordBrowser_Field_Instance {
	
    public function default_display($record, $nolink=false) {
    	$ret = '';
    	if (isset($record[$this->id]) && $record[$this->id]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$this->id], 'without_seconds', false);
    	}
    	 
    	return $ret;
    }
    
    public function handleCrits($operator, $value, $tab_alias='') {
    	$field = $this->getSqlId($tab_alias);
    	 
    	$vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $field = "CAST($field as time)";
            $sql = "$field $operator %s";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    public function get_style($add_in_table_enabled = false) {
    	return array(
    			'wrap'=>$add_in_table_enabled,
    			'width'=>$add_in_table_enabled? 100: 50
    	);
    }
    
    public function isSearchPossible($advanced = false) {
    	return false;
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('Enter the time using select elements') . '<br />' . 
    		__('You can change 12/24-hour format in Control Panel, Regional Settings');
    }    
}
