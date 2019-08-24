<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_RecordBrowser_Recordset_Query_Crits
{
    protected static $placeholderCallbacks = [];

    protected $active = true;

    /**
     * Replace crits value to other value or disable crits that uses this value.
     *
     * Object will be changed! Clone it before use if you'd like to hold original one.
     *
     * @param Utils_RecordBrowser_Recordset 									$recordset
     * @param Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder
     * @param boolean 															$humanReadable replace with placeholder value or label
     */
    abstract function replacePlaceholder(Utils_RecordBrowser_Recordset $recordset, Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder, $humanReadable = false);

    /**
     * Method to lookup in crits for certain fields crits or crits objects
     *
     * @param string|object $key key to find or crits object
     *
     * @return array Crits objects in array that matches $key
     */
    abstract function find($key);
    
    /**
     * Validate the values against the crit object
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
    
    abstract public function isEmpty();
    
    /**
     * @param string|Utils_RecordBrowser_Recordset $recordset
     * @param boolean $asHtml
     */
    abstract public function toWords($recordset, $asHtml = true);

    final public static function registerPlaceholderCallback($callback)
    {
    	self::$placeholderCallbacks[] = $callback;
    }

    /**
     * Replace all registered placeholders
     *
     * Returns a modified cloned dbject. Current object will not be changed.
     *
     * @param Utils_RecordBrowser_Recordset $recordset The recordset that the placeholders shoul the replaced for
     * @param bool $humanReadable Use special value or it's human readable form
     *
     * @return Utils_RecordBrowser_Recordset_Query_Crits New object with replaced values
     */
    final public function toFinal(Utils_RecordBrowser_Recordset $recordset, $humanReadable = false)
    {
        /**
         * @var Utils_RecordBrowser_Recordset_Query_Crits $crits
         */
        $crits = clone $this;
        
        /** @var Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder */
        foreach ($crits->getPlaceholders() as $placeholder) {
        	$crits->replacePlaceholder($recordset, $placeholder, $humanReadable);
        }
        
        return $crits;
    }

    final protected static function getPlaceholders($user = null)
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
                	
                	if (!isset($cache[$user][$key])	|| $cache[$user][$key]->getPriority() < $placeholder->getPriority()) {
                    	$cache[$user][$key] = $placeholder;
                    }
                }
            }
        }
        
        return $cache[$user];
    }
    
    final public static function stripModifiers($key)
    {
    	return substr($key, strlen(self::parseModifiers($key)));
    }
    
    final public static function parseModifiers($key)
    {
    	$result = preg_split( '/[a-zA-Z:_\[\]0-9\s]/i' , $key, -1, PREG_SPLIT_NO_EMPTY);
    	
    	return $result? reset($result): '';
    }
        
    public function deactivate()
    {
        return $this->setActive(false);
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
                  
    public static function __set_state($array)
    {
    	$crits = new static();
    	
    	foreach ($array as $key => $value) {
    		$crits->{$key} = $value;
    	}
    	
    	return $crits;
    }
}
