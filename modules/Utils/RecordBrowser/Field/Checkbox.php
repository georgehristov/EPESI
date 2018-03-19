<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Checkbox extends Utils_RecordBrowser_Field_Instance {
	
    public function isEmpty($record) {
    	 return false;
    }
    
    public static function encodeValue($value) {
    	return $value? 1: 0;
    }
    
    public function createQFfieldStatic($form, $mode, $default, $rb_obj) {
    	return isset($rb_obj->display_callback_table[$this->getId()])? parent::createQFfieldStatic($form, $mode, $default, $rb_obj): false;
    }
    
    public function handleCrits($operator, $value, $tab_alias='') {
    	$field = $this->getSqlId($tab_alias);
    
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        if ($operator == '!=') {
            $sql = $value ?
                    "$field IS NULL OR $field!=%b" :
                    "$field IS NOT NULL AND $field!=%b";
        } else {
            $sql = $value ?
                    "$field IS NOT NULL AND $field=%b" :
                    "$field IS NULL OR $field=%b";
        }
        return array($sql, array($value ? true : false));
    }    

    public function getStyle($add_in_table_enabled = false) {
    	return array(
    			'wrap'=>false,
    			'width'=>50
    	);
    }
    
    public function isSearchable($advanced = false) {
    	return false;
    }
}
