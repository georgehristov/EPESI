<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode_Controller {
	/**
	 * @var Utils_RecordBrowser_BrowseMode_Controller[]
	 */
	protected static $registry = [];
	
	protected static $key = '__ALL__';
	protected static $label = 'All';
	
	public function order() {
		return [];
	}
	
	public function crits() {
		return [];
	}
		
	public function columns() {
		return [];
	}
		
	public function recordInfo($record) {
		return '';
	}
		
	public function userSettings() {
		return [];
	}
	
	public function process($values, $mode, $tab) {
		return $values;
	}
	
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return true;
	}
	
	public static function getKey() {
		return static::$key;
	}
	
	public static function getLabel() {
		return _M(static::$label);
	}
	
	final public static function register($classes = []) {
		$classes = $classes?: static::class;

		foreach (is_array($classes)? $classes: [$classes] as $class) {
			self::$registry[$class::getKey()] = new $class();
		}
	}
	
	final public static function getController($key) {
		return self::$registry[$key]?? new self();
	}
		
	public function getOrder() {
		return $this->order();
	}
	
	public function getCrits() {
		return $this->crits();
	}
		
	public function getRecordInfo($record) {
		return $this->recordInfo($record);
	}
		
	public function getUserSettings() {
		return $this->userSettings();
	}
		
	final public static function getColumns($recordset, $disabled = []) {
		$ret = [];
		foreach (self::getRegistry() as $key => $controller) {
			$disabled = $disabled[$key]?? false;
			
			if ($disabled || ! $controller->isAvailable($recordset)) continue;
			
			$ret = array_merge($ret, $controller->columns());
		}
		
		return $ret;
	}
	
	/**
	 * @return Utils_RecordBrowser_BrowseMode_Controller[]
	 */
	final public static function getRegistry() {
		return [
				self::getKey() => new self()
		] + self::$registry;
	}
	
	final public static function getSelectList($recordset, $disabled = []) {
		if ($disabled === true) return [];
		
		$ret = [];
		foreach (self::getRegistry() as $key => $controller) {
			$disabled = $disabled[$key]?? false;
			
			if ($disabled || ! $controller->isAvailable($recordset)) continue;
			
			$ret[$key] = _V($controller->getLabel());
		}

		return array_diff(array_keys($ret), [self::getKey()])? $ret: [];		
	}
}