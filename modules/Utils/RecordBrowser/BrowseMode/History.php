<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_History extends Utils_RecordBrowser_BrowseMode {
	protected static $key = 'history';
	protected static $label = 'History';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getProperty('full_history');
	}
	
	public function recordInfo(Utils_RecordBrowser_Recordset_Record $record) {
		$info = DB::GetRow('SELECT
								edited_on,
								edited_by
							FROM ' .
				$record->getRecordset()->getTable('history') . '
							WHERE ' .
				$record->getTab() . '_id=%d
							ORDER BY edited_on DESC', [$record->getId()]);

		return array_filter([
				__('Edited by') . ':' => $info['edited_by']? Base_UserCommon::get_user_label($info['edited_by']): '',
				__('Edited on') . ':' => Base_RegionalSettingsCommon::time2reg($info['edited_on'])
		]);
	}
	
	public function process($values, $mode, $tab) {
		switch ($mode) {
			case 'view':
			case 'edit':
			case 'added':
				
				break;
			
		}
		
		return $values;
	}
	
	public function moduleSettings(Utils_RecordBrowser_Recordset $recordset) {
		
	}
	
	public function recordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		if (! $this->isAvailable($record->getRecordset())) return;
		
		if (in_array($mode, ['add', 'history', 'browse'])) return;
		
		$info = $record->getInfo();
		
		if ($info[':edited_on']) {
			return '<a '.Utils_TooltipCommon::open_tag_attrs(__('This record was never edited')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history_inactive.png').'" /></a>';
		}
		
		return '<a '.Utils_TooltipCommon::open_tag_attrs(__('Click to view edit history of currently displayed record')).' '.$module->create_callback_href([$module,'navigate'], ['view_edit_history', $record->getId()]).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history.png').'" /></a>';
	}
}



