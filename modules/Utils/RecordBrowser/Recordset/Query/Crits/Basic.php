<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Basic extends Utils_RecordBrowser_Recordset_Query_Crits
{
	/**
	 * @var Utils_RecordBrowser_Recordset_Query_Crits_Basic_Key
	 */
    protected $key;
    /**
     * @var Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value
     */
    protected $value;
    /**
     * @var Utils_RecordBrowser_Recordset_Query_Crits_Basic_Operator
     */
    protected $operator;

    public static function create($key, $value, $operator = '')
    {
    	if ($value instanceof Utils_RecordBrowser_Recordset_Query_Crits) return $value;
    	
    	return new static ($key, $value, $operator);
    }
    
    public function __construct($key, $value, $operator = '')
    {
        $this->setKey($key);
        $this->setOperator($operator?: $key);
        $this->setValue($value);
    }
    
    public function validate(Utils_RecordBrowser_Recordset $recordset, $values)
    {
    	if (! $this->isActive()) return [];
    	
    	if (! $field = $this->getField($recordset)) return [];
    	
    	if ($callback = $this->getValue()->getCallback()) {
    		$valid = is_callable($callback)? call_user_func_array($callback, [$values, $field]): true;
    	}
    	else {
    		$valid = $field->validate($values, $this);
    	}
    	
    	return $valid? []: [$this];
    }
    
    public function negate()
    {
        $this->getOperator()->negate();
        
        return $this;
    }

    public function find($key)
    {
        return (string) $this->getKey() == $key? $this: null;
    }

    public function replaceValue($search, $replace, $deactivateOnNull = false)
    {
    	$deactivate = $deactivateOnNull && ($replace === null);
        
        $match = $this->getValue()->replace($search, $replace);
        
        if ($match && $deactivate) {        	
        	$this->setActive(false);
        }
        
        return $match;
    }

    public function __clone()
    {
    	$this->key = clone $this->key;
        $this->value = clone $this->value;
        $this->operator = clone $this->operator;        
    }

	public function getKey()
	{
		return $this->key;
	}
	
	public function getOperator()
	{
		return $this->operator;
	}
	
	public function getValue()
	{
		return $this->value;
	}
	
	public function setKey($key)
	{
		$this->key = Utils_RecordBrowser_Recordset_Query_Crits_Basic_Key::create($key);
		
		return $this;
	}	
	
	public function setOperator($operator)
	{
		$this->operator = Utils_RecordBrowser_Recordset_Query_Crits_Basic_Operator::create($operator);
		
		return $this;
	}

	public function setValue($value)
	{		
		$this->value = Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value::create($value, $this->getKey()->getModifiers());
		
		return $this;
	}
	
	public function getSQLOperator()
	{
		return $this->getOperator()->getSQL();
	}
	
	public function getSQLValue()
	{
		return $this->getValue()->getSQL();
	}

	public function getQuery(Utils_RecordBrowser_Recordset $recordset)
	{
		if (! $field = $this->getField($recordset)) return $recordset->createQuery();
		
		return $field->getQuery($this);
	}
	
	protected function getField($recordset)
	{
		return Utils_RecordBrowser_Recordset::create($recordset)->getField($this->getKey()->getField(), true);
	}
	
	public function toWords($recordset, $html = true)
	{
		/**
		 * @var Utils_RecordBrowser_Recordset_Field $field
		 */
		if (! $field = $this->getField($recordset)) return '';

		$value = '';		
		$subquery = false;		
		if ($subfield = $this->getKey()->getSubfield()) {
			if ($tab2 = $field->getParam('single_tab')) {
				$crits = Utils_RecordBrowser_Recordset_Query_Crits_Basic::create($subfield, $this->getValue(), $this->getOperator());
				
				$value = $crits->toWords($tab2, $html);
				
				$subquery = true;
			}
		}
		
		$value = $subquery? $value: $this->getValue()->toWords($field);
		
		$key = $field->getLabel();
		
		if ($html) {
			$key = "<strong>$key</strong>";
			
			$value = $subquery? $value: '<strong>' . $value . '</strong>';
		}
		
		$operand = $subquery? __('is set to record where'): $this->getOperator()->toWords();
		
		$ret = "{$key} {$operand} {$value}";

		return $html? $ret: html_entity_decode($ret);
		
		//return Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['company_name[company_name]' => 'aaa'])->toWords('contact');
		return Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['company_name[company_name]' => 'aaa', 'first_name'=>'ddd'])->getQuery('contact');
		//return Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['first_name' => 'aaa'])->toWords('contact');
		//return Utils_RecordBrowser_Recordset_Query_Crits::stripModifiers('company_name[company_name]');
		Utils_RecordBrowser_Recordset_Record::create('contact', ['first_name' => 'aaa'])->validate(['first_name'=>'aaa']);
		//Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['company_name[company_name]' => 'aaa', 'first_name'=>'ddd'])->validate('contact', ['first_name' => 'aaa']);
	}
}
