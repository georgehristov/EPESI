<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Id extends Utils_RecordBrowser_Recordset_Field {
	public static function typeKey() {
		return 'id';
	}
	
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'id',
				'field' => _M('ID'),
				'type' => 'id',
				'active' => true,
				'visible' => false,
				'export' => true,
				'processing_order' => -1000,
		];
	}
	
	public function processAdd($values) {
		return false;
	}
	
	public function processEdit($values) {
		return false;
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		if (!$id = $record[':id']?? null) return '';
		
		if (!is_numeric($id)) return $id;
		
		return Utils_RecordBrowser_Recordset::create($tab)->getRecord($id)->createDefaultLinkedLabel($nolink, false);
	}
	
	public function getSqlId() {
		return $this->getId();
	}
	
	public function getSqlType() {
		return '%d';
	}
	
	public function getArrayId() {
		return ':' . $this->getId();
	}
	
	public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit)
	{
		if ($crit->getValue()->isRawSql()) {
			return $this->getRawSQLQuerySection($crit);
		}
		
		$field = $this->getQueryId();
		$operator = $crit->getSQLOperator();
		
		$value = $crit->getValue()->getValue();
		
		if (!is_numeric($value)) {
			$token = Utils_RecordBrowserCommon::decode_record_token($value);

			$value = $token['id']?? '';
		}
		
		$vals = [];
		if ($operator == DB::like() && ($value == '%' || $value == '%%')) {
			$sql = 'true';
		} 
		elseif ($value === '' || is_null($value)) {
			$sql_null = stripos($operator, '!') !== false? 'NOT': '';
			
			$sql = "$field IS $sql_null NULL OR $field $operator ''";
		} 
		else {
			$sql = "$field $operator %d";
			$vals[] = $value;
		}
		
		return $this->getRecordset()->createQuery($sql, $vals);
	}
}
