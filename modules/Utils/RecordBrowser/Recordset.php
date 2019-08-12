<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

interface Utils_RecordBrowser_RecordsetInterface {
	
}

class Utils_RecordBrowser_Recordset implements Utils_RecordBrowser_RecordsetInterface {
	protected static $cache = [];
	protected $tab;
	protected $tabAlias = 'r';
	protected $properties = [];	
	protected $admin;
	/**
	 * @var Utils_RecordBrowser_Recordset_Field[]
	 */
	protected $adminFields;
	protected $displayFields;
	protected $hash;
	protected $keyHash;
	protected $arrayKeys;
	protected $callbacks;
	protected $addons;
		
	/**
	 * @param string $tab
	 * @param boolean $admin
	 * @param boolean $force
	 * @return Utils_RecordBrowser_Recordset
	 */
	public static function create($tab, $force = false) {
		if (is_object($tab)) return $tab;
		
		if (!isset(self::$cache[$tab]) || $force) {
			self::$cache[$tab] = new static($tab);
		}

		return self::$cache[$tab];
	}
	
	public static function exists($tab, $flush = false, $quiet = false){
		static $tables = [];
		
		if (!$tables || $flush) {
			$r = DB::GetAll('SELECT tab FROM recordbrowser_table_properties');
			
			$tables = array_column($r, 'tab');			
		}
		
		if (!self::validateName($tab)) return true;
		
		$exists = in_array($tab, $tables);
		
		if (!$exists && !$flush && !$quiet) {
			trigger_error('RecordBrowser critical failure, terminating. (Requested '.serialize($tab).', available '.print_r($tables, true).')', E_USER_ERROR);
		}
		
		return $exists;
	}	
	
	public static function validateName($tab) {
		return is_string($tab) && !($tab == '__RECORDSETS__' || preg_match('/,/',$tab)) && preg_match('/^[a-zA-Z_0-9]+$/',$tab);
	}
	
