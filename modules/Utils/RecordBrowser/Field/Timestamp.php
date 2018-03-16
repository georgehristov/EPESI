<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Timestamp extends Utils_RecordBrowser_Field_Instance {
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$label = $this->getTooltip($this->getLabel());
    	$desc = $this;
       
        $f_param = array('id' => $field);
        if ($desc['param'])
            $f_param['optionIncrement'] = array('i' => $desc['param']);
        $form->addElement('timestamp', $field, $label, $f_param);
        static $rule_defined = false;
        if (!$rule_defined) {
            $form->registerRule('timestamp_required', 'callback', 'timestampRequired', __CLASS__);
            $rule_defined = true;
        }
        if (isset($desc['required']) && $desc['required'])
            $form->addRule($field, __('Field required'), 'timestamp_required');
        if ($mode !== 'add' && $default)
            $form->setDefaults(array($field => $default));
    }
    
    public static function timestampRequired($v) {
    	return $v['__datepicker'] !== '' && Base_RegionalSettingsCommon::reg2time($v['__datepicker'], false) !== false;
    }
    
    public function defaultDisplay($record, $nolink=false) {
    	$ret = '';
    	if (isset($record[$this->id]) && $record[$this->id]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$this->id], 'without_seconds');
    	}
    	 
    	return $ret;
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
            $sql = "($field $operator %T $null_part)";
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
