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
	
}



