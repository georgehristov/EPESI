<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Recent extends Utils_RecordBrowser_Recordset_Field {
	protected $id = 'recent';
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowser_Recordset_Field_Checkbox::defaultDisplayCallback($record, $nolink, $desc, $tab);
	}
	
	public function getName() {
		return _M('Recently viewed');
	}
	
	public function getSqlId() {
		return false;
	}
	
	public function getSqlType() {
		return false;
	}
	
	public function processGet($values, $options = []) {
		return [];
	}
	
	public function queryBuilderFilters($opts = []) {
		return [
				[
						'id' => ':Recent',
						'field' => ':Recent',
						'label' => __('Recent'),
						'type' => 'boolean',
						'input' => 'select',
						'values' => ['1' => __('Yes'), '0' => __('No')]
				]
		];
	}
}
