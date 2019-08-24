<?php

class Utils_RecordBrowser_Recordset_Query_Crits_Integration_QueryBuilder
{

    /**
     * @var Utils_RecordBrowser_Recordset
     */
    protected $recordset;
    protected $fields;


    function __construct($recordset, $limitAccess = true)
    {
    	$this->setRecordset($recordset, $limitAccess);
    }

    public function get_builder_module(Module $module, $crits)
    {
        /** @var Utils_QueryBuilder $qb */
        $qb = $module->init_module(Utils_QueryBuilder::module_name());
        
        $qb->set_option('operators', self::$operators);
        $qb->set_filters($this->get_filters());
        $qb->set_rules($this->crits_to_query_array($crits));
        
        return $qb;
    }

    public function get_filters()
    {
        $ret = $this->get_default_record_filters();
        foreach ($this->fields as $f) {
            if (!$def = self::map_rb_field_to_query_builder_filters($this->recordset, $f)) continue;
            
            $ret = array_merge($ret, $def);
        }
        
        return $ret;
    }

    public function get_default_record_filters()
    {
        $ret = array();
        $empty = array(''=>'['.__('Empty').']');
        if ($this->recordset == 'contact') {
            $ret[] = array(
                'id' => 'id',
                'label' => __('ID'),
                'type' => 'boolean',
                'input' => 'select',
                'values' => array('USER'=>__('User Contact'))
            );
        }
        if ($this->recordset == 'company') {
            $ret[] = array(
                'id' => 'id',
                'label' => __('ID'),
                'type' => 'boolean',
                'input' => 'select',
                'values' => array('USER_COMPANY'=>__('User Company'))
            );
        }
        $ret[] = array(
            'id' => ':Created_by',
            'label' => __('Created by'),
            'type' => 'boolean',
            'input' => 'select',
            'values' => array('USER_ID' => __('User Login'))
        );
        $ret[] = array(
            'id' => ':Created_on',
            'field' => ':Created_on',
            'label' => __('Created on'),
            'type' => 'datetime',
            'plugin' => 'datepicker',
            'plugin_config' => array('dateFormat' => 'yy-mm-dd', 'constrainInput' => false),
        );
        $ret[] = array(
            'id' => ':Created_on_relative',
            'field' => ':Created_on',
            'label' => __('Created on') . ' (' . __('relative') . ')',
            'type' => 'datetime',
            'input' => 'select',
            'values' => Utils_RecordBrowserCommon::$date_values
        );
        $ret[] = array(
            'id' => ':Edited_on',
            'field' => ':Edited_on',
            'label' => __('Edited on'),
            'type' => 'datetime',
            'plugin' => 'datepicker',
            'plugin_config' => array('dateFormat' => 'yy-mm-dd', 'constrainInput' => false),
        );
        $ret[] = array(
            'id' => ':Edited_on_relative',
            'field' => ':Edited_on',
            'label' => __('Edited on') . ' (' . __('relative') . ')',
            'type' => 'datetime',
            'input' => 'select',
            'values' => Utils_RecordBrowserCommon::$date_values
        );
        $ret[] = array(
            'id' => ':Fav',
            'field' => ':Fav',
            'label' => __('Favorite'),
            'type' => 'boolean',
            'input' => 'select',
            'values' => array('1' => __('Yes'), '0' => __('No'))
        );
        $ret[] = array(
            'id' => ':Recent',
            'field' => ':Recent',
            'label' => __('Recent'),
            'type' => 'boolean',
            'input' => 'select',
            'values' => array('1' => __('Yes'), '0' => __('No'))
        );
        if (Utils_WatchdogCommon::get_category_id($this->recordset)) {
            $ret[] = array(
                'id' => ':Sub',
                'field' => ':Sub',
                'label' => __('Subscribed'),
                'type' => 'boolean',
                'input' => 'select',
                'values' => array('1' => __('Yes'), '0' => __('No'))
            );
        }
        return $ret;
    }

