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
	
	public function userSettings() {
		$ret = [];
		foreach (Utils_RecordBrowserCommon::list_installed_recordsets() as $tab => $caption) {
			$recordset = Utils_RecordBrowser_Recordset::create($tab);
			
			if (! $recordset->getUserAccess('browse') || ! $this->isAvailable($recordset)) continue;

			$ret[] = [
					'name' => $tab . '_auto_fav',
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
						'name' => 'header_auto_fav',
						'label' => __('Automatically add to favorites records created by me'),
						'type' => 'header'
				]
		], $ret): [];
	}
	
	public function process($values, $mode, $tab) {
		switch ($mode) {
			case 'added':
				if (Base_User_SettingsCommon::get('Utils_RecordBrowser', $tab . '_auto_fav')) {
					DB::Execute("INSERT INTO {$tab}_favorite (user_id, {$tab}_id) VALUES (%d, %d)", [Acl::get_user(), $values[':id']]);
				}
			break;
		}
		
		return $values;
	}
	
}


