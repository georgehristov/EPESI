<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Clipboard extends Utils_RecordBrowser_BrowseMode {
	protected static $key = 'clipboard';
	protected static $label = 'Clipboard';
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return $recordset->getClipboardPattern()? true: false;
	}
	
	public function recordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		if (! $this->isAvailable($record->getRecordset())) return;
		
		if (in_array($mode, ['add', 'history', 'browse'])) return;

		$text = $record->getClipboardText();
		
		//TODO: replace this with the new library for copying
		
		$module_dir = Utils_RecordBrowserCommon::Instance()->get_module_dir();
		load_js($module_dir . 'selecttext.js');
		/* remove all php new lines, replace <br>|<br/> to new lines and quote all special chars */
		$ftext = htmlspecialchars(preg_replace('#<[bB][rR]/?>#', "\n", str_replace("\n", '', $text)));
		$flash_copy = '<object width="60" height="20">'.
				'<param name="FlashVars" value="txtToCopy='.$ftext.'">'.
				'<param name="movie" value="'.$module_dir.'copyButton.swf">'.
				'<embed src="'.$module_dir.'copyButton.swf" flashvars="txtToCopy='.$ftext.'" width="60" height="20">'.
				'</embed>'.
				'</object>';
		$text = '<h3>'.__('Click Copy under the box or move mouse over box below to select text and hit Ctrl-c to copy it.').'</h3><div onmouseover="fnSelect(this)" style="border: 1px solid gray; margin: 15px; padding: 20px;">'.$text.'</div>'.$flash_copy;
		
		Libs_LeightboxCommon::display('clipboard', $text, __('Copy'));
		
		return '<a '.Utils_TooltipCommon::open_tag_attrs(__('Click to export values to copy')).' '.Libs_LeightboxCommon::get_open_href('clipboard').'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','clipboard.png').'" /></a>';
	}
}



