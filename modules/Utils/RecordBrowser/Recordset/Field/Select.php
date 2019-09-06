<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Select extends Utils_RecordBrowser_Recordset_Field {
	public static $options_limit = 50;
	protected $single_tab = false;
	protected $record_count = 0;
	
	public static function typeKey() {
		return 'select';
	}
	
	public static function typeLabel() {
		return _M('Select');
	}
	
	public static function paramElements() {
		Libs_QuickFormCommon::autohide_fields('source_type', [
				'values' => '__RECORDSETS__',
				'hide' => 'source'
		]);
		
		return [
				'source_type' => [
						'type' => 'select',
						'label' => _M('Source Type'),
						'values' => [
								null => __('Static Recordsets'),
 								'__RECORDSETS__' => __('Dynamic Recordsets')
						],
						'help' => __('Sources are defined dynamically in the crits callback or selected static here')
				],
				'source' => [
						'type' => 'select',
						'label' => _M('Source of Data'),
						'values' => Utils_RecordBrowserCommon::list_installed_recordsets(),
						'help' => __('Sources are defined in the crits callback')
				],
				'crits_callback' => [
						'type' => 'text',
						'label' => _M('Crits Callback'),
						'help' => __('Crits callback method to limit the selection')
				],
				'advanced_callback' => [
						'type' => 'text',
						'label' => _M('Advanced Callback'),
						'help' => __('Advanced callback method to define selection formatting, etc')
				],
		];
	}
	
	function __construct($recordset, $desc) {
		parent::__construct($recordset, $desc);
		
		$this->single_tab = $this['param']['single_tab'];
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'order' => $this->single_tab? true: false,
				'quickjump' => true,
		]);
	}
	
	public function processAdd($values) {
		$value = $this->encodeValue($values[$this->getId()]);
		
		//---> backward compatibility
		$values[$this->getId()] = str_replace(['P:', 'C:'], ['contact/', 'company/'], $value);
		//<--- backward compatibility
		
		return $values;
	}
	
	public function getSelectOptions($record=null) {
		$ret = array();
		
		$multi_adv_params = $this->callAdvParamsCallback($record);
		$tab_crits = $this->getSelectTabCrits($record);
		
		$tabs = array_keys($tab_crits);
		
		foreach($tabs as $t) {
			$this->record_count += Utils_RecordBrowserCommon::get_records_count($t, $tab_crits[$t]);
			
			if ($this->record_count > self::$options_limit) break;
		}
		if ($this->record_count <= self::$options_limit) {
			foreach($tabs as $t) {
				$records = Utils_RecordBrowserCommon::get_records($t, $tab_crits[$t], array(), $multi_adv_params['order']);
				foreach($records as $key=>$rec) {
					if(!Utils_RecordBrowserCommon::get_access($t,'view',$rec)) continue;
					$tab_id = ($this->param['single_tab']?'':$t.'/').$key;
					$ret[$tab_id] = self::callSelectItemFormatCallback($multi_adv_params['format_callback'], $tab_id, array($this->getTab(), $tab_crits[$t], $multi_adv_params['format_callback'], $this->param));
				}
			}
		}
		
		if (isset($record[$this->getId()])) {
			foreach ($record[$this->getId()] as $tab_id) {
				if (isset($ret[$tab_id])) continue;
				$vals = Utils_RecordBrowserCommon::decode_record_token($tab_id, $this->param['single_tab']);
				if (!$vals) continue;
				list($t,) = $vals;
				$ret[$tab_id] = self::callSelectItemFormatCallback($multi_adv_params['format_callback'], $tab_id, array($this->getTab(), $tab_crits[$t], $multi_adv_params['format_callback'], $this->param));
			}
		}
		if (empty($multi_adv_params['order']))
			natcasesort($ret);
			
		return $ret;
	}
	
	public function callAdvParamsCallback($record=null) {
		static $cache = null;
		
		$key = md5(serialize($record));
		
		if (isset($cache[$key])) return $cache[$key];
		
		$callback = $this->param['adv_params_callback'];
		
		$ret = array(
				'order'=>array(),
				'cols'=>array(),
				'format_callback'=>array(__CLASS__, 'autoselect_label')
		);
		
		$adv_params = array();
		if (is_callable($callback))
			$adv_params = call_user_func($callback, $record);
			
		if (!is_array($adv_params))
			$adv_params = array();
				
		return $cache[$key] = array_merge($ret, $adv_params);
	}
	
	public static function callSelectItemFormatCallback($callback, $tab_id, $args) {
		//     	$args = array($tab, $tab_crits, $format_callback, $params);
		
		$param = self::decodeParam($args[3]);
		
		$val = Utils_RecordBrowserCommon::decode_record_token($tab_id, $param['single_tab']);
		
		if (!$val) return '';
		
		list($tab, ) = $val;
		
		$tab_caption = '';
		if (!$param['single_tab']) {
			$tab_caption = Utils_RecordBrowserCommon::get_caption($tab);
			
			$tab_caption = '[' . ((!$tab_caption || $tab_caption == '---')? $tab: $tab_caption) . '] ';
		}
		
		$callback = is_callable($callback)? $callback: array('Utils_RecordBrowserCommon', 'autoselect_label');
		
		return $tab_caption . call_user_func($callback, $tab_id, $args);
	}
	
	public function getSelectTabCrits($record=null) {
		static $cache = null;
		
		$key = md5(serialize($record));
		
		if (isset($cache[$key])) return $cache[$key];
		
		$param = $this->param;
		
		$ret = array();
		if (is_callable($param['crits_callback']))
			$ret = call_user_func($param['crits_callback'], false, $record);
			
			$tabs = $param['select_tabs'];
			
			$ret = !empty($ret)? $ret: array();
			if ($param['single_tab'] && !isset($ret[$param['single_tab']])) {
				$ret = array($param['single_tab'] => $ret);
			}
			elseif(is_array($ret) && !array_intersect($tabs, array_keys($ret))) {
				$tab_crits = array();
				foreach($tabs as $tab)
					$tab_crits[$tab] = $ret;
					
					$ret = $tab_crits;
			}
			
			foreach ($ret as $tab=>$crits) {
				if (!$tab || !Utils_RecordBrowserCommon::check_table_name($tab, false, false)) {
					unset($ret[$tab]);
					continue;
				}
				$access = Utils_RecordBrowserCommon::get_access($tab, 'selection', null, true);
				if ($access===false) unset($ret[$tab]);
				if ($access===true) continue;
				if (is_array($access) || $access instanceof Utils_RecordBrowser_Recordset_Query_Crits) {
					if((is_array($crits) && $crits) || $crits instanceof Utils_RecordBrowser_Recordset_Query_Crits)
						$ret[$tab] = Utils_RecordBrowserCommon::merge_crits($crits, $access);
					else
						$ret[$tab] = $access;
				}
			}
			
			return $cache[$key] = $ret;
	}
	
	public static function decodeParam($param) {
		if (is_array($param)) return $param;
		
		$param = explode(';', $param);
		$reference = explode('::', $param[0]);
		$crits_callback = isset($param[1])? array_filter(explode('::', $param[1])): null;
		$adv_params_callback = isset($param[2])? explode('::', $param[2]): null;
		
		$select_tab = $reference[0];
		$cols = isset($reference[1])? array_map([__CLASS__, 'getFieldId'], array_filter(explode('|', $reference[1]))): null;
		
		if ($select_tab == '__RECORDSETS__') {
			$select_tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties');
			$single_tab = false;
		}
		else {
			$select_tabs = array_filter(explode(',',$select_tab));
			$single_tab = count($select_tabs)==1? $select_tab: false;
		}
		
		return [
				'single_tab'=>$single_tab? $select_tab: false, //returns single tab name or false
				'select_tabs'=>$select_tabs, //returns array of tab names
				'cols'=>$cols, // returns array of columns for formatting the display value (used in case RB records select)
				'crits_callback'=>$crits_callback, //returns crits callback (used in case RB records select)
				'adv_params_callback'=>$adv_params_callback //returns adv_params_callback (used in case RB records select)
		];
	}
	public static function encodeParam($param) {
		$param = is_array($param)? $param: [$param];
			
		$order = 'value';
		if (isset($param['order']) || isset($param['order_by_key'])) {
			$order = Utils_CommonDataCommon::validate_order(isset($param['order'])? $param['order']: $param['order_by_key']);
				
			unset($param['order']);
			unset($param['order_by_key']);
		}
			
		$array_id = implode('::', $param);
			
		return implode('__', [$order, $array_id]);
	}
	
	public function getSqlOrder($direction) {

		$tab2 = $this['param']['single_tab'];
		
		$val = $this->getQueryId();
		// search for better sorting than id
		if ($tab2) {
			$recordset2 = Utils_RecordBrowser_Recordset::create($tab2)->setDataTableAlias('rdt');
			$cols2 = $this['param']['cols'];
			
			foreach ($cols2 as $referenced_col) {
				if ($field2 = $recordset2->getField($referenced_col, true)) {
					if ($field2->getQueryId()) {
						$val = '(SELECT ' . $field2->getQueryId() . ' FROM ' . $recordset2->getTab() . '_data_1 AS rdt WHERE rdt.id='.$this->getQueryId().')';
						break;
					}
				}
			}
		}
		
		return ' ' . $val . ' ' . $direction;
	}
	
	public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit) {
		$param = $this->getParam();
		$value = $crit->getValue()->getValue();
		$operator = $crit->getOperator()->getOperator();

		$field = $this->getQueryId();
		
		$subfield = $crit->getKey()->getSubfield();

		//if using LIKE operator and no subfield then look into the default subfields
		if (!$subfield && $operator == DB::like() && $param['cols']) {
			$subfield = implode('|', $param['cols']);
		}

		$tab2 = $param['single_tab'];
		
		$query = $this->getRecordset()->createQuery('false');
		if ($this->decodePlaceholderCallback($value)) {
			$handled_with_php = $this->getRecordset()->createQuery();//'true');
			
			if (!$tab2) return $handled_with_php;
			
			$access_crits = $this->validate($crit, null);
			
			if (!$access_crits instanceof Utils_RecordBrowser_Recordset_Query_Crits) {
				return $handled_with_php;
			}
			
			$query = $this->getSubQuery($tab2, $access_crits);
		} else if ($subfield && $tab2 && $tab2 != $this->getTab()) {
			$crits = [];
			foreach (explode('|', $subfield) as $col) {
				if (! $col = $col[0] == ':' ? $col : self::getFieldId(trim($col))) continue;
				
				$crits[] = Utils_RecordBrowser_Recordset_Query_Crits_Basic::create($col, clone $crit->getValue(), clone $crit->getOperator());
			}

			$query = $this->getSubQuery($tab2, Utils_RecordBrowser_Crits::create($crits, true));
		} else {
			if ($crit->getValue()->isRawSql()) {
				$sql = "$field $operator $value";
			} elseif (! $value) {
				$sql_null = stripos($operator, '!') !== false? 'NOT': '';
				
				$sql = "$field IS $sql_null NULL";
				if (!$tab2) {
					$sql .= " OR $field $operator ''";
				}
				
				return $this->getRecordset()->createQuery($sql);				
			} else {
				if ($tab2 && $operator != DB::like()) {
					$operand = '%d';
					$value = $this->stripToken($value);
				} else {
					if (DB::is_postgresql()) {
						$field .= '::varchar';
					}
					$operand = '%s';
				}
				
				$query = $this->getDefaultQuery($operator, $operand, $value);
			}
		}

		$sql = $query->getSql();

		return $this->getRecordset()->createQuery($sql? "$field IS NOT NULL AND $sql": '', $query->getValues());
	}
	
	public function getDefaultQuery($operator, $operand, $value) {
		return $this->getRecordset()->createQuery("{$this->getSqlId()} $operator $operand", [$value]);
	}
	
	public function getSubQuery($recordset, $crits) {
		if ($crits->isEmpty()) return $this->getRecordset()->createQuery();

		$query = Utils_RecordBrowser_Recordset::create($recordset)->setDataTableAlias('sub')->getQuery($crits);

		return $this->getRecordset()->createQuery("{$this->getQueryId()} IN ({$query->getSelectIdSql()})", $query->getValues());
	}
	
	public function getSearchCrits($word) {
		$ret = [];
		foreach ($this['param']['cols'] as $fieldId ) {
			$ret[] = [
					"~{$this->getId()}[$fieldId]" => "%$word%"
			];
		}
		
		return Utils_RecordBrowser_Crits::create($ret, true);
	}
	
	public function getAjaxTooltipOpts() {
		return [
				'tabCrits' => $this->getSelectTabCrits()
		];
	}
	
	public static function getAjaxTooltip($opts) {
		$ret = '';
		foreach ($opts['tabCrits']?? [] as $tab => $crits) {
			$recordset = Utils_RecordBrowser_Recordset::create($tab);

			$ret .= '<b>' . $recordset->getCaption() . '</b>';
			
			if ($critsWords = Utils_RecordBrowser_Crits::create($crits)->toWords($recordset)) {
				$ret .= ' ' . __('for which') . '<br />&nbsp;&nbsp;&nbsp;' . $critsWords;
			}
		}

		return __('Select one') . ($ret? ' ' . __('of') . ' ' . $ret: '');
	}

	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		$ret = '---';

		if (!isset($desc['id'])) return $ret;
		
		if (!$val = $record[$desc['id']]?? '') return $ret;

		$param = $desc['param'];
		
		$ret = [];
		foreach (is_array($val)? $val: [$val] as $v) {	
			$tab_id = Utils_RecordBrowserCommon::decode_record_token($v, $param['single_tab']);

			if (!$tab_id) continue;
			
			list ($select_tab, $id) = $tab_id;
			
			if ($param['cols']) {
				$res = Utils_RecordBrowserCommon::create_linked_label($select_tab, $param['cols'], $id, $nolink);
			} else {
				$res = Utils_RecordBrowserCommon::create_default_linked_label($select_tab, $id, $nolink);
			}
				
			$ret[] = $res;
		}
		
		return implode('<br>', $ret);
	}

	public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
		
		// --->backward compatibility
		switch ($desc->getType()) {
			case 'multiselect' :
				Utils_RecordBrowser_Recordset_Field_MultiSelect::defaultQFfieldCallback($form, $desc, $mode, $default, $rb_obj);
				return;
			case 'multicommondata' :
				Utils_RecordBrowser_Recordset_Field_MultiCommonData::defaultQFfieldCallback($form, $desc, $mode, $default, $rb_obj);
				return;

			default :
				break;
		}
		// <---backward compatibility

		if (! $desc instanceof Utils_RecordBrowser_Recordset_Field_Select) return;

		$field = $desc->getId();
		$label = $desc->getQFfieldLabel();

		$record = $rb_obj->record;
		$param = $desc->getParam();
		$multi_adv_params = $desc->callAdvParamsCallback($record);
		$format_callback = $multi_adv_params['format_callback'];

		$tab_crits = $desc->getSelectTabCrits($record);
		$select_options = $desc->getSelectOptions($record);

		if ($param['single_tab']) $label = $desc->getTooltip($label, $param['single_tab'], $tab_crits[$param['single_tab']]);

		if ($desc->record_count > Utils_RecordBrowser_Recordset_Field_Select::$options_limit) {
			$form->addElement('autoselect', $field, $label, $select_options, array(
					array(
							'Utils_RecordBrowserCommon',
							'automulti_suggestbox'
					),
					array(
							$rb_obj->tab,
							$tab_crits,
							$format_callback,
							$desc['param']
					)
			), $format_callback);
		}
		else {
			$select_options = array(
					'' => '---'
			) + $select_options;
			$form->addElement('select', $field, $label, $select_options, array(
					'id' => $field
			));
		}
		if ($mode !== 'add') $form->setDefaults(array(
				$field => $default
		));
	} 
	
	public function validate(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits, $values) {
		if ($callback = $this->decodePlaceholderCallback($crits->getValue()->getValue())) {
			return is_callable($callback['func'])? call_user_func_array($callback['func'], array_merge([$this, $values], $callback['args'])): true;
		}
		
		$values = $this->decodeValue($values, false);

		if ($subfield = $crits->getKey()->getSubfield()) {
			if ($tab2 = $this->getParam('single_tab')) {
				$checkCrits = Utils_RecordBrowser_Recordset_Query_Crits_Basic::create($subfield, $crits->getValue(), $crits->getOperator());

				foreach (is_array($values)? $values: [$values] as $value) {
					$issues = Utils_RecordBrowser_Recordset::create($tab2)->findOne($value)->validate($checkCrits);
					
					if (!$issues) return true;
				}
				
				return false;
			}
		}

		$critsCheck = clone $crits;
		
		// remove prefix for select from single tab: contact/1 => 1
		if (preg_match('/^[0-9-]+$/', is_array($values)? reset($values): $values)) {
			$crit_value = $this->stripToken($critsCheck->getValue()->getValue());
			
			$critsCheck->getValue()->setValue($crit_value);
		}
		
		return parent::validate($critsCheck, $values);
	}
	
	public static function encodePlaceholderCallback($callback, $args = []) {
		$callback = is_array($callback)? implode('::', $callback): $callback;
		
		return implode('|', array_merge(['__CALLBACK__', $callback], $args));
	}
	
	public static function decodePlaceholderCallback($value) {
		$value = explode('|', $value);
			
		if ($value[0] != '__CALLBACK__') return;
			
		array_shift($value);
		
		$func = array_shift($value);
		$args = $value;
			
		return compact('func', 'args');
	}
	
	public function toWords(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits, $asHtml = true) {
		$subquery = false;
		
		$tab2 = $this->getParam('single_tab');
		
		$subfield = $crits->getKey()->getSubfield();
		
		//if using LIKE operator and no subfield then look into the default subfields
		if (!$subfield && $crits->getOperator()->getOperator() == DB::like() && $this['param']['cols']) {
			$subfield = implode('|', $this['param']['cols']);
		}
		
		if ($subfield && $tab2) {
			$crits2 = [];
			foreach ( explode('|', $subfield) as $col ) {
				if (! $col = $col[0] == ':' ? $col: self::getFieldId(trim($col))) continue;

				$crits2[] = Utils_RecordBrowser_Recordset_Query_Crits_Basic::create($col, clone $crits->getValue(), clone $crits->getOperator());
			}

			$value = Utils_RecordBrowser_Crits::create($crits2, true)->toWords($tab2, $asHtml);

			$subquery = true;
		}

		$key = $this->getLabel();
		$value = $subquery? $value: $this->getValueToWords($crits->getValue()->getValue());;
		$operand = $subquery? __('is set to record where'): $crits->getOperator()->toWords();

		if ($asHtml) {
			$key = "<strong>$key</strong>";
			
			$value = $subquery? $value: '<strong>' . $value . '</strong>';
		}
		$ret = "{$key} {$operand} {$value}";
		
		return $asHtml? $ret: html_entity_decode($ret);
	}
	
	private function stripToken($token) {
		$id = preg_replace('#.*/#', '', $token);
		
		return is_numeric($id)? $id: 0;
	}
	
	public function queryBuilderFilters($opts = []) {
		if (! $tab = $this['param']['single_tab']) return;
		
		//TODO: Georgi hristov introduce select2 as plugin and select options
		$filters = [
				[
						'id' => $this->getId(),
						'field' => $this->getId(),
						'label' => $this->getLabel() . ' (' . __('selection') . ')',
						'type' => 'boolean',
						'input' => 'select',
						'values' => [
								'' => '[' . __('Empty') . ']'
						],
// 						'plugin' => 'select2',
// 						'plugin_config' => [
// 							'data' => ["abc", "xyz"]
// 						],
				]
		];

		if ($opts['godeep']?? true) {
			$prefix =  __('%s is set to record where', [$this->getLabel()]) . ' ';
			
			foreach (Utils_RecordBrowser_Recordset::create($tab)->getFields() as $field) {
				if (! $field->isStored()) continue;

				if (!$subfilter = $field->queryBuilderFilters(['godeep' => false])) continue;
				
				$subfilter = reset($subfilter);

				$filters[] = array_merge($subfilter, [
						'id' => $this->getId() . '[' . $subfilter['id'] . ']',
						'field' => $this->getId() . '[' . $subfilter['field'] . ']',
						'label' => $prefix . $subfilter['label'],
						'optgroup' => $prefix
				]);
			}
		}
		
		return $filters;
		
	}
}
