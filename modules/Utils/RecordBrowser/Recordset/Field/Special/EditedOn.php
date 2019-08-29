<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_EditedOn extends Utils_RecordBrowser_Recordset_Field {
	protected $id = 'edited_on';
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		$value = $record[$desc['id']];
		
		return Utils_RecordBrowser_Recordset_Field_Date::getDateValues()[$value]?? Base_RegionalSettingsCommon::time2reg($value);
	}
	
	public function getName() {
		return _M('Edited on');
	}
	
	public function validate(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits, $value) {
		$critsCheck = clone $crits;
		
		$crit_value = Base_RegionalSettingsCommon::reg2time($critsCheck->getValue()->getValue(), false);
		
		$critsCheck->getValue()->setValue(date('Y-m-d H:i:s', $crit_value));
		
		return parent::validate($critsCheck, $value);
	}
	
	public function getSqlId() {
		return false;
	}
	
	public function getSqlType() {
		return false;
	}
	
	public function getSqlOrder($direction) {
		return ' (CASE WHEN (SELECT 
								MAX(edited_on) 
							FROM ' . 
								$this->getTab().'_edit_history 
							WHERE ' . 
								$this->getTab().'_id=' . $this->getRecordset()->getDataTableAlias().'.id) 
				IS NOT NULL THEN (SELECT 
									MAX(edited_on) 
								FROM ' .
									$this->getTab().'_edit_history 
								WHERE ' . 
									$this->getTab().'_id='.$this->getRecordset()->getDataTableAlias().'.id) 
				ELSE ' . $this->getRecordset()->getDataTableAlias() . '.created_on END) ' . $direction;
	}
	
	public function processGet($values, $options = []) {
		return [];
	}
}