	public static function install($tab, $fields=[]) {
		if (!self::validateName($tab)) trigger_error('Invalid table name ('.$tab.') given to install_new_recordset.',E_USER_ERROR);
		if (strlen($tab)>39) trigger_error('Invalid table name ('.$tab.') given to install_new_recordset, max length is 39 characters.',E_USER_ERROR);
		if (!DB::GetOne('SELECT 1 FROM recordbrowser_table_properties WHERE tab=%s', array($tab))) {
			DB::Execute('INSERT INTO recordbrowser_table_properties (tab) VALUES (%s)', array($tab));
		}
		
		@DB::DropTable($tab.'_callback');
		@DB::DropTable($tab.'_recent');
		@DB::DropTable($tab.'_favorite');
		@DB::DropTable($tab.'_edit_history_data');
		@DB::DropTable($tab.'_edit_history');
		@DB::DropTable($tab.'_field');
		@DB::DropTable($tab.'_data_1');
		@DB::DropTable($tab.'_access_clearance');
		@DB::DropTable($tab.'_access_fields');
		@DB::DropTable($tab.'_access');
		
		self::exists(null, true);
		DB::CreateTable($tab.'_field',
				'id I2 AUTO KEY NOTNULL,'.
				'field C(32) UNIQUE NOTNULL,'.
				'caption C(255),'.
				'type C(32),'.
				'extra I1 DEFAULT 1,'.
				'visible I1 DEFAULT 1,'.
				'tooltip I1 DEFAULT 1,'.
				'required I1 DEFAULT 1,'.
				'export I1 DEFAULT 1,'.
				'active I1 DEFAULT 1,'.
				'position I2,'.
				'processing_order I2 NOTNULL,'.
				'filter I1 DEFAULT 0,'.
				'param C(255),'.
				'style C(64),'.
				'template C(255),'.
				'help X',
				array('constraints'=>''));
		DB::CreateTable($tab.'_callback',
				'field C(32),'.
				'callback C(255),'.
				'freezed I1',
				array('constraints'=>''));
		
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, visible, position, processing_order) VALUES(\'id\', \'foreign index\', 0, 0, 1, 1)');
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, position, processing_order) VALUES(\'General\', \'page_split\', 0, 2, 2)');
		
		$fields_sql = '';
		foreach ($fields as $v)
			$fields_sql .= Utils_RecordBrowserCommon::new_record_field($tab, $v, false, false);
			DB::CreateTable($tab.'_data_1',
					'id I AUTO KEY,'.
					'created_on T NOT NULL,'.
					'created_by I NOT NULL,'.
					'indexed I1 NOT NULL DEFAULT 0,'.
					'active I1 NOT NULL DEFAULT 1'.
					$fields_sql,
					array('constraints'=>''));
			DB::CreateIndex($tab.'_idxed',$tab.'_data_1','indexed,active');
			DB::CreateIndex($tab.'_act',$tab.'_data_1','active');
			
			DB::CreateTable($tab.'_edit_history',
					'id I AUTO KEY,'.
					$tab.'_id I NOT NULL,'.
					'edited_on T NOT NULL,'.
					'edited_by I NOT NULL',
					array('constraints'=>', FOREIGN KEY (edited_by) REFERENCES user_login(id), FOREIGN KEY ('.$tab.'_id) REFERENCES '.$tab.'_data_1(id)'));
			DB::CreateTable($tab.'_edit_history_data',
					'edit_id I,'.
					'field C(32),'.
					'old_value X',
					array('constraints'=>', FOREIGN KEY (edit_id) REFERENCES '.$tab.'_edit_history(id)'));
			DB::CreateTable($tab.'_favorite',
					'fav_id I AUTO KEY,'.
					$tab.'_id I,'.
					'user_id I',
					array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id), FOREIGN KEY ('.$tab.'_id) REFERENCES '.$tab.'_data_1(id)'));
			DB::CreateTable($tab.'_recent',
					'recent_id I AUTO KEY,'.
					$tab.'_id I,'.
					'user_id I,'.
					'visited_on T',
					array('constraints'=>', FOREIGN KEY (user_id) REFERENCES user_login(id), FOREIGN KEY ('.$tab.'_id) REFERENCES '.$tab.'_data_1(id)'));
			DB::CreateTable($tab.'_access',
					'id I AUTO KEY,'.
					'action C(16),'.
					'crits X',
					array('constraints'=>''));
			DB::CreateTable($tab.'_access_fields',
					'rule_id I,'.
					'block_field C(32)',
					array('constraints'=>', FOREIGN KEY (rule_id) REFERENCES '.$tab.'_access(id)'));
			DB::CreateTable($tab.'_access_clearance',
					'rule_id I,'.
					'clearance C(32)',
					array('constraints'=>', FOREIGN KEY (rule_id) REFERENCES '.$tab.'_access(id)'));
			self::exists($tab, true);
			self::add_access($tab, 'print', 'SUPERADMIN');
			self::add_access($tab, 'export', 'SUPERADMIN');
		return true;
	}
	
	protected function __construct($tab) {
		if (!$this->validateName($tab) || !$this->exists($tab)) return;

		$this->setTab($tab);
	}	
	
	public function setField($desc) {
		if ($desc instanceof Utils_RecordBrowser_Recordset_Field) {
			$field = $desc;
		}
		else {
			$desc = array_merge($desc, $this->getCallbacks($desc['field']));

			if (!$field = Utils_RecordBrowser_Recordset_Field::create($this, $desc)) return $this;
		}

		$this->adminFields[$desc['field']] = $field;
		
		return $this;
	}
	
	/**
	 * @param string $order
	 * @return Utils_RecordBrowser_Recordset_Field[]
	 */
	public function getFields($order = 'position') {
		$this->displayFields = $this->displayFields?: array_filter($this->getAdminFields(), function (Utils_RecordBrowser_Recordset_Field $field) {
			return $field['active'] && $field['type'] != 'page_split';
		});

		$ret = $this->displayFields;
		if ($order !== 'position') {
			$callback = is_callable($order)? $order: function ($field1, $field2) use ($order) {
				return $field1[$order] > $field2[$order];
			};
			
			uasort($ret, $callback);
		}

		return $ret;
	}
	
	public function getVisibleFields($custom = []) {
		$ret = [];
		foreach($this->getRecordset()->getFields() as $field){
			if (!$field['visible'] && (!isset($custom[$field['id']]) || $custom[$field['id']] === false)) continue;
			
			if (isset($custom[$field['id']]) && $custom[$field['id']] === false) continue;
			
			$ret[$field['id']] = true;
		}
		
		return $ret;
	}
	
	public function getAdminFields() {
		if (!$this->adminFields) {
			foreach (Utils_RecordBrowser_Recordset_Field::getSpecial() as $class) {
				$this->setField($class::create($this));
			}
			
			$result = DB::Execute('SELECT * FROM ' . $this->getTab() . '_field ORDER BY position');
			
			while($desc = $result->FetchRow()) {
				if ($desc['field'] == 'id') continue;
				
				$this->setField($desc);
			}			
		}

		return $this->adminFields;
	}
	
	/**
	 * @param string $name
	 * @param boolean $quiet
	 * @return Utils_RecordBrowser_Recordset_Field
	 */
	public function getField($name, $quiet = false) {
		$fields = $this->getFields();
		
		$fieldName = isset($fields[$name])? $name: ($this->getHash($name)?: $this->getKeyHash($name));

		if (!$fieldName || !isset($fields[$fieldName])) {
			if (!$quiet) {
				trigger_error('Unknown field "'.$name.'" for recordset "'.$this->getTab().'"',E_USER_ERROR);
			}

			$fields[$fieldName] = Utils_RecordBrowser_Recordset_Field::create($this);
		}
		
		return $fields[$fieldName];
	}
	
	public function getHash($name = null) {
		if (!$this->hash) {
			foreach ($this->getAdminFields() as $field) {
				$this->hash[$field->getId()] = $field->getName();
			}
		}	
		
		return $name? ($this->hash[strtolower($name)]?? null): $this->hash;
	}
	
	public function getKeyHash($name = null) {
		if (!$this->keyHash) {
			foreach ($this->getAdminFields() as $field) {
				if (!$arrayId = $field->getArrayId()) continue;
				
				$this->keyHash[$arrayId] = $field->getName();
			}
		}	
		
		return $name? ($this->keyHash[strtolower($name)]?? null): $this->keyHash;
	}
	
	public function getRecordArrayKeys() {
		if (!$this->arrayKeys) {
			foreach ($this->getAdminFields() as $field) {
				$this->arrayKeys[] = $field->getArrayId();
			}
		}	
		
		return $this->arrayKeys;
	}
	
	public function getUserAccess($action, $admin = false) {
		return Utils_RecordBrowser_Recordset_Access::create($this, $action)->getUserAccess($admin);
	}
	
	public function getUserValuesAccess($action, $values, $admin = false) {
		return Utils_RecordBrowser_Recordset_Access::create($this, $action, $values)->getUserAccess($admin);
	}
	
	public function getProcessMethods() {
		static $cache;
		
		$tab = $this->getTab();
		if (!isset($cache[$tab])) {			
			$result = DB::Execute('SELECT * FROM recordbrowser_processing_methods WHERE tab=%s', [$tab]);
			
			$cache[$tab] = [];
			while ($row = $result->FetchRow()) {
				$callback = explode('::',$row['func']);
				
				if (!is_callable($callback)) continue;
				
				$cache[$tab][] = $callback;
			}
		}
		
		return $cache[$this->getTab()];
	}
	
	public function getCallbacks($field) {
		if (!isset($this->callbacks)) {
			$result = DB::Execute("SELECT * FROM {$this->getTab()}_callback");
			while ($row = $result->FetchRow())
				$this->callbacks[$row['field']][$row['freezed']? 'display_callback': 'QFfield_callback'] = $row['callback'];
		}
		
		return array_merge([
				'display_callback' => false,
				'QFfield_callback' => false
		], $this->callbacks[$field]?? []);
	}
	
	public function getAddons() {
		if (!isset($this->addons)) {
			$this->addons = DB::GetAll('SELECT * FROM recordbrowser_addon WHERE tab=%s ORDER BY pos', [$this->getTab()]);
		}
		
		return $this->addons;
	}
	
	public function getPrinter() {
		$class = DB::GetOne('SELECT printer FROM recordbrowser_table_properties WHERE tab=%s', $this->getTab());
		if($class && class_exists($class))
			return new $class();
		
		return new Utils_RecordBrowser_RecordPrinter();
	}
		
	public function getCaption() {
		return $this->getProperty('caption')?: __('Record Browser');
	}
	
	public function getIcon() {
		$icon = $this->getProperty('icon');
		
		return $icon?: Base_ThemeCommon::get_template_file($icon)?: Base_ThemeCommon::get_template_file('Base_ActionBar', 'icons/settings.png');
	}
	
	public function getProperty($name) {
		return $this->getProperties()[$name]?? null;
	}
		
	public function getProperties() {
		$this->properties = $this->properties?: DB::GetRow('SELECT caption, icon, recent, favorites, full_history, quickjump FROM recordbrowser_table_properties WHERE tab=%s', [$this->getTab()]);
		
		return $this->properties;
	}
		
	public function getClipboardPattern($with_state = false) {
		if($with_state) {
			$ret = DB::GetArray('SELECT pattern,enabled FROM recordbrowser_clipboard_pattern WHERE tab=%s', [
					$this->getTab()
			]);
			if (sizeof($ret)) return $ret[0];
		}

		return DB::GetOne('SELECT pattern FROM recordbrowser_clipboard_pattern WHERE tab=%s AND enabled=1', [$this->getTab()]);
	}
		
	public function getRecords($crits = [], $order = [], $limit = [], $admin = false) {
		$result = $this->select($crits, $order, $limit, $admin);
	
		$records = [];
		while ($row = $result->FetchRow()) {
			if (isset($records[$row['id']])) continue;
			
			$record = $this->getRecord($row);

			if (!$admin && !$record->getUserAccess('view')) continue;
			
			$records[$row['id']] = $record;
		}

		return $records;
	}
	
	public function getRecord($id, $htmlspecialchars = true) {
		if (!isset($id)) return [];
		
		if (is_object($id)) return $id;
		
		$row = is_numeric($id)? DB::GetRow("SELECT * FROM {$this->getTab()}_data_1 WHERE id=%d", [$id]): $id;

		if (!isset($row['active'])) return [];

		$values = [];
		foreach ($this->getFields() as $field) {
			$values = array_merge($values, $field->process($row, 'get', compact('htmlspecialchars')));
		}

		return Utils_RecordBrowser_Recordset_Record::create($this, $values);
	}
	
	public function getRecordsCount($crits = [], $admin = false)
	{
		$query = $this->getQuery($crits);
				
		return DB::GetOne($query->getCountSQL(), $query->getValues());
	}
	
	public function addRecord($values = [])
	{
		$fields = $this->getFields();

		$values = $this->getDefaultValues('add', $values);
		
		$values = $this->process($values, 'add');
		
		if ($values === false) return;

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
			$fieldValues[] = $value;
		}

		DB::Execute("INSERT INTO {$this->getTab()}_data_1 (" . implode(',', $fieldList) . ') VALUES (' . implode(',', $fieldTypes) . ')', $fieldValues);
		
		$id = DB::Insert_ID($this->getTab() . '_data_1', 'id');
			
		$values['id'] = $id;
		foreach($fields as $field) {
			$values = $field->process($values, 'added');
		}
		
		$this->process($values, 'added');
			
		//TODO: Georgi Hristov move below to a seperate record processing callback
		$user = Acl::get_user();
		if ($user) Utils_RecordBrowserCommon::add_recent_entry($this->getTab(), $user, $id);
		if (Base_User_SettingsCommon::get('Utils_RecordBrowser', $this->getTab().'_auto_fav'))
		DB::Execute("INSERT INTO {$this->getTab()}_favorite (user_id, {$this->getTab()}_id) VALUES (%d, %d)", [$user, $id]);
		
		if (Base_User_SettingsCommon::get('Utils_RecordBrowser', $this->getTab().'_auto_subs')==1)
			Utils_WatchdogCommon::subscribe($this->getTab(), $id);
		Utils_WatchdogCommon::new_event($this->getTab(), $id, 'C');
		//up to here
					
		return $id;
	}
	
	public function getDefaultValues($mode, $customDefaults) {
		$ret = [];
		foreach($this->getFields() as $field) {
			$ret[$field->getId()] = $customDefaults[$field->getId()]?? $field->defaultValue($mode);
		}
		
		return $ret;
	}
	
	public function process($values, $mode, $cloned = null) {
		$values = is_object($values)? $values->toArray(): $values;
		
		$current = $values;
		
		$ret = $mode != 'display'? $values: [];
		
		$current = $mode=='cloned'? ['original'=>$cloned, 'clone'=>$values]: $values;
		foreach ($this->getProcessMethods() as $callback) {
			$return = call_user_func($callback, $current, $mode, $this->getTab());
			
			if ($return === false) return false;
			
			if (!$return) continue;
			
			//TODO: GH array_merge_recursive will not work or record objects
			if ($mode == 'display') {
				$ret = array_merge_recursive($ret, $return);
			}
			else {
				$current = $return;
			}
		}
		
		return $mode == 'display'? $ret: $current;
	}
	
	public function createQuery($sql = '', $values = [])
	{
		return Utils_RecordBrowser_Recordset_Query::create($this, $sql, $values);
	}
	
	protected function select($crits = [], $order = [], $limit = [], $admin = false)
	{
		$limit = array_merge(is_numeric($limit)? ['numrows' => $limit]: $limit, [
				'offset' => 0,
				'numrows' => -1
		]);
		
		$query = $this->getQuery($crits);
		var_dump($query->getSelectSql($order));
		return DB::SelectLimit($query->getSelectSql($order), $limit['numrows'], $limit['offset'], $query->getValues());
	}
	
	/**
	 * @param array $crits
	 * @param boolean $admin
	 * @return Utils_RecordBrowser_Recordset_Query
	 */
	public function getQuery($crits = [], $admin = false)
	{
		static $cache;
		static $stack = [];
				
		$key = $this->getTab() . '__' . $this->getTabAlias() . '__' . md5(serialize($crits)) . '__' . $admin . '__' . Acl::get_user();
		
		if (isset($cache[$key])) return $cache[$key];
		
		$crits = $crits? [Utils_RecordBrowser_Crits::create($crits)]: [];
			
		$accessCrits = ($admin || in_array($this->getTab(), $stack))?: Utils_RecordBrowserCommon::get_access_crits($this->getTab(), 'browse');
		if ($accessCrits == false) return [];
		elseif ($accessCrits !== true) {
			$crits[] = $accessCrits;
		}
		var_dump($accessCrits->toWords($this));
		if ($admin) {
			$adminCrits = str_replace('<tab>', $this->getTabAlias(), Utils_RecordBrowserCommon::$admin_filter);
		} else {
			$adminCrits = $this->getTabAlias() . '.active=1';
		}
		
		$crits[] = Utils_RecordBrowser_Recordset_Query_Crits_RawSQL::create($adminCrits);

		array_push($stack, $this->getTab());
		$cache[$key] = Utils_RecordBrowser_Crits::create($crits)->getQuery($this);
		array_pop($stack);
		
		return $cache[$key];
	}
	
	/**
	 * @return string
	 */
	public function getTab() {
		return $this->tab;
	}
	
	/**
	 * @param string $tab
	 */
	public function setTab($tab) {
		$this->tab = $tab;
		
		return $this;
	}
	public function getTabAlias() {
		return $this->tabAlias;
	}

	public function setTabAlias($tabAlias) {
		$this->tabAlias = $tabAlias;
		
		return $this;
	}

}



