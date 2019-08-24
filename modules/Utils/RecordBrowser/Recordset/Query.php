<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query implements ArrayAccess
{
	
	protected static $arrayMap = ['sql', 'values'];
	/**
	 * @var Utils_RecordBrowser_Recordset
	 */
	protected $recordset;
    protected $sql = '';    
    protected $values = [];
    
	/**
     * @param string $whereSql
     * @param array $values
     * @return Utils_RecordBrowser_Recordset_Query
     */
    public static function create($recordset, $sql = '', $values = [])
    {
    	return new static ($recordset, $sql, $values);
    }
    
    public function __construct($recordset, $sql = '', $values = [])
    {
    	$this->setRecordset($recordset);
    	
    	if (is_array($sql)) {
    		list($sql, $values) = $sql;
    	}
    	
    	$this->setSql($sql)->setValues($values);
    }
    
	public function getSql() 
	{
		return $this->sql;
	}

	public function getValues() 
	{
		return $this->values;
	}

	protected function setSql($sql) 
	{
		$this->sql = $sql;
		
		return $this;
	}

	protected function setValues($values) 
	{
		$this->values = $values;
		
		return $this;
	}
	
	public function offsetSet($offset, $value) {}
	
	public function offsetExists($offset) 
	{
		return in_array($offset, array_keys(self::$arrayMap));		
	}
	
	public function offsetUnset($offset) {}
	
	public function offsetGet($offset) {
		if (!$key = self::$arrayMap[$offset]?? '') return;
		
		return $this->{$key};
	}
	
	/**
	 * @param Utils_RecordBrowser_Recordset_Query $queryA
	 * @param Utils_RecordBrowser_Recordset_Query $queryB
	 * @param string $junction
	 * @return Utils_RecordBrowser_Recordset_Query
	 */
	public static function merge(Utils_RecordBrowser_Recordset_Query $queryA, Utils_RecordBrowser_Recordset_Query $queryB, $junction = 'OR') 
	{
		$sql = [];
		$values = [];

		if ($queryA->getDataTable() != $queryB->getDataTable()) {
			trigger_error("Attempting to merge queries on different recordsets: {$queryA->getDataTable()} and {$queryB->getDataTable()}", E_USER_ERROR);
		}
		
		/**
		 * @var Utils_RecordBrowser_Recordset_Query $query
		 */
		foreach ([$queryA, $queryB] as $query) {
			if (!$query) continue;

			$sql[] = $query->getSql();
			
			$values = array_merge($values, $query->getValues());
		}
		
		$sql = array_filter($sql);
		
		$multi = count($sql) > 1;

		$sql = implode(" $junction ", $sql);

		return $queryA->getRecordset()->createQuery($multi? '(' . $sql . ')': $sql, $values);
	}
	
	public function getCountSql()
	{
		return 'SELECT COUNT(*) FROM ' . $this->getDataTableWithAlias() . $this->getWhereSql();
	}
	
	public function getSelectSql($order = [])
	{
		return 'SELECT ' . $this->getDataTableAlias() . '.* FROM ' . $this->getDataTableWithAlias() . $this->getWhereSql() . $this->getOrderSql($order);
	}
	
	protected function getWhereSql()
	{
		$sql = $this->getSql();
		
		return ($sql? ' WHERE ': '') . $sql;
	}

	protected function getDataTable() 
	{
		return $this->getRecordset()->getDataTable();
	}
	
	protected function getDataTableAlias() 
	{
		return $this->getRecordset()->getDataTableAlias();
	}
	
	protected function getDataTableWithAlias() 
	{
		return $this->getRecordset()->getDataTableWithAlias();
	}
	
	public function matchRecordset($recordset) 
	{
		$tab = is_string($recordset)? $recordset: $recordset->getTab();
		
		return $tab == $this->getRecordset()->getTab();
	}
	
	/**
	 * @return Utils_RecordBrowser_Recordset
	 */
	public function getRecordset() 
	{
		return $this->recordset;
	}
	
	/**
	 * @param Utils_RecordBrowser_Recordset $recordset
	 */
	public function setRecordset($recordset) 
	{
		$this->recordset = Utils_RecordBrowser_Recordset::create($recordset);
		
		return $this;
	}
	
	public function getOrderSql($order)
	{
		$orderby = [];

		foreach ($order?: [] as $k => $v) {
			if (!is_string($k)) break;
			
			if ($field = $this->getRecordset()->getField($k, true)) {
				$order[] = ['column' => $field->getName(), 'order' => $field->getName(), 'direction' => $v];
			}
			
			unset($order[$k]);
		}
		
		foreach ($order as $v) {
			if (!$field = $this->getRecordset()->getField($v['order'], true)) continue;
				
			$orderby[] = $field->getSqlOrder($v['direction']);
		}
		
		return $orderby? ' ORDER BY' . implode(', ', $orderby): '';
	}
}
