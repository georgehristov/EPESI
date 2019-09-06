<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Recent extends Utils_RecordBrowser_BrowseMode_Controller {
	protected static $key = 'recent';
	protected static $label = 'Recent';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getProperty(self::$key);
	}
	
	public function getOrder() {
		return [':Visited_on' => 'DESC'];
	}
	
	public function crits() {
		return [':Recent' => true];
	}
}



