<?php

class Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder
{
    protected $key;
    protected $label;
    protected $value;
    protected $deactivateCritOnNull = false;
    protected $priority = 1;
    protected $available = true;

    public function create($key, $label, $value)
    {
    	return new static($key, $label, $value);
    }
    
    /**
     * @param string $key          			Meta value that should be replaced with real one
     * @param string $label 				Human redable string used in crits to words
     * @param mixed  $value        			Real value that will be used in crits
     * @param bool   $deactivateOnNull     	Do not use this crit at all if $replace is null
     */
    public function __construct($key, $label, $value)
    {
        $this->key = $key;
        $this->label = $label;
        $this->value = $value;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getValue($humanReadable = false)
    {
    	return $humanReadable? $this->getLabel(): $this->value;
    }
    
    public function getLabel()
    {
    	return _V($this->label);
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }
    
	public function setPriority($priority) {
		$this->priority = $priority;
		
		return $this;
	}
	
	public function getDeactivateCritOnNull($humanReadable = false) {
		return $humanReadable? false: $this->deactivateCritOnNull;
	}

	public function setDeactivateCritOnNull($deactivateCritOnNull = true) {
		$this->deactivateCritOnNull = $deactivateCritOnNull;
		
		return $this;
	}
	
	/**
	 * Set a value or condition for the placeholder to be available
	 * The condition can be a callback with the actual field as parameter
	 * It should evaluate if the placeholder is available for the field and return boolean
	 * 
	 * @param boolean | callable $available
	 * @return Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder
	 */
	public function setAvailable($available) {
		$this->available = $available;
		
		return $this;
	}
	
	public function getAvailable(Utils_RecordBrowser_Recordset_Field $field) {
		return is_callable($this->available)? call_user_func($this->available, $field): $this->available;
	}
}

/**
 * Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder class alias
 */
class Utils_RecordBrowser_Crits_Placeholder extends Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder {}

