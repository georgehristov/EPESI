<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Section implements ArrayAccess
{
	
	protected static $arrayMap = ['sql', 'values'];
    protected $sql = '';    
    protected $values = [];
    
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
	
	/**
	 * @param Utils_RecordBrowser_Recordset_Query_Section|null $sectionA
	 * @param Utils_RecordBrowser_Recordset_Query_Section|null $sectionB
	 * @param string $junction
	 * @return Utils_RecordBrowser_Recordset_Query_Section
	 */
	public static function merge($sectionA, $sectionB, $junction = 'OR') {
		$sql = [];
		$values = [];
		
		/**
		 * @var Utils_RecordBrowser_Recordset_Query_Section $section
		 */
		foreach ([$sectionA, $sectionB] as $section) {
			if (!$section) continue;
			
			$sql[] = $section->getSql();
			
			$values = array_merge($values, $section->getValues());
		}
		
		return self::create(implode(" $junction ", $sql), $values);
	}
	
	public function getCountSql()
	{
		return 'SELECT COUNT(*) FROM ' . $this->getTable() . ' WHERE ' . $this->getSQL();
	}
	
	public function getSelectSql($order = [])
	{
		return 'SELECT ' . $this->getTableAlias() . '.* FROM ' . $this->getSQL() . $this->getOrderSql($order);
	}
	
	protected function getTable()
	{
		return $this->getRecordset()->getTab() . '_data_1 ';
	}
	
	protected function getTableAlias()
	{
		return $this->getRecordset()->getTabAlias();
	}

}
