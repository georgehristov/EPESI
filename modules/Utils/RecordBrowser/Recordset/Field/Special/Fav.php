<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_Special_Fav extends Utils_RecordBrowser_Recordset_Field {
	protected $id = 'fav';
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowser_Recordset_Field_Checkbox::defaultDisplayCallback($record, $nolink, $desc, $tab);
	}
	
	public function getName() {
		return _M('Favorite status');
	}
	
	public function getSqlId() {
		return false;
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
