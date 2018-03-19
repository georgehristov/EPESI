<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Select extends Utils_RecordBrowser_Field_Instance {
	public static $options_limit = 50;
	protected $multiselect = false;
	protected $single_tab = false;
	protected $record_count = 0;
	
	function __construct($array) {
		parent::__construct($array);
		
		$this->single_tab = $this->param['single_tab'];
	}	
	
	public function prepareSqlValue(& $value) {
		$value = $this->encodeValue($value);
		
		//backward compatibility
		$value = str_replace(array('P:', 'C:'), array('contact/', 'company/'), $value);
		return true;
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
				if (is_array($access) || $access instanceof Utils_RecordBrowser_CritsInterface) {
					if((is_array($crits) && $crits) || $crits instanceof Utils_RecordBrowser_CritsInterface)
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
		$crits_callback = isset($param[1])? explode('::', $param[1]): null;
		$adv_params_callback = isset($param[2])? explode('::', $param[2]): null;
		
		$select_tab = $reference[0];
		$cols = isset($reference[1])? array_map(array(__CLASS__, 'get_field_id'), array_filter(explode('|', $reference[1]))): null;
		
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
	
	public function getSqlOrder($direction, $tab_alias='') {
		$field_sql_id = $this->getSqlId($tab_alias);
		
		$tab2 = $this['select']['single_tab'];
		$cols2 = $this['select']['cols'];
		$val = $field_sql_id;
		$fields2 = Utils_RecordBrowserCommon::init($tab2);
		// search for better sorting than id
		if ($fields2) {
			foreach ($cols2 as $referenced_col) {
				if (isset($fields2[$referenced_col])) {
					$desc2 = $fields2[$referenced_col];
					if ($desc2['type'] != 'calculated' || $desc2['param'] != '') {
						$field_id2 = Utils_RecordBrowserCommon::get_field_id($referenced_col);
						$val = '(SELECT rdt.f_'.$field_id2.' FROM '.$tab2.'_data_1 AS rdt WHERE rdt.id='.$field_sql_id.')';
						break;
					}
				}
			}
		}
		
		return ' ' . $val . ' ' . $direction;
	}
	
	public function handleCrits($operator, $value, $tab_alias='') {
		$param = $this->getParam();
		
		$sql = '';
		$vals = array();
		list($field, $sub_field) = Utils_RecordBrowser_CritsSingle::parse_subfield($field);
		
		$tab2 = $param['single_tab'];
		
		if ($operator == DB::like() && isset($param['cols'])) {
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
					$crits->_or(new Utils_RecordBrowser_CritsSingle($col, $operator, $value, false, $raw_sql_val));
				}
			}
			if (!$crits->is_empty()) {
				$subquery = Utils_RecordBrowserCommon::build_query($tab2, $crits, $this->admin_mode);
				if ($subquery) {
					$ids = DB::GetCol("SELECT r.id FROM $subquery[sql]", $subquery['vals']);
				} else {
					$sql = 'false';
				}
			}
		} else {
			if ($raw_sql_val) {
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
	
	public function handleCritsRawSql($operator, $value, $tab_alias='') {
		//TODO: add this
	}
	
	public function isOrderable() {
		return $this->single_tab? true: false;
	}
	
	public function getQuickjump($advanced = false) {
		return true;
	}
}
