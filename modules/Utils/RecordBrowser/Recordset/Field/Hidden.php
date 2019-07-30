<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Hidden extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Hidden');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'order' => false,
		]);
	}
   
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
   		$form->addElement('hidden', $field);
   		$form->setDefaults([$field => $default]);
    }   
}
