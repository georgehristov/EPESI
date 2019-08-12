<?php

class Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder
{
    protected $key;
    protected $label;
    protected $value;
    protected $deactivateOnNull;
    protected $priority;

    public function create($key, $label, $value, $deactivateOnNull = false, $priority = 1)
    {
    	return new static($key, $label, $value, $deactivateOnNull, $priority);
    }
    
    /**
     * @param string $key          			Meta value that should be replaced with real one
     * @param string $label 				Human redable string used in crits to words
     * @param mixed  $value        			Real value that will be used in crits
     * @param bool   $deactivateOnNull     	Do not use this crit at all if $replace is null
     * @param int    $priority       		You may override some system default replacements using higher priority
     */
    public function __construct($key, $label, $value, $deactivateOnNull = false, $priority = 1)
    {
        $this->key = $key;
        $this->label = $label;
        $this->value = $value;
        $this->deactivateOnNull = $deactivateOnNull;
        $this->priority = $priority;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function getValue($humanReadable = false)
    {
    	return $humanReadable? $this->label: $this->value;
    }

    /**
     * @return boolean
     */
    public function getDeactivateOnNull($humanReadable = false)
    {
    	return $humanReadable? false: $this->deactivateOnNull;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }
}
