<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_Hidden extends Utils_RecordBrowser_Field_Instance {
	
    public function isOrderable() {
    	return false;
    }
}