    public static function map_rb_field_to_query_builder_filters($tab, $desc, $in_depth = true, $prefix = '', $sufix = '', $label_prefix = '')
    {
        $filters = array();
        $type = null;
        $values = null;
        $input = null;
        $opts = array();
        $opts['id'] = $prefix . $desc['id'] . $sufix;
        $opts['field'] = $opts['id'];
        $opts['label'] = $label_prefix . _V($desc['name']);

        if ($tab == 'contact' && $desc['id'] == 'login' ||
            $tab == 'rc_accounts' && $desc['id'] == 'epesi_user'
        ) {
            $type = 'boolean'; // just for valid operators
            $input = 'select';
            $values = array(''=>'['.__('Empty').']', 'USER_ID'=>__('User Login'));
        } else
        switch ($desc['type']) {
            case 'text':
                $type = 'string';
                break;
            case 'multiselect':
            case 'select':
                $param = Utils_RecordBrowserCommon::decode_select_param($desc['param']);
                
                $type = 'boolean';
                $input = 'select';
                $values = self::permissions_get_field_values($tab, $desc, $in_depth);
                if ($in_depth && $param['single_tab']) {
                    if (Utils_RecordBrowserCommon::check_table_name($param['single_tab'], false, false)) {
                        $fields = Utils_RecordBrowserCommon::init($param['single_tab']);
                        foreach ($fields as $k => $v) {
                            if ($v['type'] == 'calculated' || $v['type'] == 'hidden') {
                            } else {
                                $new_label_prefix = _V($desc['name']) . ' ' .  __('is set to record where') . ' ';
                                $sub_filter = self::map_rb_field_to_query_builder_filters($tab, $v, false, $desc['id'] . '[', ']', $new_label_prefix);
                                if ($sub_filter) {
                                    $sub_filter = reset($sub_filter);
                                    $sub_filter['optgroup'] = $new_label_prefix;
                                    $filters[] = $sub_filter;
                                }
                            }
                        }
                    }
                }
                break;
            case 'commondata':
                $type = 'boolean';
                $input = 'select';
                $array_id = is_array($desc['param']) ? $desc['param']['array_id'] : $desc['ref_table'];
                $values = array('' => '['.__('Empty').']');
                if (strpos($array_id, '::') === false) {
                    $values = $values + Utils_CommonDataCommon::get_translated_array($array_id, is_array($desc['param']) ? $desc['param']['order'] : false);
                }
                break;
            case 'integer':     $type = 'integer'; break;
            case 'float':       $type = 'double'; break;
            case 'timestamp':
                $type = 'datetime';
            case 'date':
                if (!$type) $type = 'date';
                // absolute value filter
                $opts['plugin'] = 'datepicker';
                $opts['plugin_config'] = array('dateFormat' => 'yy-mm-dd', 'constrainInput' => false);
                // relative value filter
                $filt2 = $opts;
                $filt2['id'] .= '_relative';
                $filt2['label'] .= ' (' . __('relative') . ')';
                $filt2['type'] = 'date';
                $filt2['input'] = 'select';
                $filt2['values'] = self::permissions_get_field_values($tab, $desc);
                $filters[] = $filt2;
                break;
            case 'time':
                $type = 'time';
                break;
            case 'long text':   $type = 'string'; $input = 'textarea'; break;
            case 'hidden': break;
            case 'calculated': break;
            case 'checkbox':    $type = 'boolean';
                                $input = 'select';
                                $values = array('1' => __('Yes'), '0' => __('No'));
                break;
            case 'currency': $type = 'double'; break;
            case 'autonumber': break;
        }
        if ($type) {
            $opts['type'] = $type;
            if ($values) {
                $opts['values'] = $values;
            }
            if ($input) {
                $opts['input'] = $input;
            }
            $filters[] = $opts;
            return $filters;
        }
        return null;
    }

