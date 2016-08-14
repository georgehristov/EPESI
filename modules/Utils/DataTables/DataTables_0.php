<?php
use rOpenDev\DataTablesPHP\DataTable;

/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-datatables
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_DataTables extends Module {
	/**
	 * @var DataTable
	 */
	protected $datatable = null;
	protected $id = null;
	protected $data_callback_info = null;
	
	public function construct($id) {
		$this->id = $id;
		$this->datatable = Utils_DataTablesCommon::init($id);		
	}
	
	public function body($info, $params=array()){
		if(!$this->datatable instanceof DataTable)
			trigger_error('Supplied parameter not of Utils_DataTables_PHPModule class',E_USER_ERROR);

		Base_ThemeCommon::load_css($this->get_type());			
		Utils_DataTablesCommon::load_library_files();
		
		$this->setup_table($info, $params);		
		$this->set_static_params();		
		
		eval_js('(function($){' . $this->datatable->getJavascript() . '})(jQuery);');		
		print ($this->datatable->getHtml());		
	}

	public function set_static_params() {
		$this->set_language();
		$this->datatable->setJsInitParam('destroy', true);
		$this->datatable->setJsInitParam('responsive', true);
	}
	
	public function set_language() {
		$language_code = Base_LangCommon::get_lang_code();
		$language_names = Base_LangCommon::get_all_languages();
		$language = $language_names[$language_code];
		
		$filename = 'http://cdn.datatables.net/plug-ins/1.10.12/i18n/' . $language . '.json';

		$this->datatable->setJsInitParam('language', array('url'=>$filename));
	}
	
	public function setup_table($info, $params = array()) {
		if (isset($info['func']) && is_callable($info['func'])) {
			$this->data_callback_info = $info;
			$this->datatable->setServerSide($this->get_default_ajax());
			$info = Utils_DataTablesCommon::call_data_callback($info);
		}
			
		if (!isset($info['columns']))
			trigger_error('Columns not set',E_USER_ERROR);
		
		$this->datatable->setColumns($info['columns']);
		
		if (isset($info['data']))
			$this->datatable->setData(array_values($info['data']));
		
		$this->datatable->setJsInitParams($params);
	}
	
	public function get_default_ajax() {
		if (!$this->data_callback_info) return false;

		$callback_id = Utils_DataTablesCommon::set_callback_info(md5($this->get_instance_id()), $this->data_callback_info);
		
		return array(
        	'url' => $this->get_module_dir() . 'req.php?callback_id='.$callback_id.'&cid='.CID,
            'type' => 'POST',
        );
	}
	
	public function & get_datatable() {
		return $this->datatable;
	}
	
}
?>