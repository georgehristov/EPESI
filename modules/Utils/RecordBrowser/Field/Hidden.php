<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Hidden extends Utils_RecordBrowser_Field_Instance {
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	$field = $this->getId();

    	$form->addElement('hidden', $field);
        $form->setDefaults([$field => $default]);
    }    

    public function isOrderable() {
    	return false;
    }
}
