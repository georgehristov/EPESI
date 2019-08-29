<?php

class Utils_RecordBrowser_Recordset_Query_Crits_Integration_QueryBuilder
{

    /**
     * @var Utils_RecordBrowser_Recordset
     */
    protected $recordset;
    protected $fields;


    public static function create($recordset, $limitAccess = true) {
    	return new static($recordset, $limitAccess);
    }
    
    public function __construct($recordset, $limitAccess = true)
    {
    	$this->setRecordset($recordset, $limitAccess);
    }

    public function getBuilderModule(Module $module, $crits)
    {
        /** @var Utils_QueryBuilder $qb */
        $qb = $module->init_module(Utils_QueryBuilder::module_name());
        
        $qb->set_option('operators', self::$operators);
        $qb->set_filters($this->getFilters());
        $qb->set_rules($this->critsToQueryArray($crits));

        return $qb;
    }

    public function getFilters()
    {
    	$ret = [];
    	foreach ($this->getRecordset()->getFields() as $field) {
    		$ret = array_merge($ret, $field->getQueryBuilderFilters()?: []);
    	}

        return $ret;
    }

    public function critsToQueryArray($crits)
    {
    	$crits = Utils_RecordBrowser_Crits::create($crits);

    	if ($crits->isEmpty()) return [];
    	
    	if ($crits->isCompound() || $crits instanceof Utils_RecordBrowser_Crits) {
            $rules = [];
            foreach ($crits->getComponents(true) as $c) {
            	if (!$rr = $this->critsToQueryArray($c)) continue;

                $rules[] = $rr;
            }
            
            $condition = $crits->getJunction()?: 'AND';
            
            $ret = compact('rules', 'condition');
        } elseif ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits_Basic) {        	
        	$ret = $this->critsToQuery($crits);            
        } elseif ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits_RawSQL) {

        } else {
            throw new Exception("crits to json exporter: unsupported class: " . get_class($crits));
        }
        
        return $ret;
    }

    /**
     * @deprecated use jsonToCrits
     */
    public static function json_to_crits($json)
    {
    	return self::jsonToCrits($json);
    }
    
    public static function jsonToCrits($json)
    {
        // backward compatibility check
        if ($json instanceof Utils_RecordBrowser_Recordset_Query_Crits) return $json;

        return self::queryArrayToCrits(json_decode($json, true));
    }

    public static function queryArrayToCrits($arr)
    {
        $ret = null;
        if (isset($arr['condition']) && isset($arr['rules'])) {
            $rules = [];
            foreach ($arr['rules'] as $rule) {
                if (!$crit = self::queryArrayToCrits($rule)) continue;

                $rules[] = $crit;
            }
            
            $ret = $rules? Utils_RecordBrowser_Crits::create($rules, $arr['condition'] == 'OR'): $ret;
        } elseif (isset($arr['field']) && isset($arr['operator']) && array_key_exists('value', $arr)) {
            $ret = self::queryToCrits($arr['field'], $arr['operator'], $arr['value']);
        }
        
        return $ret;
    }

    public function critsToQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crits)
    {
    	$field = $crits->getField($this->getRecordset());
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
            $value = self::unescapeLikeValue($value);
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
        
        // if the value is a placeholder use the placeholder filter id
        $suffix = '';
        if ($placeholders = $field->getPlaceholderSelectList()) {
        	if (array_intersect(is_array($value)? $value: [$value], array_keys($placeholders))) $suffix = '_placeholder';
        }

        return [
        		'id' => $field->getId() . $suffix,
        		'field' => $field->getId(),
				'operator' => $operator,
        		'value'    => $value
        ];
    }

    public static function queryToCrits($field, $operator, $value)
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
        		$value = self::escapeLikeValue($value) . '%';
        	break;
        	
        	case 'not_begins_with':
        		$operator = '!~';
        		$value = self::escapeLikeValue($value) . '%';
        	break;
        	
        	case 'ends_with':
        		$operator = '~';
        		$value = '%' . self::escapeLikeValue($value);
        	break;
        	
        	case 'not_ends_with':
        		$operator = '!~';
        		$value = '%' . self::escapeLikeValue($value);
        	break;
        	
        	case 'contains':
        		$operator = '~';
        		$value = '%' . self::escapeLikeValue($value) . '%';
        	break;
        	
        	case 'not_contains':
        		$operator = '!~';
        		$value = '%' . self::escapeLikeValue($value) . '%';
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

    public static function escapeLikeValue($value)
    {
        return str_replace(['_', '%'], ['\\_', '\\%'], $value);
    }

    public static function unescapeLikeValue($value)
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