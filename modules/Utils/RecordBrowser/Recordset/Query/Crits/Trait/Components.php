<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

trait Utils_RecordBrowser_Recordset_Query_Crits_Trait_Components
{
	/**
	 * @var Utils_RecordBrowser_Recordset_Query_Crits[]
	 */
	protected $components = [];
	protected $junction;

	public function getComponents($activeOnly = false) 
	{
		return $activeOnly? array_filter($this->components, function(Utils_RecordBrowser_Recordset_Query_Crits $crits) {
			return $crits->isActive();
		}): $this->components;
	}
	
	public function addComponent(Utils_RecordBrowser_Recordset_Query_Crits $crits) 
	{
		$this->components[] = $crits;
	}
	
	public function isEmpty()
	{
		return empty($this->getComponents(true));
	}
	
	public function isCompound()
	{
		return count($this->getComponents(true)) > 1;
	}
	
    public function __clone()
    {
    	foreach ($this->components as $k => $v) {
    		$this->components[$k] = clone $v;
    	}
    }

    public function replacePlaceholder(Utils_RecordBrowser_Recordset $recordset, Utils_RecordBrowser_Recordset_Query_Crits_Basic_Value_Placeholder $placeholder, $humanReadable = false)
    {
    	foreach ($this->getComponents() as $crits) {
    		$crits->replacePlaceholder($recordset, $placeholder, $humanReadable);
    	}
    }
    
    public function validate(Utils_RecordBrowser_Recordset $recordset, $values)
    {
    	if (!$this->isActive()) return [];
    	
    	$values = is_numeric($values)? $recordset->getRecord($values)->toArray(): $values;
    	
    	$issues = [];    	
    	foreach ($this->getComponents() as $crits) {
    		$issues = array_merge($issues, $crits->validate($recordset, $values));
    	}

    	return $issues;
    }
    
    /**
     * Use De Morgan's laws to negate
     */
    public function negate()
    {
    	$this->junction = $this->junction == 'OR' ? 'AND' : 'OR';
    	
    	foreach ($this->getComponents() as $crits) {
    		$crits->negate();
    	}
    	
    	return $this;
    }
    
    public function toWords($recordset, $asHtml = true)
    {
    	if (!$this->isActive() || $this->isEmpty()) return '';
    	
    	$parts = [];
    	foreach ($this->getComponents() as $crits) {
    		$words = $crits->toWords($recordset, $asHtml);
    		
    		$parts[] = $this->isCompound() && $crits->isCompound()? "($words)": $words;
    	}
    	
    	$glue = ' ' . _V(strtolower($this->getJunction())) . ' ';
    	
    	return implode($glue, $parts);
    }
       
    /**
     * @param string | Utils_RecordBrowser_Recordset $recordset
     * @return Utils_RecordBrowser_Recordset_Query
     */
    public function getQuery($recordset)
    {
    	$recordset = Utils_RecordBrowser_Recordset::create($recordset);
    	
    	$ret = $recordset->createQuery();
    	foreach ($this->getComponents(true) as $crits) {
    		$ret = Utils_RecordBrowser_Recordset_Query::merge($ret, $crits->getQuery($recordset), $this->getJunction());
    	}
    	
    	return $ret;
    }
        
    /**
     * @return null|string
     */
    public function getJunction()
    {
    	return $this->junction;
    }
    
    protected function setJunction($junction)
    {
    	$this->junction = $junction;
    	
    	return $this;
    }
}
