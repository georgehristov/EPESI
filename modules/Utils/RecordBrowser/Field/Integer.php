<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Integer extends Utils_RecordBrowser_Field_Instance {
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$label = $this->getTooltip($this->getLabel());
       
        $form->addElement('text', $field, $label, ['id' => $field]);
        $form->addRule($field, __('Only integer numbers are allowed.'), 'regex', '/^\-?[0-9]*$/');
        if ($mode !== 'add')
            $form->setDefaults([$field => $default]);
    }
        
    public function handleCrits($operator, $value, $tab_alias='') {
    	$field = $this->getSqlId($tab_alias);
    	 
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %d AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }
    
    public function isSearchable($advanced = false) {
    	return false;
    }
}
