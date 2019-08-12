<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Active extends Utils_RecordBrowser_Recordset_Field {
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'active',
				'field' => _M('Active'),
				'type' => 'active',
				'active' => true,
				'export' => true,
				'processing_order' => -900,
		];
	}
	
	public function defaultValue($mode) {
		return 1;
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowser_Recordset_Field_Checkbox::defaultDisplayCallback($record, $nolink, $desc, $tab);
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
