<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_RawSQL extends Utils_RecordBrowser_Recordset_Query_Crits
{
    protected $sql;
    protected $negation_sql;
    protected $vals;

    function __construct($sql, $negation_sql = false, $values = array())
    {
        $this->sql = $sql;
        $this->negation_sql = $negation_sql;
        if (!is_array($values)) {
            $values = array($values);
        }
        $this->vals = $values;
    }
    
    public static function __set_state($array)
    {
    	$crits = new static();
    	
    	foreach ($array as $key => $value) {
    		$crits->{$key} = $value;
    	}
    	
    	return $crits;
    }

    /**
     * @return mixed
     */
    public function get_sql()
    {
        return $this->sql;
    }

    /**
     * @return boolean
     */
    public function get_negation_sql()
    {
        return $this->negation_sql;
    }

    /**
     * @return array
     */
    public function get_vals()
    {
        return $this->vals;
    }

    public function normalize()
    {
        if (!$this->get_negation()) return;
        
       	if ($this->negation_sql === false)
       		throw new ErrorException('Cannot normalize RawSQL crits without negation_sql param!');
            
        $this->set_negation(false);
        $tmp_sql = $this->negation_sql;
        $this->negation_sql = $this->sql;
        $this->sql = $tmp_sql;
    }

    public function replace_value($search, $replace, $deactivate = false)
    {
        $deactivate = $deactivate && ($replace === null);
        if (is_array($this->vals)) {
            foreach ($this->vals as $k => $v) {
                if ($v === $search) {
                    if ($deactivate) {
                        $this->set_active(false);
                    } else {
                        $this->vals[$k] = $replace;
                    }
                }
            }
        } elseif ($this->vals === $search) {
            if ($deactivate) {
                $this->set_active(false);
            } else {
                $this->vals = $replace;
            }
        }
    }

    public function find($key)
    {

    }

}

