<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Calculated extends Utils_RecordBrowser_Field_Instance {
	
	public function isRequiredPossible() {
		return false;
	}	
	
	public function isOrderable() {
		return $this->getParam() != ''? $this->name: false;
	}
	
	public function getQuickjump($advanced = false) {
		return preg_match('/^[a-z]+(\([0-9]+\))?$/i',$this->getParam())!==0;
	}
	
	public function isSearchable($advanced = false) {
		return preg_match('/^[a-z]+(\([0-9]+\))?$/i',$this->getParam())!==0;
	}
	
	public function prepareSqlValue(& $valueprepareSqlValue) {
		if (preg_match('/^[a-z]+(\([0-9]+\))?$/i', $this['param'])===0) return false;
		return true;
	}
	
	public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
		if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
		
		$field = $this->getId();
		$label = $this->getTooltip($this->getLabel());
		$desc = $this;		
	
		$form->addElement('static', $field, $label);
		if (!is_array($rb_obj->record))
			$values = $rb_obj->custom_defaults;
		else {
			$values = $rb_obj->record;
			if (is_array($rb_obj->custom_defaults))
				$values = $values + $rb_obj->custom_defaults;
		}
		$val = isset($values[$field]) ?
		Utils_RecordBrowserCommon::get_val($rb_obj->tab, $field, $values, true, $desc)
		: '';
		if (!$val)
			$val = '[' . __('formula') . ']';
		$record_id = isset($rb_obj->record['id']) ? $rb_obj->record['id'] : null;
		$form->setDefaults(array($field => '<div class="static_field" id="' . Utils_RecordBrowserCommon::get_calculated_id($rb_obj->tab, $field, $record_id) . '">' . $val . '</div>'));
	}
	
    public function getSqlOrder($direction, $tab_alias='') {
    	$param = explode('::', $this->getParam());
    	if (isset($param[1]) && $param[1] != '') {
    		$tab2 = $param[0];
    		$cols = explode('|', $param[1]);
    		$first_col = $cols[0];
    		$first_col = explode('/', $first_col);
    		$data_col = isset($first_col[1]) ? $this->getFieldId($first_col[1]) : $this->getId();
    		$field_id2 = Utils_RecordBrowserCommon::get_field_id($first_col[0]);
    		$val = '(SELECT rdt.f_'.$field_id2.' FROM '.$this->getTab().'_data_1 AS rd LEFT JOIN '.$tab2.'_data_1 AS rdt ON rdt.id=rd.f_'.$data_col.' WHERE '.$tab_alias.'.id=rd.id)';
    	} else {
    		$val = $this->getSqlId($tab_alias);
    	}
    	return ' ' . $val . ' ' . $direction;
    }
    
    public function handleCrits($operator, $value, $tab_alias='') {
        if (!$this->getParam()) return array('false', array());

        $field = $this->getSqlId($tab_alias);
        $vals = array();
        if (DB::is_postgresql()) $field .= '::varchar';
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $sql = "$field $operator %s AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }
    
    public function handleCritsRawSql($operator, $value, $tab_alias='') {
    	if (!$this->getParam()) return array('false', array());
    	
    	return array($this->getSqlId($tab_alias) . " $operator $value", array());
    }   
}
