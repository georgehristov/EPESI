<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Text extends Utils_RecordBrowser_Field_Instance {
	
	public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
		if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$maxlength = $this->getParam();    	
    	$label = $this->getTooltip($this->getLabel(), $maxlength);

    	$form->addElement('text', $field, $label, ['id' => $field, 'maxlength' => $maxlength]);
    	$form->addRule($field, __('Maximum length for this field is %s characters.', [$maxlength]), 'maxlength', $maxlength);
    	if ($mode !== 'add')
    		$form->setDefaults([$field => $default]);
    }
    
    public function getQuickjump($advanced = false) {
    	return true;
    }
        
    public static function decodeValue($value, $htmlspecialchars = true) {
    	return $htmlspecialchars? htmlspecialchars($value): $value;
    }
}
