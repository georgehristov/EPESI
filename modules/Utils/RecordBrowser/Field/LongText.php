<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_LongText extends Utils_RecordBrowser_Field_Instance {
	
    public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
    	if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
    	
    	$field = $this->getId();
    	$label = $this->getTooltip($this->getLabel());
       
        $form->addElement('textarea', $field, $label, ['id' => $field]);
        if ($mode !== 'add')
            $form->setDefaults([$field => $default]);
    }
    
    public function defaultDisplay($record, $nolink=false) {
    	$ret = '';
    	if (isset($record[$this->getId()]) && $record[$this->getId()]!=='') {
    		$ret = self::format($record[$this->getId()]);
    	}
    	 
    	return $ret;
    }
    
    public function format($text) {
    	$ret = htmlspecialchars($text);
		$ret = str_replace("\n",'<br>',$ret);
		$ret = Utils_BBCodeCommon::parse($ret);
        return $ret;
    }
    
    public function getQuickjump($advanced = false) {
    	return $advanced;
    }
    
    public function isSearchable($advanced = false) {
    	return $advanced;
    }
    
    public static function encodeValue($value) {
    	return Utils_BBCodeCommon::optimize($value);
    }
}
