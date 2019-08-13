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
    public function init() {}

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

    public function getDisplayValues($record, $nolink = false, $customFieldIds = [], $quiet = true) {
    	$customFieldIds = array_map([Utils_RecordBrowserCommon::class, 'get_field_id'], $customFieldIds);
    	
    	$hash = $this->getRecordset()->getHash();
    	$fieldIds = $customFieldIds? array_intersect_key($hash, array_flip($customFieldIds)): $hash;
    	
    	$fields = array_intersect_key($this->getFields(), array_flip($fieldIds));

    	if ($customFieldIds && !$quiet && count($customFieldIds) != count($fields)) {
    		trigger_error('Unknown field names: ' . implode(', ', array_diff($customFieldIds, array_keys($fields))), E_USER_ERROR);
    	}
    		
    	$ret = [];
    	foreach ($fields as $field) {
    		if (!isset($record[$field->getArrayId()])) continue;
    		
    		$ret[$field->getArrayId()] = $this->getValue($field, $nolink);
    	}
    	
    	return $ret;
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
            
            if ($rec === null) return false;
            
            $this[':id'] = $rec[':id'];
            $this[':active'] = $rec[':active'];
            $this[':created_by'] = $rec[':created_by'];
            $this[':created_on'] = $rec[':created_on'];
            
            return true;
        }
                
        return $recordset->updateRecord($this[':id'], $this->getValues());
    }

    public function delete($permanent = false) {
    	if (!$permanent) return $this->setActive(false);
    	
    	$values = $this->process('delete');
    	
    	if ($values === false) return false;
    	
    	$this->clearHistory();
    	$this->deleteFavourite();
    	$this->deleteRecent();
    	
    	DB::Execute('DELETE FROM ' . $this->getRecordset()->getDataTable() . ' WHERE id=%d', [$this->getId()]);

    	if ($ret = DB::Affected_Rows() > 0) {
    		$this->process('deleted');
    	}
    	
    	return $ret;
    }

    public function restore() {
        return $this->setActive();
    }

    public function setActive($state = true) {
    	$state = $state ? 1 : 0;
    	
    	$this[':active'] = $state;
    	
    	$current = DB::GetOne('SELECT active FROM ' . $this->getRecordset()->getDataTable() . ' WHERE id=%d', [$this->getId()]);
    	
    	if ($current == $state) return false;
    	
    	$values = $this->process($state ? 'restore' : 'delete');
    	
    	if ($values === false) return false;
    	
    	@DB::Execute('UPDATE ' . $this->getRecordset()->getDataTable() . ' SET active=%d, indexed=0 WHERE id=%d', [$state, $this->getId()]);

    	if ($this->getRecordset()->getProperty('search_include') > 0) {
    		DB::Execute('DELETE FROM recordbrowser_search_index WHERE tab_id=%d AND record_id=%d', [$this->getRecordset()->getId(), $this->getId()]);
    	}
    	
    	$editId = $this->logHistory($state ? 'RESTORED' : 'DELETED');

    	//TODO: Georgi Hristov move this to processing callback
    	Utils_WatchdogCommon::new_event($this->getRecordset()->getTab(), $this->getId(), ($state ? 'R' : 'D') . '_' . $editId);
    	
    	$this->process($state ? 'restored' : 'deleted');
    	
    	return true;
    }
    
    public function isActive() {
    	return $this[':active']?? true;
    }    
    
    public function logHistory($oldValue) {
    	$tab = $this->getRecordset()->getTab();
    	
    	DB::Execute('INSERT INTO ' . $tab . '_edit_history(edited_on, edited_by, ' . $tab . '_id) VALUES (%T,%d,%d)', [date('Y-m-d G:i:s'), Acl::get_user(), $this->getId()]);
    	
    	$edit_id = DB::Insert_ID($tab . '_edit_history', 'id');
    	
    	DB::Execute('INSERT INTO ' . $tab . '_edit_history_data(edit_id, field, old_value) VALUES (%d,%s,%s)', [$edit_id, 'id', $oldValue]);
    	
    	return $edit_id;
    }
    
    public function deleteRecent() {
    	$tab = $this->getRecordset()->getTab();
    	
    	DB::Execute('DELETE FROM ' . $tab . '_recent WHERE ' . $tab . '_id = %d', [$this->getId()]);
    	
    	return DB::Affected_Rows();
    }
    
    public function deleteFavourite() {
    	$tab = $this->getRecordset()->getTab();
    	
    	DB::Execute('DELETE FROM ' . $tab . '_favorite WHERE ' . $tab . '_id = %d', [$this->getId()]);
    	
    	return DB::Affected_Rows();
    }
    
    public function clearHistory() {
    	$tab = $this->getRecordset()->getTab();
    	
    	DB::Execute('DELETE
					FROM ' . $tab . '_edit_history_data
					WHERE edit_id IN' .
    			' (SELECT id FROM ' . $tab . '_edit_history WHERE ' . $tab . '_id = %d)', [$this->getId()]);
    	
    	DB::Execute('DELETE FROM ' . $tab . '_edit_history WHERE ' . $tab . '_id = %d', [$this->getId()]);
    	
    	return DB::Affected_Rows();
    }    
    
    public function getRevision($revisionId) {
    	$tab = $this->getRecordset()->getTab();
    	
    	$ret = $this->toArray();
    	
    	$result = DB::Execute('SELECT 
									id, edited_on, edited_by 
								FROM ' . 
    								$tab . '_edit_history 
								WHERE ' . 
    								$tab . '_id=%d AND 
									id>=%d 
								ORDER BY 
									edited_on DESC, id DESC', [$this->getId(), $revisionId]);
    	
    	while ($row = $result->FetchRow()) {
    		$result2 = DB::Execute('SELECT * FROM '.$tab.'_edit_history_data WHERE edit_id=%d', [$row['id']]);
    		
    		while($row2 = $result2->FetchRow()) {    			
    			$fieldId = $row2['field'];
    			$oldValue = $row2['old_value'];
    			
    			if ($fieldId == 'id') {
    				$ret[':active'] = ($oldValue != 'DELETED');
    				
    				continue;
    			}
    			
    			if (!$this->getRecordset()->getHash($fieldId)) continue;
    				
    			$ret[$fieldId] = $oldValue;
    		}
    	}
    	
    	return $ret;
    }  
    
    public function getTooltipData()
    {
    	if (!$this->isActive()) return [];

    	$access = $this->getUserAccess('view');

    	$data = [];
    	foreach ($this->getFields() as $field) {
    		if (!$field['tooltip'] || !$access[$field['id']]) continue;
    			
    		$data[$field->getLabel()] = $field->getDisplayValue($this, true);
    	}
    	
    	return $data;
    }
    
    public function getFields($order = 'position') {
    	return $this->getRecordset()->getFields($order);
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