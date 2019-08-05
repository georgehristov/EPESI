<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Compound extends Utils_RecordBrowser_Recordset_Query_Crits
{
    protected $negation = false;
    protected $join_operator = null;

    /** @var Utils_RecordBrowser_CritsInterface[] $component_crits */
    protected $component_crits = array();

    public static function where($field, $operator, $value)
    {
        $crits_obj = new self();
        $crits = new Utils_RecordBrowser_Recordset_Query_Crits_Single($field, $operator, $value);
        $crits_obj->component_crits[]= $crits;
        return $crits_obj;
    }

    public function __construct($crits = null, $or = false)
    {
        if (!$crits) return;
        
        if (is_array($crits)) {
            $builder = new Utils_RecordBrowser_CritsBuilder();
            $crits = $builder->build_single($crits);
            $this->component_crits = $crits;
        } else {
            $this->component_crits[] = $crits;
        }
        if (is_countable($crits) && count($crits) > 1) {
            $this->join_operator = $or ? 'OR' : 'AND';
        }
    }
    
    public static function __set_state($array)
    {
    	$crits = new static();
    	
    	foreach ($array as $key => $value) {
    		$crits->{$key} = $value;
    	}
    	
    	return $crits;
    }

    public function normalize()
    {
        if ($this->get_negation()) {
            $this->set_negation(false);
            $this->join_operator = $this->join_operator == 'OR' ? 'AND' : 'OR';
            foreach ($this->component_crits as $c) {
                $c->negate();
            }
        }
        foreach ($this->component_crits as $c) {
            $c->normalize();
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
        return $ret ? $ret : null;
    }

    public function is_empty()
    {
        return empty($this->component_crits);
    }

    public function __clone()
    {
        foreach ($this->component_crits as $k => $v) {
            $this->component_crits[$k] = clone $v;
        }
    }

    protected function __op($operator, $crits)
    {
        $ret = $this;
        $crits_count = count($this->component_crits);
        if ($crits_count == 0) {
            $this->component_crits[] = $crits;
        } elseif ($crits_count == 1) {
            $this->join_operator = $operator;
            $this->component_crits[] = $crits;
        } else {
            if ($this->join_operator == $operator) {
                $this->component_crits[] = $crits;
            } else {
                $new = new self($this);
                $new->__op($operator, $crits);
                $ret = $new;
            }
        }
        return $ret;
    }

    public function _and($crits)
    {
        return $this->__op('AND', $crits);
    }

    public function _or($crits)
    {
        return $this->__op('OR', $crits);
    }

    /**
     * @return null|string
     */
    public function get_join_operator()
    {
        return $this->join_operator;
    }

    /**
     * @return Utils_RecordBrowser_CritsInterface[]
     */
    public function get_component_crits()
    {
        return $this->component_crits;
    }

    public function replace_value($search, $replace, $deactivate = false)
    {
        foreach ($this->component_crits as $c) {
            $c->replace_value($search, $replace, $deactivate);
        }
    }

    /**
     * @param array $crits Legacy array crits
     *
     * @return Utils_RecordBrowser_Crits new object like crits
     */
    public static function from_array($crits)
    {
        $builder = new Utils_RecordBrowser_CritsBuilder();
        $ret = $builder->build_from_array($crits);
        return $ret;
    }
}
