<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Timestamp extends Utils_RecordBrowser_Field_Instance {
	
    public function handleCrits($operator, $value, $tab_alias='') {
    	$field = $this->getSqlId($tab_alias);
    	 
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $value = Base_RegionalSettingsCommon::reg2time($value, false);
            $sql = "($field $operator %T $null_part)";
            $vals[] = $value;
        }
        return [$sql, $vals];
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
    	return __('Enter the date in your selected format and the time using select elements') . '<br />' .
    		__('Click on the text field to bring up a popup Calendar that allows you to pick the date') . '<br />' .
    		__('Click again on the text field to close popup Calendar') . '<br />' .
    		__('You can change 12/24-hour format in Control Panel, Regional Settings');
    }
}
