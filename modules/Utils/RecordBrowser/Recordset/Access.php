<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Access
{
	protected $recordset;
	protected $action;
	protected $values;
	protected $ruleCrits;
	protected $activeGrantRules;
	protected $accessFields;
	
	public static function create($recordset, $action, $values = null)
	{
		static $cache;
		
		$access = new static($recordset, $action, $values);
		
		$key = $access->getSignature();
				
		return $cache[$key] = $cache[$key]?? $access;
	}
	
	public function __construct($recordset, $action, $values = null)
	{
		$this->setRecordset($recordset);
		$this->setAction($action);
		$this->setValues($values);
	}

	public function getSignature() 
	{
		return md5(serialize([$this->getTab(), $this->getAction(), $this->getValues()]));
	}
	
	public function getUserAccess($adminMode = false) 
	{
		// access inactive records only in admin mode
		if (! $this->getRecordInactiveAccess() && ! ($adminMode && Acl::i_am_admin())) return false;

		if ($this->isFullDeny()) return false;
		
		if ($this->hasAction('browse')) return $this->getCritsRaw() !== null ? true: false;
		
		if ($this->getActiveGrantRules() === false) return false;
		
		if ($this->hasAction('delete')) return true;

		return $this->getAccessFields();
	}
	
	public function getCrits() 
	{
		if (!$this->getRecordInactiveAccess()) return false;

		return $this->getCritsRaw();
	}

	protected function getCritsRaw() 
	{
		if ($this->isFullDeny()) return null;
		
		if ($this->isFullGrant()) return true;
		
		$ret = null;
		
		$ruleCrits = $this->getRuleCrits();
		
		foreach ( $ruleCrits as $ruleId => $crits ) {
			if ($ruleId === 'restrict') continue;
			
			if (! $crits instanceof Utils_RecordBrowser_Recordset_Query_Crits) continue;
			
			// if crit is empty, then we have access to all records
			if ($crits->isEmpty()) $ret = $crits;
			
			if ($ret instanceof Utils_RecordBrowser_Recordset_Query_Crits && $ret->isEmpty()) continue;
			
			$ret = Utils_RecordBrowser_Crits::merge($ret, $crits, true);
		}
		
		// if there is any access granted - limit it based on restrict crits
		if ($ret !== null && $ruleCrits['restrict'] instanceof Utils_RecordBrowser_Recordset_Query_Crits) {
			$ret = Utils_RecordBrowser_Crits::merge($ret, $ruleCrits['restrict']);
		}
		
		return $ret;
	}
	
	protected function getRecordInactiveAccess() 
	{
		if (!Utils_RecordBrowserCommon::is_record_active($this->getValues()) && ($this->hasAction('edit', 'delete')))
			return false;
			
		return true;
	}
	
	public function hasAction($action, $_ = null) 
	{
		return in_array($this->getAction(), array_filter(func_get_args()));
	}
	
	public function isFullGrant() 
	{
		$ruleCrits = $this->getRuleCrits();
		
		return ($ruleCrits['restrict']!==true && $ruleCrits['grant']===true);
	}
	public function isFullDeny() 
	{
		$ruleCrits = $this->getRuleCrits();
		
		return $ruleCrits['restrict']===true;
	}
	
	public function getRuleCrits()
	{
		if ($this->ruleCrits) return $this->ruleCrits;

		return $this->ruleCrits = $this->getGuiRuleCrits() + $this->getCallbackRuleCrits();
	}
	
	public function getGuiRuleCrits() {
		static $cache;
		
		$key = $this->getTab() . '__' . Acl::get_user();
		
		if (!isset($cache[$key])) {
			$userClearance = Acl::get_clearance();
			
			$result = DB::Execute('SELECT
								*
							FROM ' .
					$this->getTab() . '_access AS acs
							WHERE
								NOT EXISTS (SELECT
												*
											FROM ' .
					$this->getTab() . '_access_clearance
											WHERE
												rule_id=acs.id AND '.
					implode(' AND ',array_fill(0, count($userClearance), 'clearance!=%s')).')', array_values($userClearance));
			
			$ruleCrits = array_fill_keys([
					'view',
					'edit',
					'delete',
					'add',
					'print',
					'export',
					'selection'
			], []);
			
			while ($row = $result->FetchRow()) {
				$ruleCrits[$row['action']][$row['id']] = $this->parseAccessCrits($row['crits']);
			}
			
			$cache[$key] = $ruleCrits;
		}
		
		$action = $this->hasAction('browse')? 'view': $this->getAction();
		
		return $cache[$key][$action];
	}
	
	public static function parseAccessCrits($str, $humanReadable = false) 
	{
		$result = Utils_RecordBrowserCommon::unserialize_crits($str);

		return is_object($result)? $result: Utils_RecordBrowser_Crits::create($result);
	}
	
	protected function getCallbackRuleCrits()
	{
		$ret = [
				'grant' => null,
				'restrict' => null
		];
		foreach ( $this->getCallbacks() as $callback ) {
			if (!is_callable($callback)) continue;
			
			$callbackCrits = call_user_func($callback, $this->getAction(), $this->getValues(), $this->getTab());
			
			if (is_bool($callbackCrits)) {
				$ret[$callbackCrits ? 'grant': 'restrict'] = true;
				break;
			}
			
			if ($callbackCrits === null) continue;
			
			// if callback return is crits or crits array use it by default in restrict mode for backward compatibility
			$crits = [
					'grant' => null,
					'restrict' => $callbackCrits
			];
			
			if (is_array($callbackCrits) && (isset($callbackCrits['grant']) || isset($callbackCrits['restrict']))) {
				// if restrict rules are not set make sure the restrict crits are clean				
				$callbackCrits['restrict'] = $callbackCrits['restrict']?? null;
				
				$crits = array_merge($crits, $callbackCrits);
			}
			
			$crits['grant'] = $crits['grant']?: null;
			
			foreach ( $crits as $mode => $c ) {
				$c = is_array($c) ? Utils_RecordBrowser_Crits::create($c): $c;
				
				if ($c instanceof Utils_RecordBrowser_Recordset_Query_Crits) {
					$ret[$mode] = ($ret[$mode] !== null) ? Utils_RecordBrowser_Crits::merge($ret[$mode], $c, $mode === 'grant'): $c;
				}
				elseif (is_bool($c)) {
					$ret[$mode] = $c;
				}
			}
		}
		
		return $ret;
	}
	
	public function getCallbacks($force = false)
	{
		static $cache = [];
		
		if (!$cache || $force) {
			$cache = [];
			$rows = DB::GetAll('SELECT * FROM recordbrowser_access_methods ORDER BY priority DESC');
			foreach ($rows as $row) {
				$cache[$row['tab']] = $cache[$row['tab']]?? [];

				$cache[$row['tab']][] = $row['func'];
			}
		}
		
		return $cache[$this->getTab()]?? [];
	}
	
	protected function getActiveGrantRules() 
	{
		if ($this->activeGrantRules) return $this->activeGrantRules;
		
		if ($this->isFullDeny()) return $this->activeGrantRules = false;
		
		if ($this->isFullGrant()) return $this->activeGrantRules = ['grant'];
		
		$ruleCrits = $this->getRuleCrits();
		
		if (! $this->validateValues($ruleCrits['restrict'])) {
			return $this->activeGrantRules = false;
		}
		
		$ret = [];
		foreach ( $ruleCrits as $ruleId => $crits ) {
			if ($ruleId === 'restrict') continue;
			
			if (! $crits instanceof Utils_RecordBrowser_Recordset_Query_Crits) continue;
			
			if (! $this->validateValues($crits)) continue;
			
			$ret[] = $ruleId;
		}
		
		return $this->activeGrantRules = $ret ?: false;
	}
	
	protected function validateValues($crits) 
	{
		if (! $this->getValues() || $this->hasAction('add') || ! $crits instanceof Utils_RecordBrowser_Recordset_Query_Crits ) return true;
		
		return $crits->validate($this->getRecordset(), $this->getValues());
	}
	
	protected function getAccessFields() 
	{
		if ($this->accessFields) return $this->accessFields;
		
		$access_rule_blocked_fields = [];
		foreach ( $this->getActiveGrantRules() as $rule_id ) {
			$access_rule_blocked_fields[$rule_id] = $this->getRuleBlockedFields($rule_id);
		}

		$blocked_fields = count($access_rule_blocked_fields) > 1 ? call_user_func_array('array_intersect', $access_rule_blocked_fields): reset($access_rule_blocked_fields);
		
		$full_field_access = array_fill_keys($this->getRecordset()->getRecordArrayKeys(), true);

		$blocked_field_access = $blocked_fields? array_fill_keys($blocked_fields, false): [];
		
		return $this->accessFields = array_merge($full_field_access, $blocked_field_access);
	}
	
	protected function getRuleBlockedFields($ruleId) 
	{
		static $cache;
		
		if (!is_numeric($ruleId)) return [];
		
		if (!isset($cache[$this->getTab()])) {
			$result = DB::Execute('SELECT * FROM ' . $this->getTab() . '_access_fields');
			
			$fields = [];
			while ($row = $result->FetchRow()) {
				$fields[$row['rule_id']][] = $row['block_field'];
			}
			
			$cache[$this->getTab()] = $fields;
		}
		
		return $cache[$this->getTab()][$ruleId]?? [];
	}
	
	protected function setRecordset($recordset)
	{
		$this->recordset = Utils_RecordBrowser_Recordset::create($recordset);
		
		return $this;
	}
	
	public function getRecordset()
	{
		return $this->recordset;
	}
	
	public function getTab()
	{
		return $this->getRecordset()->getTab();
	}
	
	protected function setAction($action)
	{
		$this->action = $action;
		
		return $this;
	}
	
	protected function setValues($values)
	{
		if ($values) {
			$this->values = is_object($values)? $values->toArray(): $values;
		}
		
		return $this;
	}
	
	protected function getAction()
	{
		return $this->action;
	}
	
	protected function getValues()
	{
		return $this->values;
	}
	
}
