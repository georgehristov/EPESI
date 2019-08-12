<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_LongText extends Utils_RecordBrowser_Recordset_Field_Text {
	
	public static function typeKey() {
		return 'long text';
	}
	
	public static function typeLabel() {
		return _M('Long Text');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'quickjump' => $recordBrowser->getGenericBrowser()->is_adv_search_on(),
				'search' => $recordBrowser->getGenericBrowser()->is_adv_search_on(),
		]);
	}
	
    public function format($text) {
    	$ret = htmlspecialchars($text);
		$ret = str_replace("\n",'<br>',$ret);
		$ret = Utils_BBCodeCommon::parse($ret);
        return $ret;
    }
    
    public static function encodeValue($value, $options = []) {
    	return Utils_BBCodeCommon::optimize($value);
    }

    public static function getAjaxTooltip($opts) {
    	$example_text = __('Example text');
    	
    	return __('Enter the text in the text area') . '<br />' .
      		__('Maximum allowed length is %s characters', ['<b>400</b>']) . '<br/><br/>' .
      		__('BBCodes are supported:').'<br/>'.
	      	'[b]'.$example_text.'[/b] - <b>'.$example_text.'</b><br/>'.
	      	'[u]'.$example_text.'[/u] - <u>'.$example_text.'</u><br/>'.
	      	'[i]'.$example_text.'[/i] - <i>'.$example_text.'</i>';
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = Utils_RecordBrowserCommon::format_long_text($record[$desc['id']]);
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    		
   		$form->addElement('textarea', $field, $label, ['id' => $field]);
   		if ($mode !== 'add')
   			$form->setDefaults([$field => $default]);
    }
}
