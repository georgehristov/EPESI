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

    public function getDisplayValues($options = []) {
    	$options = array_merge([
    			'nolink' => false,
    			'fields' => [],
    			'quiet' => true
    	], $options);
    	
    	$customFieldIds = array_map([Utils_RecordBrowserCommon::class, 'get_field_id'], $options['fields']);
    	
    	$hash = $this->getRecordset()->getHash();
    	$fieldIds = $customFieldIds? array_intersect_key($hash, array_flip($customFieldIds)): $hash;
    	
    	$fields = array_intersect_key($this->getFields(), array_flip($fieldIds));

    	if ($customFieldIds && !$options['quiet'] && count($customFieldIds) != count($fields)) {
    		trigger_error('Unknown field names: ' . implode(', ', array_diff($customFieldIds, array_keys($fields))), E_USER_ERROR);
    	}
    		
    	$ret = [];
    	foreach ($fields as $field) {
    		if (!isset($this[$field->getArrayId()])) continue;
    		
    		$ret[$field->getArrayId()] = $this->getValue($field, $options);
    	}
    	
    	return $ret;
    }
    
    /**
     * @param Utils_RecordBrowser_Recordset_Field $field
     * 
     * @return string
     */
    public function getValue($field, $options = []) { //$nolink) {
    	return $this->getRecordset()->getField($field)->display($this, $options);//$nolink);
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
    	return Utils_RecordBrowser_Crits::create($crits)->validate($this->getRecordset(), $this->toArray())? false: true;
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
    private function getValues($options = []) {
    	$options = array_merge([
    			'nolink' => false,
    			'checkAccess' => false
    	], $options);
    	
    	if (! $access = $options['checkAccess']? $this->getUserAccess(): true) return [];
    	
    	$ret = [];    	
    	foreach ($this->getFields() as $field) {
    		if (isset($access[$field->getId()]) && !$access[$field->getId()]) continue;
    		
    		$ret[$field->getId()] = $this->getValue($field, $options['nolink']);
    	}
        
        return $ret;
    }

    private static function isSpecialProperty($property) {
        return $property[0] == ':';
    }

    public function read($options = []) {
    	if (!$id = $this->getId()) {
    		trigger_error('Missing record id when attempting to read', E_USER_ERROR);
    	}
    	
    	$row = DB::GetRow("SELECT * FROM {$this->getTab()}_data_1 WHERE id=%d", [$id]);
    	
    	return $this->load($row, array_merge([
    			'asHtml' => true
    	], $options, [
    			'dirty' => false
    	]));
    }
    
    /**
     * Loads raw DB values array to the record fields
     * 
     * @param array 	$row - values from the DB
     * @param boolean 	$asHtml
     * @param boolean 	$dirty - use only provided values or fill all record fields with default values if not provided
     * @return Utils_RecordBrowser_Recordset_Record
     */
    public function load($row, $options = []) {
    	$dirty = $options['dirty']?? true;
    	
    	foreach ($this->getFields() as $field) {
    		$row[$field->getSqlId()] = $row[$field->getSqlId()]?? ($row[$field->getArrayId()]?? ($row[$field->getId()]?? null));
    		
    		if (!isset($row[$field->getSqlId()]) && $dirty) continue;
    		    		
    		$result = $field->process($row, 'get', $options);

    		$this[$field->getArrayId()] = $result[$field->getArrayId()]?? '';
    	}
    	
    	return $this;
    }
    
    /**
     * Load all record fields with default values
     */
    public function loadDefaults() {
    	$values = $this->getRecordset()->getDefaultValues($this);
    	
    	$this->load($values);
    }
    
    public function save($options = []) {
    	return $this->getId()? $this->update($options): $this->insert($options);
    }
    
    public function insert($options = [])
    {
    	$this->loadDefaults();

    	$values = $this->process('add');

    	if ($values === false) return $this;
    	
    	$fields = $this->getFields();
    	
    	$fieldList = [];
    	$fieldTypes = [];
    	$fieldValues = [];
    	foreach($fields as $field) {
    		if (! $result = $field->process($values, 'add')) continue;
    		
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
    	foreach ($fields as $field) {
    		$values = $field->process($values, 'added');
    	}
    	
    	$this->load($values);
    	
    	$this->process('added');    	
    	
    	return $this;
    }
    
    /**
     * Update the record entry in the database
     * 
     * @param array $options
     * 		- allFields: update all fields or only provided ones (dirty)
     * 		- onDate: log the update as done onDate 
     * 		- dontNotify: do not notify subscribed users about changes
     * 
     * @return Utils_RecordBrowser_Recordset_Record
     */
    public function update($options = []) {
    	if (!$this->getId()) {
    		trigger_error('Missing record id when attempting to update', E_USER_ERROR);
    	}
    	
    	$recordset = $this->getRecordset();

    	$existing = $recordset->findOne($this->getId())->toArray();

   		$values = $this->process('edit');

   		if ($values === false) return $this;
   		
   		$options = array_merge([
   				'allFields' => false,
   				'onDate' => null,
   				'dontNotify' => false
   		], $options);
   		
    	$diff = [];
    	$fieldList = [];
    	$fieldValues = [];
    	foreach ( $recordset->getFields() as $field ) {
    		if (! isset($values[$field->getId()])) {
    			if (! $options['allFields']) continue;
    			
    			$values[$field->getId()] = '';
    		}
    		
    		if (!$result = $field->process($values, 'edit', $existing)) continue;
    		
//     		if (! $field->isStored()) continue;
    		
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

			if (! $options['dontNotify']) {
				$diff = $recordset->process($diff, 'edit_changes');

				$this[':edit_id'] = $this->logHistory($diff, $options['onDate']);
			}
			
			$this->process('edited');
			
			DB::CompleteTrans();
    	}    	
    	
    	return $this;
    }

    public function delete($options = []) {
    	$permanent = $options['permanent']?? false;
    	
    	$this->setActive(false);
    	
    	if (! $permanent) return;
    	
    	$values = $this->process('drop');
    	
    	if ($values === false) return false;
    	
    	$this->clearHistory();
    	
    	DB::Execute('DELETE FROM ' . $this->getRecordset()->getDataTable() . ' WHERE id=%d', [$this->getId()]);

    	if ($ret = DB::Affected_Rows() > 0) {
    		$this->process('dropped');
    	}
    	
    	return $ret;
    }

    public function restore($options = []) {
        return $this->setActive();
    }
    
    public function wasModified($from, $to = null) {
    	if (! $this->getId()) return false;
    	
    	$to = $to?? date('Y-m-d H:i:s');
    	
    	return DB::GetOne('SELECT 1 FROM ' . $this->getRecordset()->getTable('history') . ' WHERE edited_on >= %T AND edited_on <= %T AND ' . $this->getTab() . '_id=%d', [$from, $to, $this->getId()]);
    }

    public function setActive($state = true) {
    	$state = $state ? 1 : 0;
    	
    	$this[':active'] = $state;
    	
    	$current = DB::GetOne('SELECT active FROM ' . $this->getRecordset()->getDataTable() . ' WHERE id=%d', [$this->getId()]);
    	
    	if ($current == $state) return true;
    	
    	$values = $this->process($state ? 'restore' : 'delete');
    	
    	if ($values === false) return false;
    	
    	@DB::Execute('UPDATE ' . $this->getRecordset()->getDataTable() . ' SET active=%d, indexed=0 WHERE id=%d', [$state, $this->getId()]);

    	if ($this->getRecordset()->getProperty('search_include') > 0) {
    		DB::Execute('DELETE FROM recordbrowser_search_index WHERE tab_id=%d AND record_id=%d', [$this->getRecordset()->getId(), $this->getId()]);
    	}
    	
    	$this[':edit_id'] = $this->logHistory([
				'id' => $state ? 'RESTORED': 'DELETED'
		]);

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
    			
    		$data[$field->getLabel()] = $this->getValue($field, true);
    	}
    	
    	return $data;
    }
    
    public function getFields($order = 'position') {
    	return $this->getRecordset()->getFields($order);
    }
    
    public function createHref($action = 'view', $urlOptions = []) {
    	$urlOptions = is_array($urlOptions)? $urlOptions: [];
    	
    	return Module::create_href($this->getHrefArray($action) + $urlOptions);
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
    
    public function createDefaultLinkedLabel($options = []) {
    	$options = array_merge([
    			'nolink' => false,
    			'tooltip' => true,
    			'includeTabCaption' => true,
    			'urlOptions' => []
    	], $options);
    	
    	$label = '';
    	if ($this->getUserAccess()) {
    		if ($descriptionPattern = $this->getRecordset()->getProperty('description_pattern')) {
				$label = trim(Utils_RecordBrowserCommon::replace_clipboard_pattern($descriptionPattern, $this->getValues([
						'nolink' => true,
						'checkAccess' => true
				])));
			}
			elseif ($descriptionCallback = $this->getRecordset()->getProperty('description_callback')) {
    			$label = call_user_func($descriptionCallback, $this->toArray(), $options['nolink']);
    		} else {
    			foreach ($this->getFields() as $field) {
    				if (! $field->isDescriptive()) continue;
    				
    				$label = $this->getValue($field, false);
    				break;
    			}
    		}
    	}
    	
    	$tabCaption = $this->getRecordset()->getCaption();
    	if (!$tabCaption || $tabCaption == '---') $tabCaption = $this->getTab();

    	$label = $label? ($options['includeTabCaption']? $tabCaption . ': ': '') . $label: sprintf("%s: #%06d", $tabCaption, $this->getId());

    	return $this->createLinkedText($label, $options);
    }
        
    public function createLinkedLabel($cols, $options = []) { //$nolink = false, $tooltip = false, $more = []) { 
    	$cols = is_array($cols)? $cols: explode('|', $cols);
    	
    	$pattern = [];
    	foreach ($cols as $col) {
    		$col = Utils_RecordBrowserCommon::get_field_id($col);
    		
    		$pattern[] = "%{{{$col}}}";
    	}  
    	
    	return $this->createLinkedPattern(implode(' ', $pattern), $options);
    }
    
    public function createLinkedPattern($pattern, $options = []) { //$nolink = false, $tooltip = false, $more = []) {
    	$label = trim(Utils_RecordBrowserCommon::replace_clipboard_pattern($pattern, $this->toArray()))?: $this->getRecordset()->getCaption() . ": " . sprintf("#%06d", $this->getId());
    	
    	return $this->createLinkedText($label, $options);
    }

    public function createLinkedText($text, $options = []) { //$nolink = false, $tooltip = true, $more = []) {
    	$options = array_merge([
    			'nolink' => false,
    			'tooltip' => false,
    			'urlOptions' => []
    	], $options);
    	
    	$tip = $openTag = $closeTag = '';

    	if (! $this->isActive()) {
    		$tip = __('This record was deleted from the system, please edit current record or contact system administrator');
    		$openTag = '<del>';
    		$closeTag = '</del>';
    	}
    	
    	if (! $options['nolink']) {
    		if ($this->getUserAccess()) {
    			$href = $this->createHref('view', $options['urlOptions']);
    			
    			$tipAttrs = $options['tooltip']? $this->getDefaultTooltipAttrs($tip? $tip . '<hr>': ''): Utils_TooltipCommon::open_tag_attrs($tip);
    			
    			$openTag = "<a $tipAttrs $href>$openTag";
    			$closeTag .= '</a>';
    		}
    		else {
    			$tip = implode('<br />', [$tip, __('You don\'t have permission to view this record.')]);
    			
    			$tipAttrs = Utils_TooltipCommon::open_tag_attrs($tip);
    			
    			$openTag = "<span $tipAttrs>$openTag";
    			$closeTag .= '</span>';
    		}
    	}    	

    	return $openTag . $text . $closeTag;    	
    }
    
    public function createTooltip($label, $options) { //$nolink = false, $tooltip = false) {
    	$options = array_merge([
    			'nolink' => false,
    			'tooltip' => false
    	], $options);
    	
    	$tooltip = $options['tooltip'];
    	
    	if (! $tooltip || $options['nolink'] || Utils_TooltipCommon::is_tooltip_code_in_str($label))
    		return $label;
    		
    		if (!is_array($options['tooltip'])) return $this->createDefaultTooltip($label);
    			
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
    
    final public function getDefaultTooltipAttrs($header = ''){
    	return Utils_TooltipCommon::ajax_open_tag_attrs([__CLASS__, 'getDefaultTooltipContents'], [$this->getTab(), $this->getId(), $header]);
    }
    
    final public function createDefaultTooltip($label, $force = false, $header = ''){
    	if (!$force && Utils_TooltipCommon::is_tooltip_code_in_str($label)) {
    		return $label;
    	}
    	
    	return Utils_TooltipCommon::ajax_create($label, [__CLASS__, 'getDefaultTooltipContents'], [$this->getTab(), $this->getId(), $header]);
    }
    
    final public static function getDefaultTooltipContents($tab, $id, $header = '')
    {
    	return $header . Utils_TooltipCommon::format_info_tooltip(self::create($tab, $id)->read()->getTooltipData());
    }
    
    public function getInfo() {
    	$edited = DB::GetRow('SELECT 
								edited_on, 
								edited_by 
							FROM ' . 
    							$this->getRecordset()->getTable('history') . ' 
							WHERE ' . 
    							$this->getTab() . '_id=%d 
							ORDER BY edited_on DESC', [$this->getId()]);

		return [
				'created_on' => $this[':created_on'],
				'created_by' => $this[':created_by'],
				'edited_on' => $edited['edited_on']?? null,
				'edited_by' => $edited['edited_by']?? null,
				'id' => $this->getId()
		];
	}
    
    public function cloneData() {
        $c = clone $this;
        
        $c[':id'] = $c[':created_by'] = $c[':created_on'] = null;
        
        return $c;
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