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
	protected static $datatypes;
		
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
		if (!DB::GetOne('SELECT 1 FROM recordbrowser_table_properties WHERE tab=%s', [$tab])) {
			DB::Execute('INSERT INTO recordbrowser_table_properties (tab) VALUES (%s)', [$tab]);
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
				['constraints'=>'']);
		
		DB::CreateTable($tab.'_callback',
				'field C(32),'.
				'callback C(255),'.
				'freezed I1',
				['constraints'=>'']);
		
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, visible, position, processing_order) VALUES(\'id\', \'foreign index\', 0, 0, 1, 1)');
		DB::Execute('INSERT INTO '.$tab.'_field(field, type, extra, position, processing_order) VALUES(\'General\', \'page_split\', 0, 2, 2)');
		
		$fields_sql = '';
		foreach ( $fields as $v ) {
			$fields_sql .= Utils_RecordBrowserCommon::new_record_field($tab, $v, false, false);
		}
		
		DB::CreateTable($tab . '_data_1', 
				'id I AUTO KEY,' . 
				'created_on T NOT NULL,' . 
				'created_by I NOT NULL,' . 
				'indexed I1 NOT NULL DEFAULT 0,' . 
				'active I1 NOT NULL DEFAULT 1' . $fields_sql, [
				'constraints' => ''
		]);

		DB::CreateIndex($tab . '_idxed', $tab . '_data_1', 'indexed,active');
		DB::CreateIndex($tab . '_act', $tab . '_data_1', 'active');

		DB::CreateTable($tab . '_edit_history', 
				'id I AUTO KEY,' . $tab . 
				'_id I NOT NULL,' . 
				'edited_on T NOT NULL,' . 
				'edited_by I NOT NULL', [
				'constraints' => ', FOREIGN KEY (edited_by) REFERENCES user_login(id), FOREIGN KEY (' . $tab . '_id) REFERENCES ' . $tab . '_data_1(id)'
		]);
		DB::CreateTable($tab . '_edit_history_data', 
				'edit_id I,' . 
				'field C(32),' . 
				'old_value X',[
				'constraints' => ', FOREIGN KEY (edit_id) REFERENCES ' . $tab . '_edit_history(id)'
		]);
		DB::CreateTable($tab . '_favorite', 
				'fav_id I AUTO KEY,' . 
				$tab . '_id I,' . 
				'user_id I', [
				'constraints' => ', FOREIGN KEY (user_id) REFERENCES user_login(id), FOREIGN KEY (' . $tab . '_id) REFERENCES ' . $tab . '_data_1(id)'
		]);
		DB::CreateTable($tab . '_recent', 
				'recent_id I AUTO KEY,' . 
				$tab . '_id I,' . 
				'user_id I,' . 
				'visited_on T', [
				'constraints' => ', FOREIGN KEY (user_id) REFERENCES user_login(id), FOREIGN KEY (' . $tab . '_id) REFERENCES ' . $tab . '_data_1(id)'
		]);
		DB::CreateTable($tab . '_access', 
				'id I AUTO KEY,' . 
				'action C(16),' . 
				'crits X', [
				'constraints' => ''
		]);
		DB::CreateTable($tab . '_access_fields', 
				'rule_id I,' . 
				'block_field C(32)', [
				'constraints' => ', FOREIGN KEY (rule_id) REFERENCES ' . $tab . '_access(id)'
		]);
		DB::CreateTable($tab . '_access_clearance', 
				'rule_id I,' . 
				'clearance C(32)', [
				'constraints' => ', FOREIGN KEY (rule_id) REFERENCES ' . $tab . '_access(id)'
		]);
		
		self::exists($tab, true);
		Utils_RecordBrowserCommon::add_access($tab, 'print', 'SUPERADMIN');
		Utils_RecordBrowserCommon::add_access($tab, 'export', 'SUPERADMIN');
		
		return true;
	}
	
	public static function uninstall($tab) {
		if (!self::exists($tab,true)) return;
		
		Utils_WatchdogCommon::unregister_category($tab);
		DB::DropTable($tab.'_callback');
		DB::DropTable($tab.'_recent');
		DB::DropTable($tab.'_favorite');
		DB::DropTable($tab.'_edit_history_data');
		DB::DropTable($tab.'_edit_history');
		DB::DropTable($tab.'_field');
		DB::DropTable($tab.'_data_1');
		DB::DropTable($tab.'_access_clearance');
		DB::DropTable($tab.'_access_fields');
		DB::DropTable($tab.'_access');
		DB::Execute('DELETE FROM recordbrowser_table_properties WHERE tab=%s', [$tab]);
		DB::Execute('DELETE FROM recordbrowser_processing_methods WHERE tab=%s', [$tab]);
		DB::Execute('DELETE FROM recordbrowser_browse_mode_definitions WHERE tab=%s', [$tab]);
		DB::Execute('DELETE FROM recordbrowser_clipboard_pattern WHERE tab=%s', [$tab]);
		DB::Execute('DELETE FROM recordbrowser_addon WHERE tab=%s', [$tab]);
		DB::Execute('DELETE FROM recordbrowser_access_methods WHERE tab=%s', [$tab]);
		
		return true;
	}
	
	public static function registerDatatype($type, $module, $func) {
		if(self::$datatypes!==null) self::$datatypes[$type] = [$module, $func];
		DB::Execute('INSERT INTO recordbrowser_datatype (type, module, func) VALUES (%s, %s, %s)', [$type, $module, $func]);
	}
	public static function unregisterDatatype($type) {
		if (self::$datatypes!==null) unset(self::$datatypes[$type]);
		DB::Execute('DELETE FROM recordbrowser_datatype WHERE type=%s', [$type]);
	}
	
	public static function getDatatypes() {
		if (!isset(self::$datatypes)) {			
			$result = DB::Execute('SELECT * FROM recordbrowser_datatype');
			
			self::$datatypes = [];
			while ($row = $result->FetchRow()) {
				self::$datatypes[$row['type']] = [$row['module'], $row['func']];
			}
		}
		
		return self::$datatypes;
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
	
	public function getAccessibleFields($action = 'view') {
		$access = $this->getUserAccess($action);
		
		$fields = $this->getFields();
		
		if ($access !== true) {
			foreach($fields as $field) {
				if (isset($access[$field['id']]) && $access[$field['id']]) continue;
					
				unset($fields[$field['name']]);
			}
		}
		
		return $fields;
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
		if (is_object($name)) return $name;
		
		$fields = $this->getFields();
		
		$name = Utils_RecordBrowser_Recordset_Query_Crits::stripModifiers($name);
		
		$name = preg_match('/^[0-9]+$/', strval($name))? ($this->getPKeyHash($name)?: $name): $name; // numeric
		
		$fieldName = isset($fields[$name])? $name: $this->getHash($name);

		if (!$fieldName || !isset($fields[$fieldName])) {
			if (!$quiet) {
				trigger_error('Unknown field "'.$name.'" for recordset "'.$this->getTab().'"',E_USER_ERROR);
			}

			$fields[$fieldName] = Utils_RecordBrowser_Recordset_Field::create($this);
		}
		
		return $fields[$fieldName];
	}
	
	public function getHash($name = null, $key = 'id') {
		if (!$this->hash) {
			foreach ($this->getAdminFields() as $field) {
				$this->hash[$field->getId()] = $field['field'];
				
				if (!$arrayId = $field->getArrayId()) continue;
				
				$this->hash[$arrayId] = $field['field'];
			}
		}	
		
		return $name? ($this->hash[strtolower($name)]?? null): $this->hash;
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
	
	public function getAccessCrits($action, $values = null) {
		return Utils_RecordBrowser_Recordset_Access::create($this, $action, $values)->getCrits();
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
		return $this->addons = $this->addons?? DB::GetAll('SELECT * FROM recordbrowser_addon WHERE tab=%s ORDER BY pos', [$this->getTab()]);
	}
	
	public function getPrinter() {
		$class = $this->getProperty('printer');
		
		$class = $class && class_exists($class)? $class: Utils_RecordBrowser_RecordPrinter::class;
		
		return new $class();
	}
		
	public function getCaption() {
		return _V($this->getProperty('caption')?: _M('Record Browser'));
	}
	
	public function getIcon() {
		$icon = $this->getProperty('icon');
		
		return $icon? Base_ThemeCommon::get_template_file($icon): Base_ThemeCommon::get_template_file('Base_ActionBar', 'icons/settings.png');
	}
	
	public function getProperty($name) {
		return $this->getProperties()[$name]?? null;
	}
		
	public function setProperty($name, $value) {
		return $this->setProperties([$name => $value]);
	}
		
	public function getProperties() {
		return $this->properties = $this->properties?: DB::GetRow('SELECT * FROM recordbrowser_table_properties WHERE tab=%s', [$this->getTab()]);
	}
		
	public function setProperties($properties) {
		$properties = array_merge($this->getProperties(), $properties);
		
		DB::Execute('UPDATE 
						recordbrowser_table_properties 
					SET 
						caption=%s,
						description_pattern=%s,
						favorites=%b,
						recent=%d,
						full_history=%b,
						jump_to_id=%b,
						search_include=%d,
						search_priority=%d 
					WHERE tab=%s', [
							$properties['caption'],
							$properties['description_pattern'],
							$properties['favorites'],
							$properties['recent'],
							$properties['full_history'],
							$properties['jump_to_id'],
							$properties['search_include'],
							$properties['search_priority'],
							$this->getTab()
		]);
		
		return $this;
	}
		
	/**
	 * Method to manipulate clipboard pattern
	 * 
	 * @param string|null 		$pattern pattern, or when it's null the pattern stays the same, only enable state changes
	 * @param array $options
	 * 		- enabled: new enabled state of clipboard pattern
	 * 		- force: make it true to allow any changes or overwrite when clipboard pattern exist
	 * @return bool 			true if any changes were made, false otherwise
	 */

	public function setClipboardPattern($pattern, $options = []) {
		$tab = $this->getTab();
		
		$options = array_merge([
				'enabled' => true,
				'force' => false
		], $options);
		
		$ret = null;
		$enabled = $options['enabled'] ? 1 : 0;
		$existing = $this->getClipboardPatternEntry();
		
		/* when pattern exists and i can overwrite it... */
		if($existing && $options['force']) {
			/* just change enabled state, when pattern is null */
			if($pattern === null) {
				$ret = DB::Execute('UPDATE recordbrowser_clipboard_pattern SET enabled=%d WHERE tab=%s', [$enabled, $tab]);
			} else {
				/* delete if it's not necessary to hold any value */
				if($enabled == 0 && strlen($pattern) == 0) $ret = DB::Execute('DELETE FROM recordbrowser_clipboard_pattern WHERE tab = %s', [$tab]);
				/* or update values */
				else $ret = DB::Execute('UPDATE recordbrowser_clipboard_pattern SET pattern=%s,enabled=%d WHERE tab=%s',[$pattern,$enabled,$tab]);
			}
		}
		/* there is no such pattern in database so create it*/
		if(!$existing) {
			$ret = DB::Execute('INSERT INTO recordbrowser_clipboard_pattern values (%s,%s,%d)', [$tab, $pattern, $enabled]);
		}

		return $ret? true: false;
	}
	
	public function getClipboardPatternEntry() {
		$ret = DB::GetArray('SELECT pattern, enabled FROM recordbrowser_clipboard_pattern WHERE tab=%s', [
					$this->getTab()
		]);
		
		return $ret? $ret[0]: [];
	}
	
	public function getClipboardPattern() {
		return DB::GetOne('SELECT pattern FROM recordbrowser_clipboard_pattern WHERE tab=%s AND enabled=1', [$this->getTab()]);
	}
	
	public function find($crits = [], $options = []) {
		$result = $this->select($crits, $options);
		
		$admin = $options['admin']?? false;
	
		$records = [];
		while ($row = $result->FetchRow()) {
			if (isset($records[$row['id']])) continue;
			
			$record = Utils_RecordBrowser_Recordset_Record::create($this)->load($row, [
					'dirty' => false
			]);

			if (! $admin && ! $record->getUserAccess('view')) continue;
			
			$records[$record->getId()] = $record;
		}

		return $records;
	}
	
	public function findOne($idOrValuesOrObject, $options = []) { 
		return $this->entry($idOrValuesOrObject)->read($options);
	}
	
	public function entry($idOrValuesOrObject) {
		if (is_object($idOrValuesOrObject)) return $idOrValuesOrObject;
		
		if (is_numeric($idOrValuesOrObject)) return $this->findOne([':id' => $idOrValuesOrObject]);
		
		return Utils_RecordBrowser_Recordset_Record::create($this, $idOrValuesOrObject);
	}
	
	public function insertOne($values = [], $options = [])
	{
		return Utils_RecordBrowser_Recordset_Record::create($this, $values)->insert();
	}
	
	public function insertMany($records = [], $options = [])
	{
		$ret = true;
		foreach ($records as $values) {
			$ret &= $this->insertOne($values, $options)? true: false;
		}
		
		return $ret;
	}
	
	public function saveOne($values = []) {
		return Utils_RecordBrowser_Recordset_Record::create($this, $values)->save();
	}
	
	public function count($crits = [], $options = [])
	{
		$query = $this->getQuery($crits, $options);
				
		return DB::GetOne($query->getCountSQL(), $query->getValues());
	}
	
	public function deleteOne($idOrValuesOrObject, $options = [])
	{
		return $this->findOne($idOrValuesOrObject)->delete($options);
	}
	
	public function deleteMany($crits, $options = [])
	{
		$ret = true;
		foreach ($this->find($crits, $options) as $record) {
			$ret &= $record->delete($options);
		}
		
		return $ret;
	}
	
	public function getDefaultValues($customDefaults) {
		$ret = [];
		foreach($this->getFields() as $field) {
			$ret[$field->getId()] = $customDefaults[$field->getId()]?? $field->defaultValue();
		}
		
		return $ret;
	}
	
	public function process($values, $mode, $cloned = null) {
		$values = is_object($values)? $values->toArray(): $values;
		
		$current = $values;
		
		$ret = $mode != 'display'? $values: [];
		
		$current = $mode == 'cloned'? ['original'=>$cloned, 'clone'=>$values]: $values;
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
	
	protected function select($crits = [], $options = [])
	{
		$options = array_merge([
				'order' => [],
				'limit' => [],
				'admin' => false
		], $options);
		
		$limit = is_numeric($options['limit'])? ['numrows' => $options['limit']]: ($options['limit']?: []);
		
		$limit = array_merge([
				'offset' => 0,
				'numrows' => -1
		], $limit);
		
		$query = $this->getQuery($crits, $options);
		var_dump($query->getSelectSql($options['order']), $query->getValues());
		return DB::SelectLimit($query->getSelectSql($options['order']), $limit['numrows'], $limit['offset'], $query->getValues());
	}
	
	/**
	 * @param array $crits
	 * @param boolean $admin
	 * @return Utils_RecordBrowser_Recordset_Query
	 */
	public function getQuery($crits = [], $options = [])
	{
		static $cache;
		static $stack = [];
		
		$admin = $options['admin']?? false;
				
		$key = md5(serialize([$this->getTab(), $this->getDataTableAlias(), $crits, $admin, Acl::get_user()]));
		
		if (isset($cache[$key])) return $cache[$key];
		
		$crits = $crits? [Utils_RecordBrowser_Crits::create($crits)]: [];
			
		if (! $accessCrits = ($admin || in_array($this->getTab(), $stack))?: $this->getAccessCrits('browse')) {
			return Utils_RecordBrowser_Recordset_Query::create($this, 'false');
		}
		
		if ($accessCrits !== true) {
			$crits[] = $accessCrits;
		}

		if ($admin) {
			$adminCrits = str_replace('<tab>', $this->getDataTableAlias(), Utils_RecordBrowserCommon::$admin_filter);
		} else {
			$adminCrits = $this->getDataTableAlias() . '.active=1';
		}
		
		$crits[] = Utils_RecordBrowser_Recordset_Query_Crits_RawSQL::create($adminCrits);

		array_push($stack, $this->getTab());
		$cache[$key] = Utils_RecordBrowser_Crits::create($crits)->getQuery($this);
		array_pop($stack);
		
		return $cache[$key];
	}
	
	public function getDataTable()
	{
		return $this->getTable('data');
	}
	
	public function getDataTableWithAlias()
	{
		$alias = $this->getDataTableAlias();
		
		return $this->getDataTable() . ($alias? ' AS ' . $alias: '');
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
	public function getDataTableAlias() {
		return $this->tabAlias;
	}

	public function setDataTableAlias($tabAlias) {
		$this->tabAlias = $tabAlias;
		
		return $this;
	}
	
	public function getTable($key) {
		return $this->getTables()[$key]?? '';
	}
	
	public function getTables() {
		$tab = $this->getTab();
		
		return [
				'callback' => $tab . '_callback',
				'recent' => $tab . '_recent',
				'favorite' => $tab . '_favorite',
				'history_data' => $tab . '_edit_history_data',
				'history' => $tab . '_edit_history',
				'fields' => $tab . '_field',
				'data' => $tab . '_data_1'
		];
	}

	public function getId() {
		return $this->getProperty('id');
	}
	
	public function getUserFavouriteRecords() {
		return DB::GetCol('SELECT ' . $this->getTab() . '_id FROM ' . $this->getTab() . '_favorite WHERE user_id=%d', [Acl::get_user()]);
	}
	
	final public function clearSearchIndex()
	{
		if ($tab_id = DB::GetOne('SELECT id FROM recordbrowser_table_properties WHERE tab=%s', [$this->getTab()])) {
			DB::Execute('DELETE FROM recordbrowser_search_index WHERE tab_id=%d', [$tab_id]);
			DB::Execute('UPDATE ' . $this->getTable('data') . ' SET indexed=0');
			return true;
		}
		return false;
	}
}



