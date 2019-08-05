<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_CreatedBy extends Utils_RecordBrowser_Recordset_Field {
	public function defaultValue() {
		return Acl::get_user();
	}
}
