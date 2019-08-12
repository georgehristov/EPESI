<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Compound extends Utils_RecordBrowser_Recordset_Query_Crits
{
	use Utils_RecordBrowser_Recordset_Query_Crits_Trait_Components;

    public static function where($key, $operator, $value)
    {
    	return self::create(Utils_RecordBrowser_Recordset_Query_Crits_Single::create($key, $value, $operator));
    }

    public static function create($crits = null, $or = false)
    {
    	if ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits) return $crits;
    	
    	return new static ($crits, $or);
    }
    
    public function __construct($crits = null, $or = false)
    {
    	if (is_bool($crits)) {
    		$crits = $crits? []: Utils_RecordBrowser_Recordset_Query_Crits_RawSQL::create('false', 'true');
     	}
    	
        if (!$crits) return;
        
        $crits = is_array($crits)? $crits: [$crits];
        $orGroup = [];
        foreach ($crits as $key => $value) {
        	$modifiers = $this->parseModifiers($key);
        	
        	if (stripos($modifiers, '^') !== false || stripos($modifiers, '(') !== false || ($orGroup && stripos($modifiers, '|') !== false)) {
				// strip orGroup modifier
        		$orGroup[str_ireplace(['^', '(', '|'], '', $key)] = $value;
				continue;
        	}
        	
        	if ($orGroup) {
        		$this->addComponent(self::create($orGroup, true));
        		$orGroup = [];
        	}

        	if ($value instanceof Utils_RecordBrowser_Recordset_Query_Crits) {
        		$component = $value;
        	}
        	// if $key is numeric it is stripped, the value is Crits
        	// handy when merging crits in the form of arrays
        	elseif (!$this->stripModifiers($key)) {
        		$component = self::create($value);
        	}
        	else {
        		$component = Utils_RecordBrowser_Recordset_Query_Crits_Single::create($key, $value);
        	}
        	
        	if ($component->isEmpty()) continue;

        	$this->addComponent($component);
        }

        if ($orGroup) {
        	$this->addComponent(self::create($orGroup, true));
        }
        
        if (count($crits) > 1) {
            $this->setJunction($or ? 'OR' : 'AND');
        }
    }
    
    public function find($key)
    {
        $ret = array();
        foreach ($this->get_component_crits() as $cc) {
            if (is_object($key)) {
                if ($cc == $key) {
                    $ret[] = $cc;
                } elseif ($cc instanceof Utils_RecordBrowser_Recordset_Query_Crits_Compound) {
                    $crit = $cc->find($key);
                    if (is_array($crit)) {
                        $ret = array_merge($ret, $crit);
                    }
                }
            } else {
                $crit = $cc->find($key);
                if (is_array($crit)) {
                    $ret = array_merge($ret, $crit);
                } elseif (!is_null($crit)) {
                    $ret[] = $crit;
                }
            }
        }
        return $ret ?: null;
    }
    
    public static function and($crits, $_ = null)
    {
    	return self::create(func_get_args());
    }
    
    public static function or($crits, $_ = null)
    {
    	return self::create(func_get_args(), true);
    }    
}
