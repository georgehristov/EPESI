<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Calculated extends Utils_RecordBrowser_Recordset_Field {
	
	public static function typeLabel() {
		return __('Calculated');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'order' => $this->isStored(),
				'quickjump' => $this->isStored(),
				'search' => $this->isStored(),
		]);
	}
	
	public function isRequiredPossible() {
		return false;
	}	
	
	public function processAdd($values) {
		return $this->isStored()? $values: false;
	}
	
	public function isStored() {
		return preg_match('/^[a-z]+(\([0-9]+\))?$/i',$this->getParam())!==0;
	}
	
    public function getSqlOrder($direction) {
    	$param = explode('::', $this->getParam());
    	if (isset($param[1]) && $param[1] != '') {
    		$tab2 = $param[0];
    		$cols = explode('|', $param[1]);
    		$first_col = $cols[0];
    		$first_col = explode('/', $first_col);
    		$data_col = isset($first_col[1]) ? $this->getFieldId($first_col[1]) : $this->getId();
    		$field_id2 = Utils_RecordBrowserCommon::get_field_id($first_col[0]);
    		$val = '(SELECT rdt.f_'.$field_id2.' FROM '.$this->getTab().'_data_1 AS rd LEFT JOIN '.$tab2.'_data_1 AS rdt ON rdt.id=rd.f_'.$data_col.' WHERE '.$this->getRecordset()->getTabAlias().'.id=rd.id)';
    	} else {
    		$val = $this->getQueryId();
    	}
    	return ' ' . $val . ' ' . $direction;
    }
    
    public function getQuerySection(Utils_RecordBrowser_Recordset_Query_Crits_Single $crit) {
    	if (!$this->getParam()) return Utils_RecordBrowser_Recordset_Query_Section::create('false');
    	
    	$field = $this->getQueryId();
    	$operator = $crit->getSqlOperator();
    	$value = $crit->getSqlValue();

        $vals = [];
        if (DB::is_postgresql()) $field .= '::varchar';
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $sql = "$field $operator %s AND $field IS NOT NULL";
            $vals[] = $value;
        }
        
        return Utils_RecordBrowser_Recordset_Query_Section::create($sql, $vals);
    }
    
    public function handleCritsRawSql($field, $operator, $value) {
    	if (!$this->getParam()) return ['false', []];
    	
    	return [$this->getQueryId() . " $operator $value", []];
    }   
    
    public static function getAjaxTooltip($opts) {
    	return __('This field is not editable');
    }
    
    public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
    	$ret = '';
    	if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
    		$ret = $record[$desc['id']];
    		
    		if (!$nolink && isset($record['id']) && $record['id'])
    			$ret = Utils_RecordBrowserCommon::record_link_open_tag_r($tab, $record) . $ret . Utils_RecordBrowserCommon::record_link_close_tag();
    	}
    	
    	return $ret;
    }
    
    public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
    	if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
    		return;
    		
    	$form->addElement('static', $field, $label);
    	if (!is_array($rb_obj->record))
    		$values = $rb_obj->custom_defaults;
    	else {
    		$values = $rb_obj->record;
    		if (is_array($rb_obj->custom_defaults))
    			$values = $values + $rb_obj->custom_defaults;
    		}
    	$val = isset($values[$field]) ?
    	Utils_RecordBrowserCommon::get_val($desc->getTab(), $field, $values, true, $desc)
    		: '';
    	if (!$val)
    		$val = '[' . __('formula') . ']';
    	$record_id = $rb_obj->record['id']?? null;
    	
    	$form->setDefaults([$field => '<div class="static_field" id="' . Utils_RecordBrowserCommon::get_calculated_id($desc->getTab(), $field, $record_id) . '">' . $val . '</div>']);
    }   
}
