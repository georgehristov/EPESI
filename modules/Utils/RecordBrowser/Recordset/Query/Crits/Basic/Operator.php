<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Basic_Operator
{
	protected static $map = [
			'~' => 'LIKE',
			'>=' => '>=',
			'<=' => '<=',
			'=' => '=',
			'<' => '<',			
			'>' => '>',			
	];
	protected static $opposites = [
			'=' => '!=',
			'!=' => '=',
			'<' => '>=',
			'>' => '<=',
			'>=' => '<',			
			'<=' => '>',			
			'LIKE' => 'NOT LIKE',
			'NOT LIKE' => 'LIKE',
			'IN' => 'NOT IN',
			'NOT IN' => 'IN',
	];
	
    protected $operator;
    
    public static function create($string)
    {
    	if (is_object($string)) return $string;
    	
    	$modifiers = Utils_RecordBrowser_Recordset_Query_Crits::parseModifiers($string);
    	
    	$negation = stripos($modifiers, '!') !== false;
    	
    	$operator = null;
    	foreach (self::$map as $check => $op) {
    		if (stripos($modifiers, $check) !== false) {
    			$operator = $op;
    			break;
    		}
    	}

    	return new static ($operator?: '=', $negation);
    }
    
    public function __construct($operator = '=', $negation = false)
    {
        $this->setOperator($operator);
        
        if ($negation) $this->negate();
    }
    
	public function getOperator() {
		return $this->operator;
	}

	public function setOperator($operator) {
		if (!in_array($operator, self::$opposites))
			trigger_error('Unknown crits operator', E_USER_ERROR);
		
		$this->operator = $operator;
		
		return $this;
	}

	public function negate() {
		$this->setOperator(self::$opposites[$this->getOperator()]);
	}
	
	public function getSQL() {
		switch ($this->getOperator()) {
			case 'LIKE':
				$ret = DB::like();
			break;
			
			case 'NOT LIKE':
				$ret = 'NOT ' . DB::like();
			break;
			
			default:
				$ret = $this->getOperator();
			break;
		}
		
		return $ret;
	}
	
	public function __toString() {
		return $this->getSQL();
	}
}
