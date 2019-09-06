<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Favourites extends Utils_RecordBrowser_BrowseMode_Controller {
	protected static $key = 'favorites';
	protected static $label = 'Favourites';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getProperty(self::$key);
	}
	
	public function crits() {
		return [':fav' => true];
	}
	
	public function columns() {
		return [
				[
						'name' => '&nbsp;',
						'width' => '24px',
						'attrs' => 'class="Utils_RecordBrowser__favs"',
						'position' => -10,
						'order' => ':Fav',
						'cell_callback' => [__CLASS__, 'getTableCell']
				]
		];
	}
	
	public static function getTableCell($record, $column, $options = []) {
		static $favs;
		
		$favs = $favs?? $record->getRecordset()->getUserFavouriteRecords();
		
		return Utils_RecordBrowserCommon::get_fav_button($record->getTab(), $record[':id'], in_array($record[':id'], $favs));
	}
	
}



