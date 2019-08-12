<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_RecordBrowser_Recordset_Query_Crits
{
    protected static $placeholderCallbacks = [];

    protected $active = true;

    /**
     * Replace crits value to other value or disable crits that uses this value.
     *
     * Object will be changed! Clone it before use if you'd like to hold
     * original one.
     *
     * @param mixed $search
     * @param mixed $replace
     * @param bool  $deactivateOnNull pass true and null as replace to disable crit
     */
    abstract function replaceValue($search, $replace, $deactivateOnNull = false);

    /**
     * Method to lookup in crits for certain fields crits or crits objects
     *
     * @param string|object $key key to find or crits object
     *
     * @return array Crits objects in array that matches $key
     */
    abstract function find($key);
    
    /**
     * Validate the crit object
     * 
     * @param Utils_RecordBrowser_Recordset $recordset
     * @param array $values
     * @return array $issues
     */
    abstract public function validate(Utils_RecordBrowser_Recordset $recordset, $values);
        
    /**
     * Negate this crit object
     * 
     * @return Utils_RecordBrowser_Recordset_Query_Crits $this
     */
    abstract public function negate();
        
    /**
     * @param Utils_RecordBrowser_Recordset $recordset
     * @return Utils_RecordBrowser_Recordset_Query
     */
    abstract public function getQuery(Utils_RecordBrowser_Recordset $recordset);
    
    /**
     * @param string|Utils_RecordBrowser_Recordset $recordset
     * @param boolean $html
     */
    abstract public function toWords($recordset, $html = true);
    

    public static function registerPlaceholderCallback($callback)
    {
    	self::$placeholderCallbacks[] = $callback;
    }

    /**
     * Replace all registered placeholders
     *
     * Object will be cloned. Current object will not be changed.
     *
     * @param bool $human_readable Use special value or it's human readable form
     *
     * @return Utils_RecordBrowser_Recordset_Query_Crits New object with replaced values
     */
    public function replacePlaceholders($humanReadable = false)
    {
        /**
         * @var Utils_RecordBrowser_Recordset_Query_Crits $crits
         */
        $crits = clone $this;
        
        /** @var Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder */
        foreach ($this->getPlaceholders() as $placeholder) {
            $crits->replaceValue($placeholder->getKey(), $placeholder->getValue($humanReadable), $placeholder->getDeactivateOnNull($humanReadable));
        }
        
        return $crits;
    }

    protected static function getPlaceholders($user = null)
    {
        static $cache = [];
        
        $user = $user?: Acl::get_user();
        
        if (!isset($cache[$user])) {
            $cache[$user] = [];
            foreach (self::$placeholderCallbacks as $callback) {
                $ret = call_user_func($callback);

                /** @var Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder */
                foreach (is_array($ret)? $ret: [$ret] as $placeholder) {
                	$key = $placeholder->getKey();
                	
                	if (!isset($cache[$user][$key])	|| $cache[$user][$key]->getPriority() < $placeholder->getPriority()
                    ) {
                    	$cache[$user][$key] = $placeholder;
                    }
                }
            }
        }
        
        return $cache[$user];
    }
        
    /**
     * @param bool $active
     */
    public function setActive($active = true)
    {
        $this->active = ($active == true);
        
        return $this;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }
    
    public function isCompound()
    {
        return false;
    }
            
    public function isEmpty()
    {
        return false;
    }
            
    public static function stripModifiers($key)
    {
    	return substr($key, strlen(self::parseModifiers($key)));
    }
    
    public static function parseModifiers($key)
    {
    	$result = preg_split( '/[a-zA-Z:_\[\]]/i' , $key, -1, PREG_SPLIT_NO_EMPTY);
    	
    	return $result? reset($result): '';
    }
    
    public static function __set_state($array)
    {
    	$crits = new static();
    	
    	foreach ($array as $key => $value) {
    		$crits->{$key} = $value;
    	}
    	
    	return $crits;
    }
}
