<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Recent extends Utils_RecordBrowser_BrowseMode {
	protected static $key = 'recent';
	protected static $label = 'Recent';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getProperty('recent');
	}
	
	public function order() {
		return [':Visited_on' => 'DESC'];
	}
	
	public function crits() {
		return [':Recent' => true];
	}
	
	public function recordInfo(Utils_RecordBrowser_Recordset_Record $record) {
		$value = DB::GetOne("SELECT
								MAX(visited_on)
							FROM
								{$record->getTab()}_recent
							WHERE
								{$record->getTab()}_id=%d AND
								user_id=%d", [$record[':id'], Acl::get_user()]);
								
		return [
				_M('Visited on') . ':' => Utils_RecordBrowser_Recordset_Field_Date::getDateValues()[$value]?? Base_RegionalSettingsCommon::time2reg($value)
		];
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
			case 'destroyed':
				DB::Execute('DELETE FROM ' . $tab . '_recent WHERE ' . $tab . '_id = %d', [$values[':id']]);
				break;
		}
		
		return $values;
	}
	
	public function moduleSettings(Utils_RecordBrowser_Recordset $recordset) {
		$values = [
				'[' . __('Deactivate') . ']'
		];
		foreach ( [5, 10, 15, 20, 25] as $value ) {
			$values[$value] = __('%d Records', [$value]);
		}
		
		return [
				[
						'name' => 'recent',
						'label' => __('Recent'),
						'type' => 'select',
						'values' => $values,
						'default' => $this->isAvailable($recordset)? 1: 0
				]
		];
	}
}



