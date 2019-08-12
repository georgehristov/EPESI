<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_PageSplit extends Utils_RecordBrowser_Recordset_Field {
	public function getSqlId() {
		return false;
	}
	
	public function getSqlType() {
		return false;
	}
	
	public function processGet($values, $options = []) {
		return $values;
	}
}
