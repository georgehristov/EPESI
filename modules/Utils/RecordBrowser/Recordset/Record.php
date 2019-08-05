<?php

class Utils_RecordBrowser_Recordset_Record implements ArrayAccess {

    /** @var Utils_RecordBrowser_Recordset */
    protected $__recordset;
    protected $__id;

    /**
     * Is record visible to user or was deleted.
     * 
     * Readonly variable. Modifications of this variable will not be saved.
     * @var bool */
    public $_active;

    /**
     * Id of user that created record.
     * 
     * Readonly variable. Modifications of this variable will not be saved.
     * @var int */
    public $created_by;

    /**
     * Time and Date of records creation.
     * 
     * Readonly variable. Modifications of this variable will not be saved.
     * @var string formatted date */
    public $created_on;

    /**
     * Create object of record.
     * To perform any operation during object construction
     * please override init() function. It's called at the end of __construct
     * 
     * @param Utils_RecordBrowser_Recordset $recordset Recordset object
     * @param array $array data of record
     */
    public final function __construct(Utils_RecordBrowser_Recordset $recordset, array $values) {
        $this->__recordset = $recordset;
        if (isset($values[':active'])) {
        	$values['_active'] = $values[':active'];
        	unset($values[':active']);
        }
        foreach ($values as $property => $value) {
            $property = self::getFieldId($property);
            $this->$property = $value;
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
        return $this->__recordset;
    }

    protected static function getFieldId($property) {
        return Utils_RecordBrowserCommon::get_field_id($property);
    }

    /**
     * Get array of all properties - including id, author, active and creation date
     * @return array
     */
    public function toArray() {
        $refl = new ReflectionObject($this);
        $props = $refl->getProperties(ReflectionProperty::IS_PUBLIC);
        $ret = array();
        foreach ($props as $pro)
            $ret[$pro->getName()] = $pro->getValue($this);
        
        return $ret;
    }

    /**
     * Get only values of record - exclude id, _active, created_by, created_on
     * @return array
     */
    private function getValues() {
        $values = $this->toArray();
        unset($values['created_on']);
        unset($values['created_by']);
        unset($values['_active']);
        unset($values['id']);
        return $values;
    }

    private static function _is_private_property($property) {
        // below code is faster than
        //   substr($property, 0, 2) == '__' 
        // or strpos($property, '__') === 0
        return isset($property[0]) && isset($property[1]) && $property[0] == '_' && $property[1] == '_';
    }

    public function save() {
        if (!$recordset = $this->getRecordset()) {
        	trigger_error('Trying to save record that was not linked to proper recordset', E_USER_ERROR);
        }
        
        if (!$this->__id) {
            $rec = $recordset->addRecord($this->getValues());
            if ($rec === null)
                return false;
            $this->__id = $rec->id;
            $this->_active = $rec->_active;
            $this->created_by = $rec->created_by;
            return true;
        }
                
        return $recordset->updateRecord($this->__id, $this->getValues());
    }

    public function delete() {
        return $this->setActive(false);
    }

    public function restore() {
        return $this->setActive(true);
    }

    public function setActive($state) {
        $state = (boolean) $state;
        $this->_active = $state;
        return $this->getRecordset()->set_active($this->__records_id, $state);
    }

    public function clone_data() {
        $c = clone $this;
        $c->__records_id = $c->created_by = $c->created_on = $c->id = null;
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
        if (!self::_is_private_property($offset))
            return property_exists($this, $offset);
        return false;
    }

    public function offsetGet($offset) {
    	$offset = self::getFieldId($offset);
        if (!self::_is_private_property($offset))
            return $this->$offset;
        return null;
    }

    public function offsetSet($offset, $value) {
    	$offset = self::getFieldId($offset);
        if (self::_is_private_property($offset))
            trigger_error("Cannot use \"$offset\" as offset name.", E_USER_ERROR);
        $this->$offset = $value;
    }

    public function offsetUnset($offset) {
    	$offset = self::getFieldId($offset);
        if (!self::_is_private_property($offset))
            unset($this->$offset);
    }

}

?>