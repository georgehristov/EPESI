<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field implements IteratorAggregate, ArrayAccess, Countable {
	/**
	 * @var Utils_RecordBrowser_Recordset
	 */
	protected $recordset;
	protected $formElement = null;
	protected $desc = [];
	protected static $registry = [];
	protected static $special = [];
	
	public static function typeKey() {
		return static::class;
	}
	
	public static function typeLabel() {
		return false;
	}
	
	/**
	 * @param string|Utils_RecordBrowser_Recordset
	 * @param string|array $name_or_desc
	 * @param boolean $admin
	 * @return Utils_RecordBrowser_Recordset_Field
	 */
	public static function create($recordset, $name_or_desc = null, $admin = false) {
		$recordset = Utils_RecordBrowser_Recordset::create($recordset);
		
		$desc = !is_array($name_or_desc)? static::desc($recordset->getTab(), $name_or_desc): $name_or_desc;
		
		if (!$desc) return [];
		
		$desc = self::resolveDesc($desc);
		
		if (!$class = self::resolveClass($desc)) return [];

		return new $class($recordset, $desc);
	}
	
	public function __construct($recordset, $desc = null) {
		$this->setRecordset($recordset);

		$this->setDesc($desc);
	}
		
	public static function resolveClass($desc) {
		return self::$registry[$desc['type']]?? (self::$special[$desc['type']]?? static::class);
	}
	
	public static function getRegistrySelectList() {
		$ret = [];
		
		foreach (self::$registry as $type => $class) {
			$ret[$type] = $class::typeLabel();
		}
		
		return array_filter($ret);
	}
		
	public static function paramElements() {
		return [];
	}
		
	/**
	 * @param array $desc
	 * @return array
	 */
	public static function resolveDesc($desc) {
		foreach (array_keys($desc) as $id)
			if (is_numeric($id)) unset($desc[$id]);
			
		$desc['pkey'] = self::getFieldId($desc['id']);
		$desc['id'] = self::getFieldId($desc['field']);
		$desc['name'] = str_replace('%', '%%', $desc['caption']?: $desc['field']);
			
		return self::resolveDescBackwardsCompatible($desc);
	}
	
	/**
	 * @param array $desc
	 * @return array
	 */
	public static function resolveDescBackwardsCompatible($desc) {
		$ret = [];
		$commondata = $desc['type'] == 'commondata';
		if (($desc['type']=='select' || $desc['type']=='multiselect') && $desc['param']) {
			$pos = strpos($desc['param'], ':');
			$ret['ref_table'] = substr($desc['param'], 0, $pos);
			if ($ret['ref_table']=='__COMMON__') {
				$ret['ref_field'] = '__COMMON__';
				$exploded = explode('::', $desc['param']);
				$ret['commondata_array'] = $ret['ref_table'] = $exploded[1];
				$ret['commondata_order'] = isset($exploded[2]) ? $exploded[2] : 'value';
				$ret['type'] = 'multicommondata';
				$commondata = true;
			} else {
				$end = strpos($desc['param'], ';', $pos+2);
				if ($end==0) $end = strlen($desc['param']);
				$ret['ref_field'] = substr($desc['param'], $pos+2, $end-$pos-2);
			}
		}
		$ret['commondata'] = $commondata;
		if ($commondata) {
			$param = Utils_RecordBrowser_Recordset_Field_CommonData::decodeParam($desc['param']);
			if (!isset($ret['commondata_order'])) {
				if (isset($param['order'])) {
					$ret['commondata_order'] = $param['order'];
				} else {
					$ret['commondata_order'] = 'value';
				}
			}
			if (!isset($ret['commondata_array'])) {
				$ret['commondata_array'] = $param['array_id'];
			}
		}
			
		return array_merge($desc, $ret);
	}
		
	public static final function register($type_or_list = null) {
		$type_or_list = $type_or_list?: static::class;
		
		foreach (is_array($type_or_list)? $type_or_list: [$type_or_list] as $class) {
			if (!is_a($class, Utils_RecordBrowser_Recordset_Field::class, true)) {
				trigger_error("Attempting to register field $class that is not descendent of Utils_RecordBrowser_Recordset_Field", E_USER_ERROR);
			}
			
			$entry = [
					$class::typeKey() => $class
			];
			
			if ($class::typeLabel()) {
				self::$registry = array_merge(self::$registry, $entry);
			}
			else {
				self::$special = array_merge(self::$special, $entry);
			}			
		}
	}
	
	public static final function getSpecial() {
		return self::$special;
	}
	
	public final function isVisible() {
		return $this->visible;
	}
	
	public final function isRequired() {
		return $this->required;
	}
	
	public function isRequiredPossible() {
		return true;
	}
	
	public static final function getFieldId($field_name) {
		return Utils_RecordBrowserCommon::get_field_id($field_name);
	}
	
	public final function getGridColumnOptions(Utils_RecordBrowser $recordBrowser, $disabled = []) {
		$column = array_filter(array_diff_key($this->gridColumnOptions($recordBrowser), array_filter($disabled)));
		
		$options = array_fill_keys(['quickjump', 'order', 'search'], $this->getId());
		
		return array_replace($column, array_intersect_key($options, $column));
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return [
				'name' => _V($this->getLabel()),
				'order' => true,
				'quickjump' => false,
				'search' => true,
				'search_type' => 'text',
				'wrapmode' => false,
				'width' => 100
		];
	}
	
	public function getActualDbType() {
		return Utils_RecordBrowserCommon::actual_db_type($this->getType(), $this->getParam());
	}
	
	public function getSqlType() {
		return Utils_RecordBrowserCommon::get_sql_type($this->getType());
	}
	
	public function getQueryId() {
		return implode('.', array_filter([$this->getRecordset()->getTabAlias(), $this->getSqlId()]));
	}
		
	/**
	 * The id to use when assigning value in the record array
	 *
	 * @return string
	 */
	public function getArrayId() {
		return $this->getId();
	}	
	
	public function getSqlId() {
		return 'f_' . $this->getId();
	}

	public function getSqlOrder($direction) {
		return ' ' . $this->getQueryId() . ' ' . $direction;
	}
	
	public function getLabel() {
		return _V($this->getCaption()?: $this->getName());
	}
	
	public function getQFfieldLabel() {
		return $this->getTooltip('<span id="_'.$this->getId().'__label">'.$this->getLabel().'</span>');
	}
	
	public static function decodeValue($value, $options = []) {
		return $value;
	}
	
	public static function encodeValue($value, $options = []) {
		return $value;
	}
	
	public static function decodeParam($param) {
		return $param;
	}
	
	public static function encodeParam($param) {
		return $param;
	}
	
	public static function formatParam($param) {
		return '';
	}
	
	public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit)
	{
		if ($crit->getValue()->isRawSql()) {
			return $this->getRawSQLQuerySection($crit);
		}
		
		$field = $this->getQueryId();
		$operator = $crit->getSQLOperator();
		$value = $crit->getSQLValue();
		
		$vals = [];
		if ($operator == DB::like() && ($value == '%' || $value == '%%')) {
			$sql = 'true';
		} elseif (!$value) {
			$sql_null = stripos($operator, '!') !== false? 'NOT': '';
			
			$sql = "$field IS $sql_null NULL OR $field $operator ''";
		} else {
			$sql = "$field $operator %s AND $field IS NOT NULL";
			$vals[] = $value;
		}
		
		return $this->getRecordset()->createQuery($sql, $vals);
	}
	
	public function getRawSQLQuerySection(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit) {
		$operator = $crit->getSQLOperator();
		$value = $crit->getSQLValue();
		
		return $this->getRecordset()->createQuery($this->getQueryId() . " $operator $value");
	}
	
	public final function createQFfield($form, $mode, $record, $custom_defaults, $rb_obj) {
		if ($mode == 'view' && Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(),'hide_empty') && $this->isEmpty($record)) {
			eval_js('var e=jq("#_'.$this->getId().'__data");if(e.length)e.closest("tr").hide();');
		}
				
		$default = ($mode=='add')? ($custom_defaults[$this->getId()]?? ''): ($record[$this->getId()]?? '');

		$this->callQFfieldCallback($form, $mode, $default, $rb_obj);
				
		$this->formElement = $form->getElement($this->getId());
		
		if ($this->isRequired()) {
			$el = $form->getElement($this->getId());
			if (!$form->isError($el)) {
				if ($el->getType() != 'static') {
					$form->addRule($this->getId(), __('Field required'), 'required');
					$el->setAttribute('placeholder', __('Field required'));
				}
			}
		}
	}
	
	public function validate($values, Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits) {
		$value = $this->decodeValue($values[$this->getId()] ?? '', false);
		$crit_value = $crits->getValue()->getValue();
		
		$result = false;
		if (is_array($value)) {
			$result = $crit_value? in_array($crit_value, $value): empty($value);

			if ($crits->getOperator()->getOperator() == '!=') $result = !$result;
		}
		else switch ($crits->getOperator()->getOperator()) {
			case '>': $result = ($value > $crit_value); break;
			case '>=': $result = ($value >= $crit_value); break;
			case '<': $result = ($value < $crit_value); break;
			case '<=': $result = ($value <= $crit_value); break;
			case '!=': $result = ($value != $crit_value); break;
			case '=': $result = ($value == $crit_value); break;
			case 'LIKE': $result = $this->checkLikeMatch($value, $crit_value); break;
			case 'NOT LIKE': $result = !$this->checkLikeMatch($value, $crit_value); break;
		}

		return $result;
	}
	
	protected final static function checkLikeMatch($value, $pattern, $ignoreCase = true)
	{
		$pattern = preg_quote($pattern);
		$pattern = str_replace(['_', '%', '/'], ['.', '.*', '\/'], $pattern);
		$pattern = "/^$pattern\$/" . ($ignoreCase ? "i" : "");
		
		return preg_match($pattern, $value) > 0;
	}
	
	public function isEmpty($record) {
		if (is_array($record[$this->getId()])) return empty($record[$this->getId()]);
		
		return $record[$this->getId()]=='';
	}
	
	public function callQFfieldCallback($form, $mode, $default, $rb_obj) {
		$callback = is_callable($this->QFfield_callback)? $this->QFfield_callback: [$this, 'defaultQFfieldCallback'];
		
		if (!is_callable($callback)) return;

		$callback($form, $this->getId(), $this->getQFfieldLabel(), $mode, $default, $this, $rb_obj, []);
	}
	
	public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {}
		
	public static function createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if ($mode !== 'add' && $mode !== 'edit') {
			$field = $desc->getId();
			
			$value = Utils_RecordBrowserCommon::get_val($desc->getTab(), $field, $rb_obj->record, false, $desc);
			$form->addElement('static', $field, $desc->getQFfieldLabel(), $value, ['id' => $field]);
			return true;
		}
		return false;
	}
	
	/**
	 * @param Utils_RecordBrowser_Recordset_Record $record
	 * @param boolean $nolink
	 * @return string
	 *  */
	public final function display($record, $nolink=false) {
		static $recurrence = [];

		if(!isset($record['id'])) $record['id'] = null;
		if (!isset($record[$this['id']])) trigger_error($this['id'].' - unknown field for record '.print_r($record->toArray(), true), E_USER_ERROR);
		
		$val = $record[$this['id']];
		
		$function_call_id = implode('|', [$this->getTab(), $this['id'], serialize($val)]);
		if (isset($recurrence[$function_call_id])) {
			return '!! ' . __('recurrence issue') . ' !!';
		} else {
			$recurrence[$function_call_id] = true;
		}
		
		$callback = is_callable($this->display_callback)? $this->display_callback: [$this, 'defaultDisplayCallback'];
		
		$ret = $callback($record, $nolink, $this, $this->getTab());
		
		unset($recurrence[$function_call_id]);

		return $ret;
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return is_array($record[$desc['id']])? implode('<br />', $record[$desc['id']]): $record[$desc['id']];
	}
	
	public final function getTooltip($label = null) {
		$label = $label?: $this->getLabel();
		
		if(strpos($label,'Utils_Tooltip')!==false) return $label;
		$args = func_get_args();
		array_shift($args);
		array_unshift($args, $this->getType());
		return Utils_TooltipCommon::ajax_create($label, [static::class, 'getAjaxTooltip'], [$this->getAjaxTooltipOpts()]);
	}
	
	public function getAjaxTooltipOpts() {
		return [];
	}
	
	public static function getAjaxTooltip($opts) {
		return __('No additional information');
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function getId() {
		return $this->id;
	}
	
	public function getPkey() {
		return $this->pkey;
	}
	
	public function getType() {
		return $this->type;
	}
	
	public function getVisible() {
		return $this->visible;
	}
	
	public function getRequired() {
		return $this->required;
	}
	
	public function getExtra() {
		return $this->extra;
	}
	
	public function getActive() {
		return $this->active;
	}
	
	public function getExport() {
		return $this->export;
	}
	
	// 	public function getTooltip() {
	// 		return $this->tooltip;
	// 	}
	
	public function getPosition() {
		return $this->position;
	}
	
	public function getProcessingOrder() {
		return $this->processing_order;
	}
	
	public function getFilter() {
		return $this->filter;
	}
	
	public function getStyle() {
		return $this->style;
	}
	
	public function getParam($key = null) {
		return $key? ($this->param[$key]?? null): $this->param;
	}
	
	public function getHelp() {
		return $this->help;
	}
	
	public function getTemplate() {
		return $this->template;
	}
	
	public function getDisplayCallback() {
		return $this->display_callback;
	}
	
	public function getQFfieldCallback() {
		return $this->QFfield_callback;
	}
	
	public function getTab() {
		return $this->getRecordset()->getTab();
	}
	
	public function setName($name) {
		$this->name = $name;
		
		return $this;
	}
	
	public function setId($id) {
		$this->id = $id;
		
		return $this;
	}
	
	public function setPkey($pkey) {
		$this->pkey = $pkey;
		
		return $this;
	}
	
	public function setType($type) {
		$this->type = $type;
		
		return $this;
	}
	
	public function setVisible($visible) {
		$this->visible = $visible;
		
		return $this;
	}
	
	public function setRequired($required = true) {
		$this->required = $required;
		
		return $this;
	}
	
	public function setExtra($extra) {
		$this->extra = $extra;
		
		return $this;
	}
	
	public function setActive($active) {
		$this->active = $active;
		
		return $this;
	}
	
	public function setExport($export) {
		$this->export = $export;
		
		return $this;
	}
	
	public function setTooltip($tooltip) {
		$this->tooltip = $tooltip;
		
		return $this;
	}
	
	public function setPosition($position) {
		$this->position = $position;
		
		return $this;
	}
	
	public function setProcessingOrder($processing_order) {
		$this->processing_order = $processing_order;
		
		return $this;
	}
	
	public function setFilter($filter) {
		$this->filter = $filter;
		
		return $this;
	}
	
	public function setStyle($style) {
		$this->style = $style;
		
		return $this;
	}
	
	public function setParam($param) {
		$this->param = $param;
		
		return $this;
	}
	
	public function setHelp($help) {
		$this->help = $help;
		
		return $this;
	}
	
	public function setTemplate($template) {
		$this->template = $template;
		
		return $this;
	}
	
	public function setDisplay_callback($display_callback) {
		$this->display_callback = $display_callback;
		
		return $this;
	}
	
	public function setQFfield_callback($QFfield_callback) {
		$this->QFfield_callback = $QFfield_callback;
		
		return $this;
	}
	
	public function getCaption() {
		return $this->caption;
	}
	
	public function setCaption($caption) {
		$this->caption = $caption;
		
		return $this;
	}
	
	public function defaultValue($mode) {
		return '';
	}
	
	public function process($values, $mode, $options = []) {
		$callback = [$this, 'process' . ucfirst($mode)];

		return is_callable($callback)? call_user_func($callback, $values, $options): $values;
	}
	
	/**
	 * Prepare the field value for saving to the database
	 * Return true if value should be included for inserting and false otherwise
	 */
	public function processAdd($values, $options = []) {
		$value = $this->encodeValue($values[$this->getId()]);
		
		$values[$this->getId()] =  is_bool($value)? ($value? 1: 0): $value;
		
		return $values;
	}
	
	public function processAdded($values, $options = []) {
		$values[$this->getId()] =  $this->decodeValue($values[$this->getId()], $options);
		
		return $values;
	}
	
	/**
	 * Process values retrieved from database
	 * $values has sqlId keys
	 * 
	 * @param array $values
	 * @return array
	 */
	public function processGet($values, $options = []) {
		$sqlId = $this->getSqlId();

		$value = $sqlId && isset($values[$sqlId])? $values[$sqlId]: $this->defaultValue('view');
				
		return [
				$this->getArrayId() => $this->decodeValue($value, $options)
		];	
	}
	
	public function getFormElement() {
		return $this->formElement;
	}
	
	public function getRecordset() {
		return $this->recordset;
	}

	protected function setRecordset($recordset) {
		$this->recordset = Utils_RecordBrowser_Recordset::create($recordset);
		
		return $this;
	}
	
	protected function setDesc($desc) {
		$desc = is_string($desc)? $this->desc($this->getRecordset()->getTab(), $desc): $desc;

		$descDefault = [
				'name' => null,
				'field' => null,
				'id' => null,
				'caption' => null,
				'pkey' => null,
				'type' => null,
				'visible' => null,
				'required' => null,
				'extra' => null,
				'active' => null,
				'export' => null,
				'tooltip' => null,
				'position' => null,
				'processing_order' => null,
				'filter' => null,
				'style' => null,
				'param' => null,
				'help' => null,
				'template' => null,
				'display_callback' => null,
				'QFfield_callback' => null,
				'tab' => null,
				//---> deprecated - backward compatibility
				'ref_table' => null,
				'ref_field' => null,
				'commondata' => false,
				'commondata_array' => null,
				'commondata_order' => null,
				//<--- deprecated
		];
		
		$desc = array_intersect_key(array_merge($descDefault,$this->resolveDesc($desc)), $descDefault);
		
		$desc['param'] = $this->decodeParam($desc['param']);
				
		if (!$this->isRequiredPossible())
			$desc['required'] = false;
		
		$this->desc = $desc;
		
		return $this;
	}
	
	public static function desc($tab = null, $name = null) {
		return $tab && $name? DB::GetRow("SELECT * FROM {$tab}_field  WHERE field=%s", [$name]): [];
	}
	
	// Interface methods
	public function count() {
		return count($this->desc);
	}
	
	public function offsetExists($offset) {
		return array_key_exists($offset, $this->desc);
	}
	
	public function offsetGet($offset) {
		return $this->desc[$offset]?? null;
	}
	
	public function offsetSet($offset, $value) {
		$this->desc[$offset] = $value;
	}
	
	public function offsetUnset($offset) {
		unset($this->desc[$offset]);
	}
	
	public function getIterator() {
		return new ArrayIterator($this->desc);
	}
	
	public function __get($property) { 
		return $this[$property]?? null; 
	}
}