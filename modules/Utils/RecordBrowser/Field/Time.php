<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Time extends Utils_RecordBrowser_Field_Instance {
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$label = $this->getTooltip($this->getLabel());
    	$desc = $this;
       
        $time_format = Base_RegionalSettingsCommon::time_12h() ? 'h:i a' : 'H:i';
        $lang_code = Base_LangCommon::get_lang_code();
        $minute_increment = 5;
        if ($desc['param']) {
            $minute_increment = $desc['param'];
        }
        $form->addElement('timestamp', $field, $label, array('date' => false, 'format' => $time_format, 'optionIncrement' => array('i' => $minute_increment), 'language' => $lang_code, 'id' => $field));
        if ($mode !== 'add' && $default)
            $form->setDefaults(array($field => $default));
    }
    
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
    
    public function isSearchable($advanced = false) {
    	return false;
    }
}
