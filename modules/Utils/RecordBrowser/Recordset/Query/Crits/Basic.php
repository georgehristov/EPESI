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
    
    public function isEmpty()
    {
    	return false;
    }
    
    public function validate(Utils_RecordBrowser_Recordset $recordset, $values)
    {
    	if (! $this->isActive()) return [];
    	
    	if (! $field = $this->getField($recordset)) return [];

    	return $field->validate($this, $values[$field->getArrayId()]?? '')? []: [$this];
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

    public function replacePlaceholder(Utils_RecordBrowser_Recordset $recordset, Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder, $humanReadable = false)
    {
    	if (! $placeholder->getAvailable($this->getField($recordset))) return;
    		
    	$deactivate = $placeholder->getDeactivateCritOnNull($humanReadable) && ($placeholder->getValue() === null);
        
    	$match = $this->getValue()->replace($placeholder, $humanReadable);
        
        if ($match && $deactivate) {        	
        	$this->deactivate();
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

		return $field->getQuery($this->toFinal($recordset));
	}
	
	public function getField($recordset)
	{
		return Utils_RecordBrowser_Recordset::create($recordset)->getField($this->getKey()->getField(), true);
	}
	
	public function toWords($recordset, $asHtml = true)
	{
		$recordset = Utils_RecordBrowser_Recordset::create($recordset);
		
		/**
		 * @var Utils_RecordBrowser_Recordset_Field $field
		 */
		if (! $field = $this->getField($recordset)) return '';

		return $field->toWords($this->toFinal($recordset, true), $asHtml);
		
		//return Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['company_name[company_name]' => 'aaa'])->toWords('contact');
		return Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['company_name[company_name]' => 'aaa', 'first_name'=>'ddd'])->getQuery('contact');
		//return Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['first_name' => 'aaa'])->toWords('contact');
		//return Utils_RecordBrowser_Recordset_Query_Crits::stripModifiers('company_name[company_name]');
		Utils_RecordBrowser_Recordset_Record::create('contact', ['first_name' => 'aaa'])->validate(['first_name'=>'aaa']);
		//Utils_RecordBrowser_Recordset_Query_Crits_Compound::create(['company_name[company_name]' => 'aaa', 'first_name'=>'ddd'])->validate('contact', ['first_name' => 'aaa']);
	}
}
