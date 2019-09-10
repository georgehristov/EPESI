<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_PhoneCall_BrowseMode extends Utils_RecordBrowser_BrowseMode {
	protected static $key = 'phonecall';
	protected static $label = 'Phone Call';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getTab() == 'phonecall';
	}
	
	public function recordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		if (! $this->isAvailable($record->getRecordset())) return;
		
		if (in_array($mode, ['add', 'history', 'browse'])) return;
		
		$ret = [];
		
		$values = $record->toArray();
		$values['date_and_time'] = date('Y-m-d H:i:s');
		$values['subject'] = __('Follow-up').': '.$values['subject'];
		$values['status'] = 0;
		$values['related'] = $record->getToken();

		if (CRM_MeetingInstall::is_installed()) {
			$ret[] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Meeting')).' '.Utils_RecordBrowserCommon::create_new_record_href('crm_meeting', [
					'title'       => $values['subject'],
					'permission'  => $values['permission'],
					'priority'    => $values['priority'],
					'description' => $values['description'],
					'date'        => date('Y-m-d'),
					'time'        => date('H:i:s'),
					'duration'    => 3600,
					'employees'   => $values['employees'],
					'customers'   => $values['customer'],
					'status'      => 0,
					'related'     => $values['related']
			], 'none', false).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Calendar','icon-small.png').'" /></a>';
		}
		
		if (CRM_TasksInstall::is_installed()) {
			$ret[] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Task')).' '.Utils_RecordBrowserCommon::create_new_record_href('task', [
					'title'       => $values['subject'],
					'permission'  => $values['permission'],
					'priority'    => $values['priority'],
					'description' => $values['description'],
					'employees'   => $values['employees'],
					'customers'   => $values['customer'],
					'status'      => 0,
					'deadline'    => date('Y-m-d', strtotime('+1 day')),
					'related'     => $values['related']
			]).'><img border="0" src="'.Base_ThemeCommon::get_template_file('CRM_Tasks','icon-small.png').'"></a>';
		}

		$ret[] = '<a ' . Utils_TooltipCommon::open_tag_attrs(__('New Phonecall')) . ' ' . Utils_RecordBrowserCommon::create_new_record_href('phonecall', $values, 'none', false) . '><img border="0" src="' . Base_ThemeCommon::get_template_file('CRM_PhoneCall', 'icon-small.png') . '"></a>';
		$ret[] = $module->add_note_button($record->getToken());
		
		return $ret;
	}
}



