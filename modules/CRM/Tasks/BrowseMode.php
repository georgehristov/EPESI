<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Tasks_BrowseMode extends Utils_RecordBrowser_BrowseMode {
	protected static $key = 'task';
	protected static $label = 'Task';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getTab() == 'phonecall';
	}
	
	public function recordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		if (! $this->isAvailable($record->getRecordset())) return;
		
		if (in_array($mode, ['add', 'history', 'browse'])) return;
		
		$ret = [];
		
		$values = $record->toArray();
		$values['title'] = __('Follow-up') . ': '. $values['title'];
		$values['status'] = 0;
		$values['deadline'] = date('Y-m-d', strtotime('+1 day'));

		if (CRM_MeetingInstall::is_installed()) {
			$ret[] = '<a ' . Utils_TooltipCommon::open_tag_attrs(__('New Meeting')) . ' ' . Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', array(
					'title' => $values['title'],
					'permission' => $values['permission'],
					'priority' => $values['priority'],
					'description' => $values['description'],
					'date' => date('Y-m-d'),
					'time' => date('H:i:s'),
					'duration' => 3600,
					'employees' => $values['employees'],
					'customers' => $values['customers'],
					'status' => 0
			), 'none', false) . '><img border="0" src="' . Base_ThemeCommon::get_template_file('CRM_Calendar', 'icon-small.png') . '" /></a>';
		}
		
		$ret[] = '<a ' . Utils_TooltipCommon::open_tag_attrs(__('New Task')) . ' ' . Utils_RecordBrowserCommon::create_new_record_href('task', $values) . '><img border="0" src="' . Base_ThemeCommon::get_template_file('CRM_Tasks', 'icon-small.png') . '" /></a>';
		
		if (CRM_PhoneCallInstall::is_installed()) {
			$ret[] = '<a ' . Utils_TooltipCommon::open_tag_attrs(__('New Phonecall')) . ' ' . Utils_RecordBrowserCommon::create_new_record_href('phonecall', array(
					'subject' => $record['title'],
					'permission' => $record['permission'],
					'priority' => $record['priority'],
					'description' => $record['description'],
					'date_and_time' => date('Y-m-d H:i:s'),
					'employees' => $record['employees'],
					'customer' => reset($record['customers']),
					'status' => 0
			), 'none', false) . '><img border="0" src="' . Base_ThemeCommon::get_template_file('CRM_PhoneCall', 'icon-small.png') . '" /></a>';
		}
		$ret[] = $module->add_note_button($record->getToken());
		
		return $ret;
	}
}



