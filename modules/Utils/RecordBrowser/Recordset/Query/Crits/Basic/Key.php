<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Basic_Key
{
    protected $key;
    protected $modifiers = '';
    protected $fields = [];
    
    public static function create($key)
    {
    	return is_object($key)? $key: new static ($key);
    }
    
    public function __construct($key)
    {
    	$this->setKey($key);
    }
    
    public function setKey($key) 
    {
    	$this->modifiers = Utils_RecordBrowser_Recordset_Query_Crits::parseModifiers($key);
    	
    	$this->key = Utils_RecordBrowser_Recordset_Query_Crits::stripModifiers($key);
    	
    	$this->fields = preg_split('/[\[\]]/', $this->key, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    public function __toString() 
    {
    	return $this->key;
    }
    
	public function getFields()
	{
		return $this->fields;
	}
	
	public function getField()
	{
		return reset($this->fields);
	}
	
	public function getSubfield($sequence = 0)
	{
		return $this->getSubfields()[$sequence]?? '';
	}
	
	public function getSubfields()
	{
		$subfields = $this->fields;
		
		array_shift($subfields);
		
		return $subfields;
	}
	
	public function getModifiers() 
	{
		return $this->modifiers;
	}	
}
