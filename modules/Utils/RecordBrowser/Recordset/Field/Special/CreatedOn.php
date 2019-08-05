<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_CreatedOn extends Utils_RecordBrowser_Recordset_Field {
	public function defaultValue() {
		return date('Y-m-d H:i:s');
	}
}
