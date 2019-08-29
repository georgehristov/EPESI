<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_CreatedOn extends Utils_RecordBrowser_Recordset_Field {
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'created_on',
				'field' => _M('Created on'),
				'type' => 'created_on',
				'active' => true,
				'visible' => false,
				'export' => true,
				'processing_order' => -700,
		];
	}
	
	public function defaultValue() {
		return date('Y-m-d H:i:s');
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		$value = $record[$desc['id']];
		
		return Utils_RecordBrowser_Recordset_Field_Date::getDateValues()[$value]?? Base_RegionalSettingsCommon::time2reg($value);
	}
	
	public function validate(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits, $value) {
		$critsCheck = clone $crits;
		
		$crit_value = Base_RegionalSettingsCommon::reg2time($critsCheck->getValue()->getValue(), false);
		
		$critsCheck->getValue()->setValue(date('Y-m-d H:i:s', $crit_value));
		
		return parent::validate($critsCheck, $value);
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
	
	/**
	 * Update the time of actual adding the record
	 * {@inheritDoc}
	 * @see Utils_RecordBrowser_Recordset_Field::processAdd()
	 */
	public function processAdd($values, $options = []) {
		$values[$this->getId()] =  $this->defaultValue();
		
		return $values;
	}
	
	public function queryBuilderFilters($opts = []) {
		return [
				[
						'id' => ':created_on',
						'field' => ':created_on',
						'label' => $this->getLabel(),
						'type' => 'datetime',
						'plugin' => 'datepicker',
						'plugin_config' => ['dateFormat' => 'yy-mm-dd', 'constrainInput' => false],
				],
				[
						'id' => $this->getId() . '_relative',
						'field' => $this->getId(),
						'label' => $this->getLabel() . ' (' . __('relative') . ')',
						'type' => 'date',
						'input' => 'select',
						'values' => Utils_RecordBrowser_Recordset_Field_Date::getDateValues()
				]
		];
	}
}
