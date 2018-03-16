<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Date extends Utils_RecordBrowser_Field_Instance {
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$label = $this->getTooltip($this->getLabel());
       
        $form->addElement('datepicker', $field, $label, ['id' => $field]);
        if ($mode !== 'add')
            $form->setDefaults([$field => $default]);
    }
    
    public function defaultDisplay($record, $nolink=false) {
    	$ret = '';
    	if (isset($record[$this->getId()]) && $record[$this->getId()]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$this->getId()], false, true, false);
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
            $sql = "($field $operator %D $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }
    
    public function getStyle($add_in_table_enabled = false) {
    	return array(
    			'wrap'=>$add_in_table_enabled,
    			'width'=>$add_in_table_enabled? 100: 50
    	);
    }
    
    public function getQuickjump($advanced = false) {
    	return true;
    }

    public function getSearchType($advanced = false) {
    	return 'datepicker';
    }
}
