<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Select extends Utils_RecordBrowser_Recordset_Field {
	public static $options_limit = 50;
	protected $multiselect = false;
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
	
	function __construct($array) {
		parent::__construct($array);
		
		$this->single_tab = $this->param['single_tab'];
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
		
		return array(
				'single_tab'=>$single_tab? $select_tab: false, //returns single tab name or false
				'select_tabs'=>$select_tabs, //returns array of tab names
				'cols'=>$cols, // returns array of columns for formatting the display value (used in case RB records select)
				'crits_callback'=>$crits_callback, //returns crits callback (used in case RB records select)
				'adv_params_callback'=>$adv_params_callback //returns adv_params_callback (used in case RB records select)
		);
	}
	public static function encodeParam($param) {
		if (!is_array($param))
			$param = array($param);
			
			$order = 'value';
			if (isset($param['order']) || isset($param['order_by_key'])) {
				$order = Utils_CommonDataCommon::validate_order(isset($param['order'])? $param['order']: $param['order_by_key']);
				
				unset($param['order']);
				unset($param['order_by_key']);
			}
			
			$array_id = implode('::', $param);
			
			return implode('__', array($order, $array_id));
	}
	
	public function getSqlOrder($direction) {

		$tab2 = $this['param']['single_tab'];
		$cols2 = $this['param']['cols'];
		$val = $this->getQueryId();
		$fields2 = Utils_RecordBrowserCommon::init($tab2);
		// search for better sorting than id
		if ($fields2) {
			foreach ($cols2 as $referenced_col) {
				if (isset($fields2[$referenced_col])) {
					$desc2 = $fields2[$referenced_col];
					if ($desc2['type'] != 'calculated' || $desc2['param'] != '') {
						$field_id2 = Utils_RecordBrowserCommon::get_field_id($referenced_col);
						$val = '(SELECT rdt.f_'.$field_id2.' FROM '.$tab2.'_data_1 AS rdt WHERE rdt.id='.$this->getQueryId().')';
						break;
					}
				}
			}
		}
		
		return ' ' . $val . ' ' . $direction;
	}
	
	public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit) {
		$param = $this->getParam();
		
		$sql = '';
		$vals = [];
		list($field, $sub_field) = Utils_RecordBrowser_CritsSingle::parse_subfield($field);
		
		$field = $this->getQueryId();
		
		$tab2 = $param['single_tab'];
		
		if ($crit->getOperator()->getOperator() == DB::like() && isset($param['cols'])) {
			$sub_field = implode('|', $param['cols']);
		}
		
		$vv = explode('::', $value, 2);
		$ids = null;
		if(isset($vv[1]) && is_callable($vv)) {
			$handled_with_php = array('true', array());
			if (!$tab2) return $handled_with_php;
			$callbacks = array(
					'view' => 'Utils_RecordBrowserCommon::get_recursive_view',
					'edit' => 'Utils_RecordBrowserCommon::get_recursive_edit',
					'print' => 'Utils_RecordBrowserCommon::get_recursive_print',
					'delete' => 'Utils_RecordBrowserCommon::get_recursive_delete',
			);
			$action = null;
			foreach ($callbacks as $act => $c) {
				if (strpos($value, $c) !== false) {
					$action = $act;
					break;
				}
			}
			if (!$action) return $handled_with_php;
			
			$access_crits = Utils_RecordBrowserCommon::get_access($tab2, $action, null, true);
			$subquery = Utils_RecordBrowserCommon::build_query($tab2, $access_crits, $this->admin_mode);
			if ($subquery) {
				$ids = DB::GetCol("SELECT r.id FROM $subquery[sql]", $subquery['vals']);
			} else {
				$sql = 'false';
			}
		} else if ($sub_field && $tab2 && $tab2 != $this->getTab()) {
			$col2 = explode('|', $sub_field);
			$crits = new Utils_RecordBrowser_Crits();
			foreach ($col2 as $col) {
				$col = $col[0] == ':' ? $col : self::getFieldId(trim($col));
				if ($col) {
					$crits->_or(new Utils_RecordBrowser_CritsSingle($col, $operator, $value, false, $rawSql));
				}
			}
			if (!$crits->isEmpty()) {
				$subquery = Utils_RecordBrowserCommon::build_query($tab2, $crits, $this->admin_mode);
				if ($subquery) {
					$ids = DB::GetCol("SELECT r.id FROM $subquery[sql]", $subquery['vals']);
				} else {
					$sql = 'false';
				}
			}
		} else {
			if ($rawSql) {
				$sql = "$field $operator $value";
			} elseif (!$value) {
				$sql = "$field IS NULL";
				if (!$tab2 || $this->multiselect) {
					$sql .= " OR $field=''";
				}
			} else {
				if ($tab2 && !$this->multiselect && $operator != DB::like()) {
					$operand = '%d';
				} else {
					if (DB::is_postgresql()) {
						$field .= '::varchar';
					}
					$operand = '%s';
				}
				if ($this->multiselect) {
					$value = "%\\_\\_{$value}\\_\\_%";
					$operator = DB::like();
				}
				$sql = "($field $operator $operand AND $field IS NOT NULL)";
				$vals[] = $value;
			}
		}
		if ($ids) {
			if ($this->multiselect) {
				$q = array();
				foreach ($ids as $id) {
					$q[] = "$field LIKE '%\\_\\_$id\\_\\_%'";
				}
				$q = implode(' OR ', $q);
			} else {
				$q = implode(',', $ids);
				$q = "$field IN ($q)";
			}
			$sql = "($field IS NOT NULL AND ($q))";
		}
		return array($sql, $vals);
	}
	
	public function getAjaxTooltipOpts() {
		return [
				'tabCrits' => $this->getSelectTabCrits()
		];
	}
	
	public static function getAjaxTooltip($opts) {
		$ret = '';
		foreach ($opts['tabCrits']?? [] as $tab => $crits) {
			$ret .= '<b>' . Utils_RecordBrowserCommon::get_caption($tab) . '</b>';
			
			if ($critsWords = Utils_RecordBrowserCommon::crits_to_words($tab, $crits)) {
				$ret .= ' ' . __('for which') . '<br />&nbsp;&nbsp;&nbsp;' . $critsWords;
			}
		}

		return __('Select one') . ($ret? ' ' . __('of') . ' ' . $ret: '');
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		$ret = '---';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$val = $record[$desc['id']];
			$commondata_sep = '/';
			if ((is_array($val) && empty($val))) return $ret;
			
			$param = Utils_RecordBrowserCommon::decode_select_param($desc['param']);
			
			if(!$param['array_id'] && $param['single_tab'] == '__COMMON__') return;
			
			if (!is_array($val)) $val = array($val);
			
			$ret = '';
			foreach ($val as $v) {
				$ret .= ($ret!=='')? '<br>': '';
				
				if ($param['single_tab'] == '__COMMON__') {
					$array_id = $param['array_id'];
					$path = explode('/', $v);
					$tooltip = '';
					$res = '';
					if (count($path) > 1) {
						$res .= Utils_CommonDataCommon::get_value($array_id . '/' . $path[0], true);
						if (count($path) > 2) {
							$res .= $commondata_sep . '...';
							$tooltip = '';
							$full_path = $array_id;
							foreach ($path as $w) {
								$full_path .= '/' . $w;
								$tooltip .= ($tooltip? $commondata_sep: '').Utils_CommonDataCommon::get_value($full_path, true);
							}
						}
						$res .= $commondata_sep;
					}
					$label = Utils_CommonDataCommon::get_value($array_id . '/' . $v, true);
					if (!$label) continue;
					$res .= $label;
					$res = Utils_RecordBrowserCommon::no_wrap($res);
					if ($tooltip) $res = '<span '.Utils_TooltipCommon::open_tag_attrs($tooltip, false) . '>' . $res . '</span>';
				} else {
					$tab_id = Utils_RecordBrowserCommon::decode_record_token($v, $param['single_tab']);
					
					if (!$tab_id) continue;
					
					list($select_tab, $id) = $tab_id;
					
					if ($param['cols']) {
						$res = Utils_RecordBrowserCommon::create_linked_label($select_tab, $param['cols'], $id, $nolink);
					} else {
						$res = Utils_RecordBrowserCommon::create_default_linked_label($select_tab, $id, $nolink);
					}
				}
				
				$ret .= $res;
			}
		}
		
		return $ret;
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
	
	public function validate(Utils_RecordBrowser_Recordset_Record $record, Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits) {
		$values = $this->decodeValue($record[$this->getId()] ?? '', false);

		if ($subfield = $crits->getKey()->getSubfield()) {
			if ($tab2 = $this->getParam('single_tab')) {
				$checkCrits = Utils_RecordBrowser_Recordset_Query_Crits_Basic::create($subfield, $crits->getValue(), $crits->getOperator());

				foreach (is_array($values)? $values: [$values] as $value) {
					$issues = Utils_RecordBrowser_Recordset::create($tab2)->getRecord($value)->validate($checkCrits);
					
					if (!$issues) return true;
				}
				
				return false;
			}
		}

		$critsCheck = clone $crits;
		
		// remove prefix for select from single tab: contact/1 => 1
		if (preg_match('/^[0-9-]+$/', is_array($values)? reset($values): $values)) {
			$crit_value = preg_replace('#.*/#', '', $critsCheck->getValue()->getValue());
			
			$critsCheck->getValue()->setValue($crit_value);
		}
		
		return parent::validate($record, $critsCheck);
	}
}
