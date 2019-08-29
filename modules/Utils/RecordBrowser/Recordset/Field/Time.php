<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Time extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeKey() {
		return 'time';
	}
	
	public static function typeLabel() {
		return _M('Time');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'search' => false,
				'wrapmode' => !$recordBrowser->addInTableEnabled()? 'nowrap': false,
				'width' => $recordBrowser->addInTableEnabled()? 100: 50
		]);
	}
	
    public function handleCrits($field, $operator, $value) {
    	$field = $this->getQueryId();
    	 
    	$vals = [];
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $field = "CAST($field as time)";
            $sql = "$field $operator %s";
            $vals[] = $value;
        }
        return [$sql, $vals];
    }

    public static function getAjaxTooltip($opts) {
    	return __('Enter the time using select elements') . '<br />' . 
    		__('You can change 12/24-hour format in Control Panel, Regional Settings');
    } 
    
    public static function defaultStyle() {
    	return 'time';
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']])) {
    		$ret = $record[$desc['id']] !== '' && $record[$desc['id']] !== false
    		? Base_RegionalSettingsCommon::time2reg($record[$desc['id']], 'without_seconds', false)
    		: '---';
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    	
    	$time_format = Base_RegionalSettingsCommon::time_12h() ? 'h:i a' : 'H:i';
    	$lang_code = Base_LangCommon::get_lang_code();
    	$minute_increment = 5;
    	if ($desc['param']) {
    		$minute_increment = $desc['param'];
    	}
    	$form->addElement('timestamp', $field, $label, array('date' => false, 'format' => $time_format, 'optionIncrement' => array('i' => $minute_increment), 'language' => $lang_code, 'id' => $field));
    	if ($mode !== 'add' && $default)
    		$form->setDefaults(array($field => $default));
    }   
    
    public function queryBuilderFilters($opts = []) {
    	return [
    			[
    					'id' => $this->getId(),
    					'field' => $this->getId(),
    					'label' => $this->getLabel(),
    					'type' => 'time'
    			]
    	];
    }
}
