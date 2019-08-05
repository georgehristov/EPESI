<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_RecordBrowser_Recordset_Query_Crits
{
    protected static $replace_callbacks = array();

    /**
     * Make sure that all crits do not use negation. Reverse operators and logic
     * operators according to De Morgan's laws
     *
     * @return mixed
     */
    abstract function normalize();

    /**
     * Replace crits value to other value or disable crits that uses this value.
     *
     * Object will be changed! Clone it before use if you'd like to hold
     * original one.
     *
     * @param mixed $search
     * @param mixed $replace
     * @param bool  $deactivate pass true and null as replace to disable crit
     */
    abstract function replace_value($search, $replace, $deactivate = false);

    /**
     * Method to lookup in crits for certain fields crits or crits objects
     *
     * @param string|object $key key to find or crits object
     *
     * @return array Crits objects in array that matches $key
     */
    abstract function find($key);

    public static function register_special_value_callback($callback)
    {
        self::$replace_callbacks[] = $callback;
    }

    /**
     * Replace all registered special values.
     *
     * Object will be cloned. Current object will not be changed.
     *
     * @param bool $human_readable Use special value or it's human readable form
     *
     * @return Utils_RecordBrowser_CritsInterface New object with replaced values
     */
    public function replace_special_values($human_readable = false)
    {
        $new = clone $this;
        $user = Base_AclCommon::get_user();
        $replace_values = self::get_replace_values($user);
        /** @var Utils_RecordBrowser_ReplaceValue $rv */
        foreach ($replace_values as $rv) {
            $replacement = $human_readable ? $rv->get_human_readable() : $rv->get_replace();
            $deactivate = $human_readable ? false : $rv->get_deactivate();
            $new->replace_value($rv->get_value(), $replacement, $deactivate);
        }
        return $new;
    }

    protected static function get_replace_values($user)
    {
        static $replace_values_cache = array();
        if (!isset($replace_values_cache[$user])) {
            $replace_values_cache[$user] = array();
            foreach (self::$replace_callbacks as $callback) {
                $ret = call_user_func($callback);
                if (!is_array($ret)) {
                    $ret = array($ret);
                }
                /** @var Utils_RecordBrowser_ReplaceValue $rv */
                foreach ($ret as $rv) {
                    if (!isset($replace_values_cache[$user][$rv->get_value()])
                        || $replace_values_cache[$user][$rv->get_value()]->get_priority() < $rv->get_priority()
                    ) {
                        $replace_values_cache[$user][$rv->get_value()] = $rv;
                    }
                }
            }
        }
        return $replace_values_cache[$user];
    }

    /**
     * @return boolean
     */
    public function get_negation()
    {
        return $this->negation;
    }

    /**
     * @param boolean $negation
     */
    public function set_negation($negation = true)
    {
        $this->negation = $negation;
    }

    /**
     * Negate this crit object
     */
    public function negate()
    {
        $this->set_negation(!$this->get_negation());
    }

    /**
     * @param bool $active
     */
    public function set_active($active = true)
    {
        $this->active = ($active == true);
    }

    /**
     * @return bool
     */
    public function is_active()
    {
        return $this->active;
    }

    protected $negation = false;
    protected $active = true;
        
    public static function opposite_operator($operator)
    {
    	switch ($operator) {
    		case '=' : return '!=';
    		case '!=': return '=';
    		case '>=': return '<';
    		case '<' : return '>=';
    		case '<=': return '>';
    		case '>': return '<=';
    		case 'LIKE': return 'NOT LIKE';
    		case 'NOT LIKE': return 'LIKE';
    		case 'IN': return 'NOT IN';
    		case 'NOT IN': return 'IN';
    	}
    }
    
    public static function stripModifiers($key)
    {
    	return substr($key, strlen(self::parseModifiers($key)));
    }
    
    public static function parseModifiers($key)
    {
    	$result = preg_split( '/[a-zA-Z:_]/i' , $key, -1, PREG_SPLIT_NO_EMPTY);
    	
    	return $result? reset($result): '';
    }
}
