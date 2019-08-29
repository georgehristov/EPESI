<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value
{
    protected $value;
    protected $isRawSql = false;
    
    public static function create($value, $isRawSql = false)
    {
    	if (is_object($value)) return $value;
    	
    	$isRawSql = is_string($isRawSql)? stripos($isRawSql, '"') !== false: $isRawSql;
    	
    	return new static ($value, $isRawSql);
    }
    
    public function __construct($value, $isRawSql = false)
    {
    	$this->setValue($value)->setRawSql($isRawSql);
    }

	public function getValue() {
		return $this->value;
	}

	public function isRawSql() {
		return $this->isRawSql;
	}

	public function setValue($value) {
		$this->value = $value;
		
		return $this;
	}
	
	protected  function setPrefix($prefix) {
		$this->value = $prefix . $this->value;
		
		return $this;
	}

	public function setRawSql($isRawSql) {
		$this->isRawSql = $isRawSql;
		
		return $this;
	}
	
	public function getSQL() {
		$value = $this->getValue();
		
		if ($this->isRawSql()) return $value;
		
		return preg_match('/^[A-Za-z]$/', $value)? "'%" . $value . "%'": $value;
	}
	
	public function __toString() {
		return $this->getSQL();
	}
	
	public function replace(Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder, $humanReadable = false) {
		if ($this->isRawSql()) return false;

		if ($match = $this->getValue() === $placeholder->getKey()) {
			$this->setValue($placeholder->getValue($humanReadable));
			
// 			if ($humanReadable) {
// 				$this->setPrefix('__PLACEHOLDER__::');
// 			}
		}
		
		return $match;
	}

	public function getPlaceholder() {
		$value = explode('::', $this->getValue(), 3);
		
		if ($value[0] != '__PLACEHOLDER__') return false;
		
		array_shift($value);

		return implode('::', $value);
	}
}
