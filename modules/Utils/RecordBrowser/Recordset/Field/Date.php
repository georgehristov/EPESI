<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Date extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Date');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'quickjump' => true,
				'search_type' => 'datepicker',
				'wrapmode' => !$recordBrowser->addInTableEnabled()? 'nowrap': false,
				'width' => $recordBrowser->addInTableEnabled()? 100: 50
		]);
	}
	
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
            $sql = "($field $operator %D $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('Enter the date in your selected format') . '<br />' .
      		__('Click on the text field to bring up a popup Calendar that allows you to pick the date') . '<br />' .
    		__('Click again on the text field to close popup Calendar');
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$desc['id']], false, true, false);
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    		
  		$form->addElement('datepicker', $field, $label, ['id' => $field]);
   		if ($mode !== 'add')
   			$form->setDefaults([$field => $default]);
    }   
}
