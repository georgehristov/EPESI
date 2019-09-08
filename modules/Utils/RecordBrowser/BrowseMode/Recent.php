<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Recent extends Utils_RecordBrowser_BrowseMode_Controller {
	protected static $key = 'recent';
	protected static $label = 'Recent';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getProperty(self::$key);
	}
	
	public function order() {
		return [':Visited_on' => 'DESC'];
	}
	
	public function crits() {
		return [':Recent' => true];
	}
	
	public function recordInfo($record) {
		return '<b>' . __('Visited on: %s', [$record['visited_on']]) . '</b><br>';
	}
	
	public function process($values, $mode, $tab) {
		switch ($mode) {
			case 'view':
			case 'edit':
			case 'added':
				if ($user = Acl::get_user()) {
					Utils_RecordBrowserCommon::add_recent_entry($tab, $user, $values[':id']);
				}
				break;
			case 'deleted':
				DB::Execute('DELETE FROM ' . $tab . '_recent WHERE ' . $tab . '_id = %d', [$values[':id']]);
				break;
		}
		
		return $values;
	}
}



