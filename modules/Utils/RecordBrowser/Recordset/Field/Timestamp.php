<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Timestamp extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Timestamp');
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
    	 
    	if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return ["$field $operator %s", [$value]];
        }
        $vals = [];
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $value = Base_RegionalSettingsCommon::reg2time($value, false);
            $sql = "($field $operator %T $null_part)";
            $vals[] = $value;
        }
        return [$sql, $vals];
    }

    public static function getAjaxTooltip($opts) {
    	return __('Enter the date in your selected format and the time using select elements') . '<br />' .
    		__('Click on the text field to bring up a popup Calendar that allows you to pick the date') . '<br />' .
    		__('Click again on the text field to close popup Calendar') . '<br />' .
    		__('You can change 12/24-hour format in Control Panel, Regional Settings');
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$desc['id']], 'without_seconds');
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;

		$f_param = array(
				'id' => $field
		);
		if ($desc['param']) $f_param['optionIncrement'] = array(
				'i' => $desc['param']
		);
		$form->addElement('timestamp', $field, $label, $f_param);
		static $rule_defined = false;
		if (! $rule_defined) {
			$form->registerRule('timestamp_required', 'callback', 'timestamp_required', __CLASS__);
			$rule_defined = true;
		}
		if (isset($desc['required']) && $desc['required']) $form->addRule($field, __('Field required'), 'timestamp_required');
		if ($mode !== 'add' && $default) $form->setDefaults(array(
				$field => $default
		));
	}   
}
