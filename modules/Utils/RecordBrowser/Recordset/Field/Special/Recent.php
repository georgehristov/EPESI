<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Recent extends Utils_RecordBrowser_Recordset_Field {
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'recent',
				'field' => _M('Recent'),
				'type' => 'recent',
				'active' => true,
				'visible' => false,
				'export' => true,
				'processing_order' => -600,
		];
	}
	
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
	
	public function getArrayId() {
		return ':' . $this->getId();
	}
	
	public function processGet($values, $options = []) {
		return [];
	}
	
	public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit) {
		$operator = $crit->getSQLOperator();
		
		$sql_null = stripos($operator, '!') === false? 'NOT': '';
		
		$sql = "recent.user_id IS $sql_null NULL";
		
		return $this->getRecordset()->createQuery("EXISTS (SELECT 1 FROM {$this->getTab()}_recent AS recent WHERE recent.{$this->getTab()}_id={$this->getRecordset()->getDataTableAlias()}.id AND recent.user_id=%d AND $sql)", [Acl::get_user()]);
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
