<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_LongText extends Utils_RecordBrowser_Field_Instance {
	    
    public function format($text) {
    	$ret = htmlspecialchars($text);
		$ret = str_replace("\n",'<br>',$ret);
		$ret = Utils_BBCodeCommon::parse($ret);
        return $ret;
    }
    
    public function getQuickjump($advanced = false) {
    	return $advanced;
    }
    
    public function isSearchPossible($advanced = false) {
    	return $advanced;
    }
    
    public static function encodeValue($value) {
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
}
