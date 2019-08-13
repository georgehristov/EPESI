<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Single extends Utils_RecordBrowser_Recordset_Query_Crits_Basic
{
	use Utils_RecordBrowser_Recordset_Query_Crits_Trait_Components;
	
	public function __construct($key, $value, $operator = '')
	{
		parent::__construct($key, $value, $operator);
		
		$this->setJunction('OR');
	}
	
	public function setValue($values) 
	{
		$this->value = is_array($values)? $values: [$values];
		
		foreach ($this->value as $value) {
			$this->addComponent(Utils_RecordBrowser_Recordset_Query_Crits_Basic::create($this->getKey(), $value, $this->getOperator()));
		}
		
		return $this;
	}
		
	public function getQuery(Utils_RecordBrowser_Recordset $recordset)
	{
		$ret = $recordset->createQuery();

		foreach ($this->getComponents(true) as $crits) {
			$ret = Utils_RecordBrowser_Recordset_Query::merge($ret, $crits->getQuery($recordset));
		}
		
		return $ret;
	}
}
