<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_CreatedOn extends Utils_RecordBrowser_Recordset_Field {
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'created_on',
				'field' => _M('Created on'),
				'type' => 'created_on',
				'active' => true,
				'export' => true,
				'processing_order' => -700,
		];
	}
	
	public static function getDateValues() {
		return [
				'-1 year' => __('1 year back'),
				'-6 months' => __('6 months back'),
				'-3 months' => __('3 months back'),
				'-2 months' => __('2 months back'),
				'-1 month' => __('1 month back'),
				'-2 weeks' => __('2 weeks back'),
				'-1 week' => __('1 week back'),
				'-6 days' => __('6 days back'),
				'-5 days' => __('5 days back'),
				'-4 days' => __('4 days back'),
				'-3 days' => __('3 days back'),
				'-2 days' => __('2 days back'),
				'-1 days' => __('1 days back'),
				'today' => __('current day'),
				'+1 days' => __('1 days forward'),
				'+2 days' => __('2 days forward'),
				'+3 days' => __('3 days forward'),
				'+4 days' => __('4 days forward'),
				'+5 days' => __('5 days forward'),
				'+6 days' => __('6 days forward'),
				'+1 week' => __('1 week forward'),
				'+2 weeks' => __('2 weeks forward'),
				'+1 month' => __('1 month forward'),
				'+2 months' => __('2 months forward'),
				'+3 months' => __('3 months forward'),
				'+6 months' => __('6 months forward'),
				'+1 year' => __('1 year forward')
		];	
	}	
	
	public function defaultValue($mode) {
		return $mode == 'add'? date('Y-m-d H:i:s'): null;
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		$value = $record[$desc['id']];
		
		return self::getDateValues()[$value]?? Base_RegionalSettingsCommon::time2reg($value);
	}
	
	public function validate(Utils_RecordBrowser_Recordset_Record $record, Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits) {
		$critsCheck = clone $crits;
		
		$crit_value = Base_RegionalSettingsCommon::reg2time($critsCheck->getValue()->getValue(), false);
		
		$critsCheck->getValue()->setValue(date('Y-m-d H:i:s', $crit_value));
		
		return parent::validate($record, $critsCheck);
	}
	
	public function getSqlId() {
		return $this->getId();
	}
	
	public function getSqlType() {
		return '%T';
	}
	
	public function getArrayId() {
		return ':' . $this->getId();
	}
}