    private static function permissions_get_field_values($tab, $desc, $first_level = true) {
        $arr = array(''=>'['.__('Empty').']');
        $field = $desc['id'];
        switch (true) {
            case $desc['type']=='text' && $desc['filter']:
                $arr_add = @DB::GetAssoc('SELECT f_'.$desc['id'].', f_'.$desc['id'].' FROM '.$tab.'_data_1 GROUP BY f_'.$desc['id'].' ORDER BY count(*) DESC LIMIT 20');
                if($arr_add) $arr += $arr_add;
                break;
            case $desc['commondata']:
                $array_id = is_array($desc['param']) ? $desc['param']['array_id'] : $desc['ref_table'];
                if (strpos($array_id, '::')===false)
                    $arr = $arr + Utils_CommonDataCommon::get_translated_array($array_id, is_array($desc['param'])?$desc['param']['order']:false);
                break;
            case $tab=='contact' && $field=='login' ||
                 $tab=='rc_accounts' && $field=='epesi_user': // just a quickfix, better solution will be needed
                $arr = $arr + array('USER_ID'=>__('User Login'));
                break;
            case $desc['type']=='date' || $desc['type']=='timestamp':
                $arr = $arr + Utils_RecordBrowserCommon::$date_values;
                break;
            case ($desc['type']=='multiselect' || $desc['type']=='select') && (!isset($desc['ref_table']) || !$desc['ref_table']):
                $arr = $arr + array('USER'=>__('User Contact'));
                $arr = $arr + array('USER_COMPANY'=>__('User Company'));
                break;
            case $desc['type']=='checkbox':
                $arr = array('1'=>__('Yes'),'0'=>__('No'));
                break;
            case ($desc['type']=='select' || $desc['type']=='multiselect') && isset($desc['ref_table']):
                $ref_tables = explode(',', $desc['ref_table']);
                if (in_array('contact', $ref_tables)) $arr = $arr + array('USER'=>__('User Contact'));
                if (in_array('company', $ref_tables)) $arr = $arr + array('USER_COMPANY'=>__('User Company'));
                if ($first_level) {
                    if($desc['type']=='multiselect')
                        $arr = $arr + array('ACCESS_VIEW'=>__('Allow view any record'),'ACCESS_VIEW_ALL'=>__('Allow view all records'),'ACCESS_EDIT'=>__('Allow edit any record'),'ACCESS_EDIT_ALL'=>__('Allow edit all records'),'ACCESS_PRINT'=>__('Allow print any record'),'ACCESS_PRINT_ALL'=>__('Allow print all records'),'ACCESS_DELETE'=>__('Allow delete any record'),'ACCESS_DELETE_ALL'=>__('Allow delete all records'));
                    else
                        $arr = $arr + array('ACCESS_VIEW'=>__('Allow view record'),'ACCESS_EDIT'=>__('Allow edit record'),'ACCESS_PRINT'=>__('Allow print record'),'ACCESS_DELETE'=>__('Allow delete record'));
                }
                break;
        }
        return $arr;
    }


