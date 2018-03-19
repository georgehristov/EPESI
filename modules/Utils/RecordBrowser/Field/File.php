<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_MultiSelect extends Utils_RecordBrowser_Field_Select {
	public function defaultValue() {
		return [];
	}	
	
	public static function decodeValue($value, $htmlspecialchars = true) {
		return Utils_RecordBrowserCommon::decode_multi($value);
	}
	
	public function prepareSqlValue(& $files) {
		$files = $this->decodeValue($files);
		if ($this['param']['max_files'] && count($files) > $this['param']['max_files']) {
			throw new Exception('Too many files in field ' . $this['id']);
		}
		$files = $this->encodeValue(Utils_FileStorageCommon::add_files($files));
		return true;
	}
	
	public function processAddedValue($value, $record) {
		// update backref
		$value = $this->decodeValue($value);
		Utils_FileStorageCommon::add_files($value, "rb:$this[tab]/$record[id]/$this[pkey]");
		
		return $value;
	}
}
