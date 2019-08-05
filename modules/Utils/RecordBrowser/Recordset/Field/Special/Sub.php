<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Sub extends Utils_RecordBrowser_Recordset_Field {
	public function processAdd($values) {
		return false;
	}
}
