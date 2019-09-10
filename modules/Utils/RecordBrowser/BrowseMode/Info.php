<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Info extends Utils_RecordBrowser_BrowseMode {
	protected static $key = 'info';
	protected static $label = 'Info';
	
	public function recordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		return $mode != 'add'? '<a ' . Utils_TooltipCommon::open_tag_attrs($record->getInfoTooltip()).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>': '';
	}
}



