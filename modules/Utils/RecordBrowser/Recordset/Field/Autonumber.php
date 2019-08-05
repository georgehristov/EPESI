<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Autonumber extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Autonumber');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'quickjump' => true,
		]);
	}
	
    public static function encodeParam($param) {
    	return implode('__', [$param['prefix'], $param['pad_length'], $param['pad_mask']]);
    }
    
    public static function decodeParam($param) {
    	if(is_array($param)) return $param;
    	
    	$parsed = explode('__', $param, 4);
    	if (!is_array($parsed) || count($parsed) != 3)
    		trigger_error("Not well formed autonumber parameter: $param", E_USER_ERROR);
    	
    	return array_combine(['prefix', 'pad_length', 'pad_mask'], $parsed);
    }
    
    public function processAdded($values) {
    	$values[$this->getId()] = self::formatStr($this['param'], $values['id']);
    	
    	Utils_RecordBrowserCommon::update_record($this->getTab(), $values['id'], [$this->getId() => $values[$this->getId()]], false, null, true);
    	
    	return $values;
    }
    
    protected static function formatStr($param, $id = null) {
    	$param = self::decodeParam($param);
    	if ($id === null)
    		$param['pad_mask'] = '?';
    	return $param['prefix'] . str_pad($id, $param['pad_length'], $param['pad_mask'], STR_PAD_LEFT);
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('This field is not editable');
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;

    	$param = $desc->getParam();
    		
    	$value = $default ?: self::formatStr($param);
    	$form->addElement('static', $field, $label);
    	$record_id = $rb_obj->record['id'] ?? null;
    	$field_id = Utils_RecordBrowserCommon::get_calculated_id($desc->getTab(), $field, $record_id);
    	$val = '<div class="static_field" id="' . $field_id . '">' . $value . '</div>';
    	$form->setDefaults([$field => $val]);
    }    
}
