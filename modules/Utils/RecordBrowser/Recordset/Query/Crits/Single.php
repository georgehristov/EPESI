<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Single extends Utils_RecordBrowser_Recordset_Query_Crits_Basic
{
	/**
	 * @var Utils_RecordBrowser_Recordset_Query_Crits_Basic[]
	 */
	protected $components = [];
	
	public function setValue($values) 
	{
		$this->value = is_array($values)? $values: [$values];
		
		foreach ($this->value as $value) {
			$this->components[] = Utils_RecordBrowser_Recordset_Query_Crits_Basic::create($this->getKey(), $value, $this->getOperator());
		}
		
		return $this;
	}
	
	public function getComponents() 
	{
		return $this->components;
	}
	
    /**
     * Normalize to remove negation
     */
    public function normalize()
    {
        if (!$this->get_negation()) return;
        
        $this->set_negation(false);
        $this->operator = self::opposite_operator($this->operator);
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

    public function getQuerySection(Utils_RecordBrowser_Recordset_Field $field)
    {
    	$ret = Utils_RecordBrowser_Recordset_Query_Section::create('false');
    	
    	foreach ($this->getComponents() as $crit) {
    		$ret = Utils_RecordBrowser_Recordset_Query_Section::merge($ret, $crit->getQuerySection($field));
    	}
    	
    	return $ret;
    }
}
