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
    	return new static ($key, $value, $operator);
    }
    
    function __construct($key, $value, $operator = '')
    {
        $this->setKey($key);
        $this->setOperator($operator?: $key);
        $this->setValue($value);
    }
       
    /**
     * Normalize to remove negation
     */
    public function normalize()
    {
        
    }

    public function find($key)
    {
        return $this->key == $key? $this: null;
    }

    public function replace_value($search, $replace, $deactivate = false)
    {
        $deactivate = $deactivate && ($replace === null);
        if (is_array($this->value)) {
            $found = false;
            foreach ($this->value as $k => $v) {
                if ($v === $search) {
                    $found = true;
                    unset($this->value[$k]);
                }
            }
            if ($found) {
                if ($deactivate) {
                    if (empty($this->value)) {
                        $this->set_active(false);
                    }
                } else {
                    if (!is_array($replace)) {
                        $replace = array($replace);
                    }
                    $this->value = array_merge($this->value, $replace);
                }
            }
        } elseif ($this->value === $search) {
            if ($deactivate) {
                $this->set_active(false);
            } else {
                $this->value = $replace;
            }
        }
    }

    public function __clone()
    {
        if (is_object($this->value)) {
            $this->value = clone $this->value;
        } elseif (is_array($this->value)) {
            foreach ($this->value as $k => $v) {
                if (is_object($v)) {
                    $this->value[$k] = clone $v;
                }
            }
        }
    }

    public function getQuerySection(Utils_RecordBrowser_Recordset_Field $field) {
    	return $field->getQuerySection($this);
    }
    
	public function getKey() {
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
	
	public function setKey($key) {
		$this->key = Utils_RecordBrowser_Recordset_Query_Crits_Basic_Key::create($key);
		
		return $this;
	}	
	
	public function setOperator($operator) {
		$this->operator = Utils_RecordBrowser_Recordset_Query_Crits_Basic_Operator::create($operator);
		
		return $this;
	}

	public function setValue($value) {		
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
}
