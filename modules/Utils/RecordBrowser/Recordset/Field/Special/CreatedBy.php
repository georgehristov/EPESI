<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_CreatedBy extends Utils_RecordBrowser_Recordset_Field {
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'created_by',
				'field' => _M('Created by'),
				'type' => 'created_by',
				'active' => true,
				'visible' => false,
				'export' => true,
				'processing_order' => -800,
		];
	}
	
	public function defaultValue($mode) {
		return Acl::get_user();
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return is_numeric($value = $record[$desc['id']])? Base_UserCommon::get_user_login($value): $value;
	}
	
	public function getSqlId() {
		return $this->getId();
	}
	
	public function getSqlType() {
		return '%d';
	}
	
	public function getArrayId() {
		return ':' . $this->getId();
	}
}
