<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_RawSQL extends Utils_RecordBrowser_Recordset_Query_Crits
{
    protected $sql;
    protected $negationSql;
    protected $values;

    public static function create($sql, $negationSql = false, $values = [])
    {
        return new static ($sql, $negationSql, $values);
    }
    
    public function __construct($sql, $negationSql = false, $values = [])
    {
        $this->sql = $sql;
        $this->negationSql = $negationSql;
        $this->values = is_array($values)? $values: [$values];
    }
    
    public function validate(Utils_RecordBrowser_Recordset $recordset, $values)
    {
    	if (!$this->isActive()) return [];
    	
    	if ($sql = $this->getSql()) {
    		$sql = "AND $sql";
    	}
    	$ret = DB::GetOne("SELECT 1 FROM {$recordset->getTab()}_data_1 WHERE id=%d $sql", [$values['id']?? 0]);
    	
    	return $ret? []: [$this];
    }
    
    public function negate()
    {
        if ($this->negationSql === false)
       		throw new ErrorException('Cannot negate RawSQL crits without negationSql param!');

        $sql = $this->negationSql;
        $this->negationSql = $this->sql;
        $this->sql = $sql;
        
        return $this;
    }

    public function toWords($recordset, $html = true)
    {
    	$sql = $this->get_negation() ? $this->getNegationSql() : $this->getSql();
    	$value = implode(', ', $this->getValues());
    	
    	return __('Raw SQL') . ': ' . "'{$sql}'" . __('with values') . ': ' . "({$value})";
    }
       
    public function getQuery(Utils_RecordBrowser_Recordset $recordset)
    {
    	return $recordset->createQuery($this->getSql(), $this->getValues());
    }
        
    /**
     * @return mixed
     */
    public function getSql()
    {
    	return $this->sql;
    }
    
    /**
     * @return boolean
     */
    public function getNegationSql()
    {
    	return $this->negationSql;
    }
    
    /**
     * @return array
     */
    public function getValues()
    {
    	return $this->values;
    }
    
    
    public function replaceValue($search, $replace, $deactivateOnNull = false) {}
    
    public function find($key) {}
}

