<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Fav extends Utils_RecordBrowser_Recordset_Field {
	public static function desc($tab = null, $name = null) {
		return [
				'id' => 'fav',
				'field' => _M('Fav'),
				'caption' => _M('Favourite'),
				'type' => 'fav',
				'active' => true,
				'visible' => false,
				'export' => true,
				'processing_order' => -600,
		];
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowser_Recordset_Field_Checkbox::defaultDisplayCallback($record, $nolink, $desc, $tab);
	}
	
	public function getSqlId() {
		return false;
	}
	
	public function getArrayId() {
		return ':' . $this->getId();
	}
	
	public function getSqlOrder($direction) {
		return ' (SELECT 
					COUNT(*) 
				FROM '.
					$this->getTab().'_favorite 
				WHERE '.
					$this->getTab().'_id='.$this->getRecordset()->getDataTableAlias().'.id AND 
					user_id=' . Acl::get_user() . ') ' . $direction;
	}
	
	public function getQuery(Utils_RecordBrowser_Recordset_Query_Crits_Basic $crit) {
		$operator = $crit->getSQLOperator();
		
		$sql_null = stripos($operator, '!') === false? 'NOT': '';
		
		$sql = "fav.fav_id IS $sql_null NULL";

		return $this->getRecordset()->createQuery("EXISTS (SELECT 1 FROM {$this->getTab()}_favorite AS fav WHERE fav.{$this->getTab()}_id={$this->getRecordset()->getDataTableAlias()}.id AND fav.user_id=%d AND $sql)", [Acl::get_user()]);
	}
	
	public function processGet($values, $options = []) {
		return [];
	}
	
	public function queryBuilderFilters($opts = []) {
		return [
				[
						'id' => ':Fav',
						'field' => ':Fav',
						'label' => __('Favorite'),
						'type' => 'boolean',
						'input' => 'select',
						'values' => ['1' => __('Yes'), '0' => __('No')]
				]
		];
	}
}
