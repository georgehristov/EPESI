<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Text extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Text');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'quickjump' => true,
		]);
	}
	  
    public static function decodeValue($value, $htmlspecialchars = true) {
    	return $htmlspecialchars? htmlspecialchars($value): $value;
    }
    
    public function getAjaxTooltipOpts() {
    	return [
    			'maxlength' => $this->getParam()
    	];
    }
    
    public static function getAjaxTooltip($opts) {
    	$ret = __('Enter the text in the text field');
    	
    	if (isset($opts['maxlength']) && is_numeric($opts['maxlength'])) 
    		$ret .= '<br />'.__('Maximum allowed length is %s characters', ['<b>'.$opts['maxlength'].'</b>']);
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;

    	$maxlength = $desc->getParam();
    	$label = $desc->getTooltip($desc->getLabel(), $maxlength);
    		
    	$form->addElement('text', $field, $label, ['id' => $field, 'maxlength' => $maxlength]);
    	$form->addRule($field, __('Maximum length for this field is %s characters.', [$maxlength]), 'maxlength', $maxlength);
    	if ($mode !== 'add')
    		$form->setDefaults([$field => $default]);
    }   
}
