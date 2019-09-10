<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Watchdog extends Utils_RecordBrowser_BrowseMode {
	protected static $key = 'watchdog';
	protected static $label = 'Watchdog';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return Utils_WatchdogCommon::category_exists($recordset->getTab());;
	}
	
	public function crits() {
		return [':Sub' => true];
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
			case 'restored':
				if (isset($values[':edit_id'])) {
					Utils_WatchdogCommon::new_event($tab, $values[':id'], 'R_' . $values[':edit_id']);
				}
				break;
			case 'deleted':
				if (isset($values[':edit_id'])) {
					Utils_WatchdogCommon::new_event($tab, $values[':id'], 'D_' . $values[':edit_id']);
				}
				break;
			case 'edited':
				if (isset($values[':edit_id'])) {
					Utils_WatchdogCommon::new_event($tab, $values[':id'], 'E_' . $values[':edit_id']);
				}
				break;
		}
		
		return $values;
	}
	
	public function recordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		if (! $this->isAvailable($record->getRecordset())) return;
		
		if (in_array($mode, ['add', 'history', 'browse'])) return;
		
		return Utils_WatchdogCommon::get_change_subscription_icon($record->getTab(), $record[':id']);
	}
}



