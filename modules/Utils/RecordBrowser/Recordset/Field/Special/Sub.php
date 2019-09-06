<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Sub extends Utils_RecordBrowser_Recordset_Field {
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'sub',
				'field' => _M('Sub'),
				'caption' => _M('Subscription status'),
				'type' => 'fav',
				'active' => true,
				'visible' => false,
				'export' => true,
				'processing_order' => -600,
		];
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowser_Recordset_Field_Checkbox::defaultDisplayCallback($record, $nolink, $desc, $tab);
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
		
		$sql = "sub.internal_id IS $sql_null NULL";

		return $this->getRecordset()->createQuery("EXISTS (SELECT 1 FROM utils_watchdog_subscription AS sub WHERE sub.internal_id={$this->getRecordset()->getDataTableAlias()}.id AND sub.category_id=%s AND sub.user_id=%d AND $sql)", [Utils_WatchdogCommon::get_category_id($this->getTab()), Acl::get_user()]);
	}
	
	public function queryBuilderFilters($opts = []) {
		if (! Utils_WatchdogCommon::get_category_id($this->getTab())) return;
		
		return [
				[
						'id' => ':Sub',
						'field' => ':Sub',
						'label' => __('Subscribed'),
						'type' => 'boolean',
						'input' => 'select',
						'values' => ['1' => __('Yes'), '0' => __('No')]
				]
		];
	}
}
