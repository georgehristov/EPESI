<?php

class Utils_RecordBrowser_Recordset_Record implements ArrayAccess {

    /** @var Utils_RecordBrowser_Recordset */
    protected $recordset;
    protected $values = [];
    
    /**
     * Create object of record.
     * To perform any operation during object construction
     * please override init() function. It's called at the end of __construct
     *
     * @param Utils_RecordBrowser_Recordset|string $recordset Recordset object
     * @param array $array data of record
     */
    public static function create($recordset, $values) {
    	$recordset = Utils_RecordBrowser_Recordset::create($recordset);
    	
    	if (is_object($values)) return $values;
    	
    	if (is_numeric($values)) return $recordset->getRecord($values);
    	
    	return new static($recordset, $values);
    }
    /**
     * Create object of record.
     * To perform any operation during object construction
     * please override init() function. It's called at the end of __construct
     * 
     * @param Utils_RecordBrowser_Recordset $recordset Recordset object
     * @param array $array data of record
     */
    public final function __construct($recordset, array $values) {
    	
    	$this->recordset = Utils_RecordBrowser_Recordset::create($recordset);

        foreach ($values as $property => $value) {
            $this[self::getFieldId($property)] = $value;
        }       

        $this->init();
    }

    /**
     * Called at the end of object construction. Override to do something with
     * object immediately after creation. Eg. create some calculated property.
     */
    public function init() {
        
    }

    /**
     * Get associated recordset object
     * @return Utils_RecordBrowser_Recordset
     */
    public function getRecordset() {
        return $this->recordset;
    }
    
    public function getId() {
        return $this[':id'];
    }
    
    /**
     * @param Utils_RecordBrowser_Recordset_Field $field
     * 
     * @return string
     */
    public function getValue($field, $nolink) {
    	return $this->getRecordset()->getField($field)->display($this, $nolink);
    }
    
    public function getUserAccess($action, $admin = false) {
    	return Utils_RecordBrowser_Recordset_Access::create($this->getRecordset(), $action, $this)->getUserAccess($admin);
    }
    
    public function process($mode, $cloned = null) {
    	$modified = $this->getRecordset()->process($this->toArray(), $mode, $cloned);
    	
    	if ($modified === false) return false;
    	
    	foreach ($modified?: [] as $key => $value) {
    		$this[$key] = $value;
    	}
    	
    	return $this;
    }

    protected static function getFieldId($offset) {
    	if ($offset instanceof Utils_RecordBrowser_Recordset_Field) {
    		$offset = $offset->getId();
    	}
    	
    	//keep the special field prefix
    	$prefix = $offset[0] == ':'? ':': '';
    	
    	$ret = Utils_RecordBrowserCommon::get_field_id($offset);
    	
    	if ($prefix) {
    		$ret[0] = $prefix;
    	}
    	
    	return $ret;
    }
    
    public function validate($crits)
    {
    	return Utils_RecordBrowser_Crits::create($crits)->validate($this);
    }

    /**
     * Get array of all properties - including id, author, active and creation date
     * @return array
     */
    public function toArray() {
    	return $this->values;
    }

    /**
     * Get only values of record - exclude internal and special properties
     * @return array
     */
    private function getValues() {
        return array_filter($this->toArray(), function ($value, $key) {
        	return !self::isSpecialProperty($key);
        });
    }

    private static function isSpecialProperty($property) {
        return $property[0] == ':';
    }

    public function save() {
        if (!$recordset = $this->getRecordset()) {
        	trigger_error('Trying to save record that was not linked to proper recordset', E_USER_ERROR);
        }
        
        if (!$this->getId()) {
            $rec = $recordset->addRecord($this->getValues());
            if ($rec === null)
                return false;
            
            $this[':id'] = $rec[':id'];
            $this[':active'] = $rec[':active'];
            $this[':created_by'] = $rec[':created_by'];
            $this[':created_on'] = $rec[':created_on'];
            
            return true;
        }
                
        return $recordset->updateRecord($this[':id'], $this->getValues());
    }

    public function delete() {
        return $this->setActive(false);
    }

    public function restore() {
        return $this->setActive();
    }

    public function setActive($state = true) {
        $state = (boolean) $state;
        
        $this[':active'] = $state;
        
        return $this->getRecordset()->set_active($this[':id'], $state);
    }
    
    public function isActive() {
    	return $this[':active']?? true;
    }

    public function clone_data() {
        $c = clone $this;
        
        $c[':id'] = $c[':created_by'] = $c[':created_on'] = null;
        
        return $c;
    }

    public function create_default_linked_label($nolink = false, $table_name = true) {
        return $this->getRecordset()->create_default_linked_label($this->__records_id, $nolink, $table_name);
    }

    /**
     * Create link to record with specific text.
     * @param string $text Html to display as link
     * @param bool $nolink Do not create link
     * @param string $action Link to specific action. 'view' or 'edit'.
     * @return string html string with link
     */
    public function record_link($text, $nolink = false, $action = 'view') {
    	return $this->getRecordset()->record_link($this->__records_id, $text, $nolink, $action);
    }

    /**
     * Get field string representation - display callback gets called.
     * @param string $field Field id, e.g. 'first_name'
     * @param bool $nolink Do not create link
     * @return string String representation of field value
     */
    public function get_val($field, $nolink = false) {
    	return $this->getRecordset()->get_val($field, $this, $nolink);
    }

    /**
     * Get HTML formatted record's info. Record has to exist in DB.
     * It has to be saved first, when you're creating new record.
     * @return string Html with record info
     */
    public function get_html_record_info() {
        if (!$this->__records_id)
            trigger_error("get_html_record_info may be called only for saved records", E_USER_ERROR);
        
        return $this->getRecordset()->get_html_record_info($this->__records_id);
    }

    public function new_history($old_value) {
        if (!$this->__records_id)
            trigger_error("new_history may be called only for saved records", E_USER_ERROR);
        
        return $this->getRecordset()->new_record_history($this->__records_id,$old_value);
    }

    // ArrayAccess interface members

    public function offsetExists($offset) {    	
        $offset = self::getFieldId($offset);
        
        return array_key_exists($offset, $this->values) || array_key_exists(':' . $offset, $this->values);
    }

    public function offsetGet($offset) {
    	$offset = self::getFieldId($offset);
    	
    	//access for special fields using direct id
    	$offset = array_key_exists($offset, $this->values)? $offset: ':' . $offset;
    	
    	return $this->values[$offset]?? null;
    }

    public function offsetSet($offset, $value) {
    	$offset = self::getFieldId($offset);

        $this->values[$offset] = $value;
    }

    public function offsetUnset($offset) {
    	$offset = self::getFieldId($offset);
    	
        unset($this->values[$offset]);
    }
    
    public function __get($offset) {
    	return $this->offsetGet($offset);
    }
    
    public function __isset($offset) {
    	return $this->offsetExists($offset);
    }
}

?>