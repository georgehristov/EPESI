<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Section implements ArrayAccess
{
	
	protected static $arrayMap = ['sql', 'values'];
    protected $sql = '';    
    protected $values = [];
    protected $order = [];
    protected $numrows;
    protected $offset;
    
    /**
     * @param string $sql
     * @param array $values
     * @return Utils_RecordBrowser_Recordset_Query_Section
     */
    public static function create($sql = '', $values = [])
    {
    	return new static ($sql, $values);
    }
    
    public function __construct($sql = '', $values = [])
    {
    	if (is_array($sql)) {
    		list($sql, $values) = $sql;
    	}
    	
    	$this->setSql($sql)->setValues($values);
    }
    
	public function getSQL() {
		return $this->sql;
	}

	public function getValues() {
		return $this->values;
	}

	protected function setSql($sql) {
		$this->sql = $sql;
		
		return $this;
	}

	protected function setValues($values) {
		$this->values = $values;
		
		return $this;
	}
	
	public function offsetSet($offset, $value) {}
	
	public function offsetExists($offset) {
		return in_array($offset, array_keys(self::$arrayMap));		
	}
	
	public function offsetUnset($offset) {}
	
	public function offsetGet($offset) {
		if (!$key = self::$arrayMap[$offset]?? '') return;
		
		return $this->{$key};
	}
	
	public static function merge(Utils_RecordBrowser_Recordset_Query_Section $section, $_) {
		$sql = [];
		$values = [];
		
		/**
		 * @var Utils_RecordBrowser_Recordset_Query_Section $section
		 */
		foreach (func_get_args() as $section) {
			$sql[] = $section->getSql();
			
			$values = array_merge($values, $section->getValues());
		}
		
		return self::create(implode(' OR ', $sql), $values);
	}

}
