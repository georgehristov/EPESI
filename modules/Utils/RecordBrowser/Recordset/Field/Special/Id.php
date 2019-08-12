<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Id extends Utils_RecordBrowser_Recordset_Field {
	public static function typeKey() {
		return 'id';
	}
	
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'id',
				'field' => 'id',
				'type' => 'id',
				'active' => true,
				'export' => true,
				'processing_order' => -1000,
		];
	}
	
	public function processAdd($values) {
		return false;
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowserCommon::create_default_linked_label($tab, $record['id'], $nolink, false);
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
