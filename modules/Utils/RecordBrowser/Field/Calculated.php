<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Calculated extends Utils_RecordBrowser_Field_Instance {
	
	public function isRequiredPossible() {
		return false;
	}	
	
	public function isOrderPossible() {
		return $this->getParam() != ''? $this->name: false;
	}
	
	public function getQuickjump($advanced = false) {
		return preg_match('/^[a-z]+(\([0-9]+\))?$/i',$this->getParam())!==0;
	}
	
	public function isSearchPossible($advanced = false) {
		return preg_match('/^[a-z]+(\([0-9]+\))?$/i',$this->getParam())!==0;
	}
	
	public function prepareSqlValue(& $valueprepareSqlValue) {
		if (preg_match('/^[a-z]+(\([0-9]+\))?$/i', $this['param'])===0) return false;
		return true;
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
    
    public static function getAjaxTooltip($opts) {
    	return __('This field is not editable');
    }
}
