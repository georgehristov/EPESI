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
    public static function create($recordset, $values = []) {
    	if (is_object($values)) return $values;

    	return new static($recordset, is_numeric($values)? [':id' => $values]: $values);
    }
    /**
     * Create object of record.
     * To perform any operation during object construction
     * please override init() function. It's called at the end of __construct
     * 
     * @param Utils_RecordBrowser_Recordset $recordset Recordset object
     * @param $values data of record
     */
    public final function __construct($recordset, $values = []) {   
    	$this->setRecordset($recordset)->load($values)->init();
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
    	if (!$this->recordset) {
    		trigger_error('Trying to access record that was not linked to proper recordset', E_USER_ERROR);
    	}
    	
        return $this->recordset;
    }
    
    protected function setRecordset($recordset) {
    	$this->recordset = Utils_RecordBrowser_Recordset::create($recordset);
		
		return $this;
	}

	public function getTab() {
        return $this->getRecordset()->getTab();
    }
    
    public function getId() {
        return $this[':id'];
    }

    public function getDisplayValues($nolink = false, $customFieldIds = [], $quiet = true) {
    	$customFieldIds = array_map([Utils_RecordBrowserCommon::class, 'get_field_id'], $customFieldIds);
    	
    	$hash = $this->getRecordset()->getHash();
    	$fieldIds = $customFieldIds? array_intersect_key($hash, array_flip($customFieldIds)): $hash;
    	
    	$fields = array_intersect_key($this->getFields(), array_flip($fieldIds));

    	if ($customFieldIds && !$quiet && count($customFieldIds) != count($fields)) {
    		trigger_error('Unknown field names: ' . implode(', ', array_diff($customFieldIds, array_keys($fields))), E_USER_ERROR);
    	}
    		
    	$ret = [];
    	foreach ($fields as $field) {
    		if (!isset($this[$field->getArrayId()])) continue;
    		
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
    
    public function getUserAccess($action = 'view', $admin = false) {
    	return Utils_RecordBrowser_Recordset_Access::create($this->getRecordset(), $action, $this)->getUserAccess($admin);
    }
    
    public function getUserFieldAccess($field, $action = 'view', $admin = false) {
    	$field = is_object($field)? $field->getId(): $field;
    	
    	$userAccess = $this->getUserAccess($action, $admin);
    	
    	if ($userAccess === true) return true;
    	
    	return $userAccess[$field]?? false;
    }
    
    public function process($mode, $cloned = null) {
    	$modified = $this->getRecordset()->process($this, $mode, $cloned);
    	
    	if ($modified === false) return false;
    	
    	foreach ($modified?: [] as $key => $value) {
    		$this[$key] = $value;
    	}
    	
    	return $this->toArray();
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
    
    public function validate($crits) {
    	return Utils_RecordBrowser_Crits::create($crits)->validate($this);
    }

    /**
     * Get array of all properties - including id, author, active and creation date
     * @return array
     */
    public function toArray() {
    	//backward compatibility as special values were not properly marked with : prefix in previous EPESI versions
    	$ret = $this->values;
    	foreach ($this->values as $key => $value) {
    		if ($key[0] != ':') continue;

    		$ret[substr($key, 1)] = $value;
    	}
    	
    	return $ret;
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

    public function read($htmlspecialchars = true) {
    	if (!$id = $this->getId()) {
    		trigger_error('Missing record id when attempting to read', E_USER_ERROR);
    	}
    	
    	$row = DB::GetRow("SELECT * FROM {$this->getTab()}_data_1 WHERE id=%d", [$id]);

    	return $this->load($row, $htmlspecialchars, false);
    }
    
    public function load($row, $htmlspecialchars = true, $dirty = true) {
    	foreach ($this->getFields() as $field) {
    		$row[$field->getSqlId()] = $row[$field->getSqlId()]?? ($row[$field->getArrayId()]?? ($row[$field->getId()]?? null));
    		
    		if (!isset($row[$field->getSqlId()]) && $dirty) continue;
    		    		
    		$result = $field->process($row, 'get', compact('htmlspecialchars'));

    		$this[$field->getArrayId()] = $result[$field->getArrayId()]?? '';
    	}
    	
    	return $this;
    }
    
    public function loadDefaults() {
    	$values = $this->getRecordset()->getDefaultValues('add', $this);
    	
    	$this->load($values);
    }
    
    public function save() {
        return $this->getId()? $this->update(): $this->add();
    }
    
    public function add()
    {
    	$this->loadDefaults();

    	$values = $this->process('add');

    	if ($values === false) return $this;
    	
    	$fields = $this->getFields();
    	
    	$fieldList = [];
    	$fieldTypes = [];
    	$fieldValues = [];
    	foreach($fields as $field) {
    		if (!$result = $field->process($values, 'add')) continue;
    		
    		$value = $result[$field->getId()]?? '';
    		
    		if ($value === '') continue;
    		
    		if (!$sqlId = $field->getSqlId()) continue;
    		
    		if (!$sqlType = $field->getSqlType()) continue;
    		
    		$fieldList[] = $sqlId;
    		$fieldTypes[] = $sqlType;
    		$fieldValues[] = $field->encodeValue($value);
    	}
    	
    	DB::Execute("INSERT INTO {$this->getTab()}_data_1 (" . implode(',', $fieldList) . ') VALUES (' . implode(',', $fieldTypes) . ')', $fieldValues);
    	
    	$id = DB::Insert_ID($this->getTab() . '_data_1', 'id');
    	
    	$this[':id'] = $values['id'] = $id;
    	foreach($fields as $field) {
    		$values = $field->process($values, 'added');
    	}
    	
    	$this->load($values);
    	
    	$this->process('added');    	
    	
    	//TODO: Georgi Hristov move below to a seperate record processing callback
    	$user = Acl::get_user();
    	if ($user) Utils_RecordBrowserCommon::add_recent_entry($this->getTab(), $user, $id);
    	if (Base_User_SettingsCommon::get('Utils_RecordBrowser', $this->getTab().'_auto_fav')) {
    		DB::Execute("INSERT INTO {$this->getTab()}_favorite (user_id, {$this->getTab()}_id) VALUES (%d, %d)", [$user, $id]);
    	}
    	if (Base_User_SettingsCommon::get('Utils_RecordBrowser', $this->getTab().'_auto_subs')==1) {
    		Utils_WatchdogCommon::subscribe($this->getTab(), $id);
    	}
    	Utils_WatchdogCommon::new_event($this->getTab(), $id, 'C');
    	//up to here
    			
    	return $this;
    }
    
    public function update($allFields = false, $onDate = null, $dontNotify = false) {
    	if (!$this->getId()) {
    		trigger_error('Missing record id when attempting to update', E_USER_ERROR);
    	}
    	
    	$recordset = $this->getRecordset();

    	$existing = $recordset->getRecord($this->getId())->toArray();
    	
   		$values = $this->process('edit');
   		
   		if ($values === false) return $this;

    	$diff = [];
    	$fieldList = [];
    	$fieldValues = [];
    	foreach ( $recordset->getFields() as $field ) {
    		if (! isset($values[$field->getId()])) {
    			if (! $allFields) continue;
    			
    			$values[$field->getId()] = '';
    		}
    		
    		if (!$result = $field->process($values, 'edit', $existing)) continue;
    		
    		if (!$sqlId = $field->getSqlId()) continue;
    		
    		if (!$sqlType = $field->getSqlType()) continue;
    		
			$value = $result[$field->getId()];

			$fieldList[] = $sqlId . '=' . ($value === ''? 'NULL': $sqlType);
			if ($value !== '') $fieldValues[] = $field->encodeValue($value);
			
			$diff[$field->getId()] = $field->encodeValue($existing[$field->getId()]);
		}
		
		if ($diff) {
			DB::StartTrans();
			
			$fieldList[] = 'indexed=0';
			
			DB::Execute('UPDATE ' . $recordset->getDataTable() . ' SET ' . implode(', ', $fieldList) . ' WHERE id=%d', array_merge($fieldValues, [$this->getId()]));

			$this->process('edited');
		
			if (! $dontNotify) {
				$diff = $recordset->process($diff, 'edit_changes');

				$editId = $this->logHistory($diff, $onDate);
				
				Utils_WatchdogCommon::new_event($this->getTab(), $this->getId(), 'E_' . $editId);
			}
			
			DB::CompleteTrans();
    	}    	
    	
    	return $this;
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
    	
		$editId = $this->logHistory([
				'id' => $state ? 'RESTORED': 'DELETED'
		]);

    	//TODO: Georgi Hristov move this to processing callback
    	Utils_WatchdogCommon::new_event($this->getRecordset()->getTab(), $this->getId(), ($state ? 'R' : 'D') . '_' . $editId);
    	
    	$this->process($state ? 'restored' : 'deleted');
    	
    	return true;
    }
    
    public function isActive() {
    	return $this[':active']?? true;
    }    
    
    public function logHistory($diff, $onDate = null) {
    	$recordset = $this->getRecordset();
    	
    	DB::Execute('INSERT INTO ' . $recordset->getTable('history') . ' (edited_on, edited_by, ' . $this->getTab() . '_id) VALUES (%T,%d,%d)', [
    			$onDate?: date('Y-m-d G:i:s'),
    			Acl::get_user(),
    			$this->getId()
    	]);
    	
    	$editId = DB::Insert_ID($recordset->getTable('history'), 'id');
    	
    	foreach ( $diff as $fieldId => $oldValues ) {
    		foreach ( is_array($oldValues)? $oldValues: [$oldValues] as $oldValue ) {
    			DB::Execute('INSERT INTO ' . $recordset->getTable('history_data') . ' (edit_id, field, old_value) VALUES (%d,%s,%s)', [$editId, $fieldId, $oldValue]);
    		}
    	}
    	
    	return $editId;
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
					FROM ' . 
    					$this->getRecordset()->getTab('history_data') .
					' WHERE edit_id IN' .
    			' (SELECT id FROM ' . $this->getRecordset()->getTab('history')  . ' WHERE ' . $tab . '_id = %d)', [$this->getId()]);
    	
    	DB::Execute('DELETE FROM ' . $this->getRecordset()->getTab('history') . ' WHERE ' . $tab . '_id = %d', [$this->getId()]);
    	
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
    
    public function createHref($action = 'view', $more = []){
    	return Module::create_href($this->getHrefArray($action) + $more);
    }
    
    public function getHrefArray($action = 'view'){
    	if ($this->navigate($action)) return [];
	
    	return $this->createHrefArray($this->getRecordset()->getTab(), $this->getId(), $action);
	}
    
    public static function createHrefArray($tab, $id, $action){
		return [
				'__jump_to_RB_table' => $tab,
				'__jump_to_RB_record' => $id,
				'__jump_to_RB_action' => $action
		];
	}
    
    public function navigate($action) {
    	if (!isset($_REQUEST['__jump_to_RB_table']) ||
    		$this->getRecordset()->getTab() != $_REQUEST['__jump_to_RB_table'] ||
    		$this->getId() != $_REQUEST['__jump_to_RB_record'] ||
    		($action != $_REQUEST['__jump_to_RB_action'])) return false;
    	
	    unset($_REQUEST['__jump_to_RB_record']);
	    unset($_REQUEST['__jump_to_RB_table']);
	    unset($_REQUEST['__jump_to_RB_action']);
    	
	    Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(), 'view_entry_with_REQUEST', [$action, $this->getId(), [], true, $_REQUEST], [$this->getRecordset()->getTab()]);
	    
	    return true;
    }
    
    public function createLinkedLabel($cols, $nolink = false, $tooltip = false, $more = []) { 
    	$cols = is_array($cols)? $cols: explode('|', $cols);
    	
    	$vals = array_filter($this->getDisplayValues($nolink, $cols, false));
    	
    	$label = implode(' ', $vals)?: $this->getRecordset()->getCaption() . ": " . sprintf("#%06d", $this->getId());
    	
    	$label = $this->createTooltip($label, $nolink, $tooltip);
    	
    	return Utils_RecordBrowserCommon::record_link_open_tag_r($this->getRecordset()->getTab(), $this->toArray(), $nolink, 'view', $more) .
    			$label . Utils_RecordBrowserCommon::record_link_close_tag();
    }
    
    public function createTooltip($label, $nolink = false, $tooltip = false){
    	if (!$tooltip || $nolink || Utils_TooltipCommon::is_tooltip_code_in_str($label))
    		return $label;
    		
    	if (!is_array($tooltip)) return $this->createDefaultTooltip($label);
    			
    	//args name => expected index (in case of numeric indexed array)
    	$tooltip_create_args = ['tip'=>0, 'args'=>1, 'help'=>1, 'max_width'=>2];
    			
    	foreach ($tooltip_create_args as $name=>&$key) {
    		switch (true) {
    			case isset($tooltip[$name]):
    				$key = $tooltip[$name];
    				break;
    			case isset($tooltip[$key]):
    				$key = $tooltip[$key];
    				break;
    			default:
    				$key = null;
    				break;
    		}
    	}
    			
    	if (is_callable($tooltip_create_args['tip'])) {
    		unset($tooltip_create_args['help']);
    				
    		if (!is_array($tooltip_create_args['args']))
    			$tooltip_create_args['args'] = array($tooltip_create_args['args']);
    					
    			$tooltip_create_callback = ['Utils_TooltipCommon', 'ajax_create'];
    		}
    		else {
    			unset($tooltip_create_args['args']);
    			$tooltip_create_callback = ['Utils_TooltipCommon', 'create'];
    		}
    			
    	array_unshift($tooltip_create_args, $label);
    			
    	//remove null values from end of the create_tooltip_args to ensure default argument values are set in the callback
    	while (is_null(end($tooltip_create_args)))
    		array_pop($tooltip_create_args);
    				
    	return call_user_func_array($tooltip_create_callback, $tooltip_create_args);
    }
    
    public function createDefaultTooltip($label, $force = false){
    	if (!$force && Utils_TooltipCommon::is_tooltip_code_in_str($label)) {
    		return $label;
    	}
    	
    	return Utils_TooltipCommon::ajax_create($label, [$this, 'getDefaultTooltipContents'], [$this->getRecordset()->getTab(), $this->getId()]);
    }
    
    public function getDefaultTooltipContents($tab, $id)
    {
    	return Utils_TooltipCommon::format_info_tooltip($this->getTooltipData());
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