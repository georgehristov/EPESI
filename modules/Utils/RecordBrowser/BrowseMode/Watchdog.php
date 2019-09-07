<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Watchdog extends Utils_RecordBrowser_BrowseMode_Controller {
	protected static $key = 'watchdog';
	protected static $label = 'Watchdog';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return Utils_WatchdogCommon::category_exists($recordset->getTab());;
	}
	
	public function crits() {
		return [':Sub' => true];
	}
	
	public function columns() {
		return [
				[
						'name' => '&nbsp;',
						'width' => '24px',
						'attrs' => 'class="Utils_RecordBrowser__watchdog"',
						'position' => -5,
						'cell_callback' => [__CLASS__, 'getTableCell']
				]
		];
	}
	
	public static function getTableCell ($record, $column, $options = []) {
		return Utils_WatchdogCommon::get_change_subscription_icon($record->getTab(), $record[':id']);
	}
	
	public function userSettings() {
		$ret = [];

		foreach (Utils_RecordBrowserCommon::list_installed_recordsets() as $tab => $caption) {
			$recordset = Utils_RecordBrowser_Recordset::create($tab);
			
			if (! $recordset->getUserAccess('browse') || ! $this->isAvailable($recordset)) continue;
			
			$ret[] = [
					'name' => $tab . '_auto_subs',
					'label' => $caption,
					'type' => 'select',
					'values' => [
							__('Disabled'),
							__('Enabled')
					],
					'default' => 0
			];
		}
		
		return $ret? array_merge([
				[
						'name' => 'header_auto_subscriptions',
						'label' => __('Automatically watch records created by me'),
						'type' => 'header'
				]
		], $ret): [];
	}
	
	public function process($values, $mode, $tab) {
		switch ($mode) {
			case 'display':
				if ($values[':id']) {
					Utils_WatchdogCommon::notified($tab, $values[':id']);
				}
				break;
			case 'added':
				if (Base_User_SettingsCommon::get('Utils_RecordBrowser', $tab . '_auto_subs')) {
					Utils_WatchdogCommon::subscribe($tab, $values[':id']);
				}
				Utils_WatchdogCommon::new_event($tab, $values[':id'], 'C');
				break;
		}
		
		return $values;
	}
}



