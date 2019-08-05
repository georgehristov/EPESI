<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Tests_MockRecordset extends Utils_RecordBrowser_Recordset {
	protected $printer;
	protected $clipboardPattern;
	protected $records;
	
	public static function create($tab, $force = false) {
		return new static($tab);
	}
	
	public static function exists($tab, $flush = false, $quiet = false){
		return true;
	}	
	
	public function getAdminFields() {
		return $this->adminFields;
	}
	
	public function getCallbacks($field) {
		return [];
	}
	
	public function setAddons($addons) {
		$this->addons = $addons;
		
		return $this;
	}
	
	public function getAddons() {
		return $this->addons;
	}
	
	public function setPrinter($printer) {
		$this->printer = $printer;
		
		return $this;
	}
	public function getPrinter() {
		return $this->printer;
	}
	
	public function setProperties($properties) {
		$this->properties = $properties;
		
		return $this;
	}
		
	public function getProperties() {
		return $this->properties;
	}
		
	public function setClipboardPattern($clipboardPattern) {
		$this->clipboardPattern = $clipboardPattern;
		
		return $this;
	}
		
	public function getClipboardPattern($with_state = false) {
		if($with_state) {
			return [$this->clipboardPattern => 1];
		}

		return $this->clipboardPattern;
	}
		
	public function setRecords($records) {
		$this->records = $records;
		
		return $this;
	}
	
	public function getRecord($id, $htmlspecialchars = true) {
		return $this->records[$id]?? [];
	}
}



