<?php

/**
 * Utils_RecordBrowser_BrowseMode class
 *
 * Provides BrowseMode management functionality for the Utils_RecordBrowser module
 * Extend the class to define various browse modes using the methods
 * Register the browse mode by calling Your_BrowseMode::register method
 *
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2019, X Systems Ltd
 * @license MIT
 * @version 2.0
 * @package epesi-utils
 * @subpackage Utils_RecordBrowser
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_BrowseMode {
	/**
	 * @var Utils_RecordBrowser_BrowseMode[]
	 */
	protected static $registry = [];
	
	protected static $key = '__ALL__';
	protected static $label = 'All';
	
	/**
	 * Order to apply on the browsed records for the specific BrowseMode
	 *
	 * @return array
	 */
	public function order() {
		return [];
	}
	
	/**
	 * Crits to filter the browsed records for the specific BrowseMode
	 * Leaving empty will not display this browse mode in the select list
	 *
	 * @return array | Utils_RecordBrowser_Crits
	 */
	public function crits() {
		return [];
	}
	
	/**
	 * Record info to be appended to the info tooltip
	 * 
	 * @param array $record
	 * @return string
	 */
	public function recordInfo(Utils_RecordBrowser_Recordset_Record $record) {
		return [];
	}
		
	/**
	 * A list of icons to be added to the record actions
	 * 
	 * @param Utils_RecordBrowser_Recordset_Record $record
	 * @return array
	 */
	public function recordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		return [];
	}
		
	/**
	 * Definitions of user settings (as form fields) used by the particular browse mode
	 * 
	 * @return array
	 */
	public function userSettings() {
		return [];
	}
	
	/**
	 * Provide module settings form field definitions and set the values if same provided
	 * 
	 * @param Utils_RecordBrowser_Recordset $recordset
	 * @param array $values
	 * @return array
	 */
	public function moduleSettings(Utils_RecordBrowser_Recordset $recordset, $values = []) {
		return [];
	}
	
	/**
	 * Process the record values based on mode and return processed values
	 * 
	 * @param array $values
	 * @param string $mode 
	 * @param string $tab
	 * @return array | bool
	 */
	public function process($values, $mode, $tab) {
		return $values;
	}
	
	/**
	 * Defines if the browse mode is available for the particular recordset
	 * 
	 * @param Utils_RecordBrowser_Recordset $recordset
	 * @return boolean
	 */
	public function isAvailable(Utils_RecordBrowser_Recordset $recordset) {
		return true;
	}
	
	final public static function getKey() {
		return static::$key;
	}
	
	final public static function getLabel() {
		return _M(static::$label);
	}
	
	/**
	 * Register a class or classes as browse mode controller
	 * If left emtpy the caller class is registered
	 * 
	 * @param array $classes
	 */
	final public static function register($classes = []) {
		$classes = $classes?: static::class;

		foreach (is_array($classes)? $classes: [$classes] as $class) {
			self::$registry[$class::getKey()] = new $class();
		}
	}
	
	/**
	 * Get one of the registered controllers based on its key
	 * 
	 * @param string $key
	 * @return Utils_RecordBrowser_BrowseMode
	 */
	final public static function get($key = null) {
		return self::$registry[$key]?? new static();
	}
		
	final public function getOrder() {
		return $this->order();
	}
	
	final public function getCrits() {
		return $this->crits();
	}
		
	final public static function getRecordInfo(Utils_RecordBrowser_Recordset_Record $record) {
		$ret = [];
		foreach (self::getRegistry() as $controller) {
			$ret = array_merge($ret, $controller->recordInfo($record)?: []);
		}
		
		return $ret;
	}
	
	final public static function getRecordActions(Module $module, Utils_RecordBrowser_Recordset_Record $record, $mode) {
		$ret = [];
		foreach (self::getRegistry() as $controller) {
			$recordActions = $controller->recordActions($module, $record, $mode);
			
			$recordActions = is_array($recordActions)? $recordActions: [$recordActions];
			
			$ret = array_merge($ret, array_filter($recordActions));
		}
		
		return $ret;
	}
		
	final public static function getUserSettings() {
		$ret = [];
		foreach (self::getRegistry() as $controller) {
			$ret = array_merge($ret, $controller->userSettings());
		}
		
		return $ret;
	}
		
	final public static function getModuleSettings(Utils_RecordBrowser_Recordset $recordset) {
		$ret = [];
		foreach (self::getRegistry() as $controller) {
			$ret = array_merge($ret, $controller->moduleSettings($recordset));
		}
		
		return $ret;
	}
		
	final public static function setModuleSettings(Utils_RecordBrowser_Recordset $recordset, $values) {
		$ret = [];
		foreach (self::getRegistry() as $controller) {
			$ret = array_merge($ret, $controller->moduleSettings($recordset, $values));
		}
		
		return $ret;
	}

	/**
	 * @return Utils_RecordBrowser_BrowseMode[]
	 */
	final public static function getRegistry() {
		return [
				self::getKey() => new self()
		] + self::$registry;
	}
	
	/**
	 * Returns a select list of available browse modes that also apply some filtering on the records
	 * 
	 * @param string | Utils_RecordBrowser_Recordset $recordset
	 * @param array | bool $disabled
	 * @return array
	 */
	final public static function getSelectList($recordset, $disabled = []) {
		if ($disabled === true) return [];
		
		$ret = [];
		foreach (self::getRegistry() as $key => $controller) {
			$disabled = ($disabled[$key]?? false) || ($key != '__ALL__' && ! $controller->getCrits());
			
			if ($disabled || ! $controller->isAvailable($recordset)) continue;
			
			$ret[$key] = _V($controller->getLabel());
		}

		return array_diff(array_keys($ret), [self::getKey()])? $ret: [];		
	}
	
	final public static function processValues($values, $mode, $tab) {
		$recordset = Utils_RecordBrowser_Recordset::create($tab);
		
		foreach (self::getRegistry() as $controller) {
			if (! $controller->isAvailable($recordset)) continue;
			
			$values = $controller->process($values, $mode, $tab);
		}
		
		return $values;
	}
}