    public static function crits_to_query_array($crits)
    {
    	$crits = Utils_RecordBrowser_Crits::create($crits);
    	
    	if ($crits->isCompound()) {
            $rules = [];
            foreach ($crits->getComponents(true) as $c) {
            	if (!$rr = self::crits_to_query_array($c)) continue;

                $rules[] = $rr;
            }
            
            $condition = $crits->getJunction()?: 'AND';
            
            $ret = compact('rules', 'condition');

        } elseif ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits_Basic) {
        	
        	$ret = self::crits_to_query($crits);
            
        } elseif ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits_RawSQL) {

        } else {
            throw new Exception("crits to json exporter: unsupported class: " . get_class($crits));
        }
        
        return $ret;
    }

    public static function json_to_crits($json)
    {
        // backward compatibility check
        if ($json instanceof Utils_RecordBrowser_Recordset_Query_Crits) return $json;

        return self::query_array_to_crits(json_decode($json, true));
    }

    public static function query_array_to_crits($arr)
    {
        $ret = null;
        if (isset($arr['condition']) && isset($arr['rules'])) {
            $rules = [];
            foreach ($arr['rules'] as $rule) {
                if (!$crit = self::query_array_to_crits($rule)) continue;

                $rules[] = $crit;
            }
            
            $ret = $rules? Utils_RecordBrowser_Crits::create($rules, $arr['condition'] == 'OR'): $ret;
        } elseif (isset($arr['field']) && isset($arr['operator']) && array_key_exists('value', $arr)) {
            $ret = self::query_to_crits($arr['field'], $arr['operator'], $arr['value']);
        }
        
        return $ret;
    }

    public static function crits_to_query(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits)
    {
    	$field = $crits->getField()->getId();
    	$operator = $crits->getOperator()->getOperator();
    	$value = $crits->getValue();
    	
        if (($operator == '=' || $operator == '!=' ) && $value == '' && !is_numeric($value)) {
            $operator = $operator == '=' ? 'is_null' : 'is_not_null';
        } elseif ($operator == 'LIKE' || $operator == 'NOT LIKE') {
            $not = $operator == 'NOT LIKE';
            if (preg_match('/^%.*%$/', $value)) {
                $operator = 'contains';
                $value = trim($value, '%');
            } elseif (preg_match('/^.*%$/', $value)) {
                $operator = 'begins_with';
                $value = rtrim($value, '%');
            } elseif (preg_match('/^%.*/', $value)) {
                $operator = 'ends_with';
                $value = ltrim($value, '%');
            }
            $value = self::unescape_like_value($value);
            if ($not) {
                $operator = "not_$operator";
            }
        } else {
            if (isset(self::$operatorMap[$operator])) {
                $operator = self::$operatorMap[$operator];
            } else {
                throw new Exception("Unsupported operator: $operator");
            }
        }
        
        return [
        		'id' => $field,
        		'field' => $field,
				'operator' => $operator,
        		'value'    => $value
        ];
    }

    public static function query_to_crits($field, $operator, $value)
    {
        static $flipped;
        
        if (!$flipped) $flipped = array_flip(self::$operatorMap);
        
        switch ($operator) {
        	case 'is_null':
        		$operator = '=';
        		$value = null;
        	break;
        	
        	case 'is_not_null':
        		$operator = '!=';
        		$value = null;
        	break;
        	
        	case 'begins_with':
        		$operator = '~';
        		$value = self::escape_like_value($value) . '%';
        	break;
        	
        	case 'not_begins_with':
        		$operator = '!~';
        		$value = self::escape_like_value($value) . '%';
        	break;
        	
        	case 'ends_with':
        		$operator = '~';
        		$value = '%' . self::escape_like_value($value);
        	break;
        	
        	case 'not_ends_with':
        		$operator = '!~';
        		$value = '%' . self::escape_like_value($value);
        	break;
        	
        	case 'contains':
        		$operator = '~';
        		$value = '%' . self::escape_like_value($value) . '%';
        	break;
        	
        	case 'not_contains':
        		$operator = '!~';
        		$value = '%' . self::escape_like_value($value) . '%';
        	break;
        	
        	case 'in':
        		$operator = '=';
        	break;
        	
        	case 'not_in':
        		$operator = '!=';
        	break;
        	
        	default:
        		if (isset($flipped[$operator])) {
        			$operator = $flipped[$operator];
        		} else {
        			throw new Exception("Unsupported operator: $operator");
        		}
        	break;
        }
        
        return Utils_RecordBrowser_Crits::create([
        		$operator . $field => $value
        ]);
    }

    public static function escape_like_value($value)
    {
        return str_replace(['_', '%'], ['\\_', '\\%'], $value);
    }

    public static function unescape_like_value($value)
    {
        return str_replace(['\\_', '\\%'], ['_', '%'], $value);
    }

    public function getRecordset() {
    	return $this->recordset;
    }
    
    protected function setRecordset($recordset, $limitAccess = true) {
    	$this->recordset = Utils_RecordBrowser_Recordset::create($recordset);
    	
    	$this->fields = $limitAccess? $this->getRecordset()->getAccessibleFields(): $this->getRecordset()->getFields();
    	
    	return $this;
    }
    
    protected static $operatorMap = [
        '' => '',
        '=' => 'equal',
        '!=' => 'not_equal',
        '>=' => 'greater_or_equal',
        '<' => 'less',
        '<=' => 'less_or_equal',
        '>' => 'greater',
        'LIKE' => 'like',
        'NOT LIKE' => 'not_like',
        'IN' => 'in',
        'NOT IN' => 'not_in'
    ];
		
	protected static $operators = [
			[
					'type' => 'equal',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string',
							'number',
							'datetime',
							'boolean'
					]
			],
			[
					'type' => 'not_equal',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string',
							'number',
							'datetime',
							'boolean'
					]
			],
			[
					'type' => 'less',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'number',
							'datetime'
					]
			],
			[
					'type' => 'less_or_equal',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'number',
							'datetime'
					]
			],
			[
					'type' => 'greater',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'number',
							'datetime'
					]
			],
			[
					'type' => 'greater_or_equal',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'number',
							'datetime'
					]
			],
			[
					'type' => 'begins_with',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string'
					]
			],
			[
					'type' => 'not_begins_with',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string'
					]
			],
			[
					'type' => 'contains',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string'
					]
			],
			[
					'type' => 'not_contains',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string'
					]
			],
			[
					'type' => 'ends_with',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string'
					]
			],
			[
					'type' => 'not_ends_with',
					'nb_inputs' => 1,
					'multiple' => false,
					'apply_to' => [
							'string'
					]
			],
			[
					'type' => 'is_null',
					'nb_inputs' => 0,
					'multiple' => false,
					'apply_to' => [
							'string',
							'number',
							'datetime',
							'boolean'
					]
			],
			[
					'type' => 'is_not_null',
					'nb_inputs' => 0,
					'multiple' => false,
					'apply_to' => [
							'string',
							'number',
							'datetime',
							'boolean'
					]
			]
	];
	

}