<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Date extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeKey() {
		return 'date';
	}
	
	public static function typeLabel() {
		return _M('Date');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'quickjump' => true,
				'search_type' => 'datepicker',
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
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $value = Base_RegionalSettingsCommon::reg2time($value, false);
            $sql = "($field $operator %D $null_part)";
            $vals[] = $value;
        }
        return [$sql, $vals];
    }
    
    public static function getAjaxTooltip($opts) {
    	return __('Enter the date in your selected format') . '<br />' .
      		__('Click on the text field to bring up a popup Calendar that allows you to pick the date') . '<br />' .
    		__('Click again on the text field to close popup Calendar');
    }
    
    public function isDescriptive() {
    	return $this->isVisible() && $this->isRequired();
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = Base_RegionalSettingsCommon::time2reg($record[$desc['id']], false, true, false);
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    		
  		$form->addElement('datepicker', $field, $label, ['id' => $field]);
   		if ($mode !== 'add')
   			$form->setDefaults([$field => $default]);
    }
    
    public function validate(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits, $value) {
    	$critsCheck = clone $crits;
    	
    	$crit_value = Base_RegionalSettingsCommon::reg2time($critsCheck->getValue()->getValue(), false);
    	
    	$critsCheck->getValue()->setValue(date('Y-m-d', $crit_value));
    	
    	return parent::validate($critsCheck, $value);
    }
    
    public function queryBuilderFilters($opts = []) {
    	return [
    			[
    					'id' => $this->getId(),
    					'field' => $this->getId(),
    					'label' => $this->getLabel(),
    					'type' => 'date',
    					'plugin' => 'datepicker',
    					'plugin_config' => ['dateFormat' => 'yy-mm-dd', 'constrainInput' => false],
    			],
    			[
    					'id' => $this->getId() . '_relative',
    					'field' => $this->getId(),
    					'label' => $this->getLabel() . ' (' . __('relative') . ')',
    					'type' => 'date',
    					'input' => 'select',
    					'values' => self::getDateValues()
    			]
    	];
    }
    
    public static function getDateValues() {
    	return [
    			'-1 year' => __('1 year back'),
    			'-6 months' => __('6 months back'),
    			'-3 months' => __('3 months back'),
    			'-2 months' => __('2 months back'),
    			'-1 month' => __('1 month back'),
    			'-2 weeks' => __('2 weeks back'),
    			'-1 week' => __('1 week back'),
    			'-6 days' => __('6 days back'),
    			'-5 days' => __('5 days back'),
    			'-4 days' => __('4 days back'),
    			'-3 days' => __('3 days back'),
    			'-2 days' => __('2 days back'),
    			'-1 days' => __('1 days back'),
    			'today' => __('current day'),
    			'+1 days' => __('1 days forward'),
    			'+2 days' => __('2 days forward'),
    			'+3 days' => __('3 days forward'),
    			'+4 days' => __('4 days forward'),
    			'+5 days' => __('5 days forward'),
    			'+6 days' => __('6 days forward'),
    			'+1 week' => __('1 week forward'),
    			'+2 weeks' => __('2 weeks forward'),
    			'+1 month' => __('1 month forward'),
    			'+2 months' => __('2 months forward'),
    			'+3 months' => __('3 months forward'),
    			'+6 months' => __('6 months forward'),
    			'+1 year' => __('1 year forward')
    	];
    }	
}
