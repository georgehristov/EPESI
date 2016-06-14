<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-overview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Overview_TableInstall extends ModuleInstall {
	const version = '1.0';

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());		

		DB::CreateTable('overview_table_properties',
				'id C(128),'.
				'printer C(255) DEFAULT \'\'',
				array('constraints'=>', UNIQUE(id)'));
		
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());		

		DB::DropTable('overview_table_properties');		
		
		return true;
	}
	
	public function version() {
		return array(self::version);
	}
	
	public function requires($v) {
		return array(
			array('name'=>Utils_GenericBrowserInstall::module_name(), 'version'=>0),
			array('name'=>Libs_PHPExcelInstall::module_name(), 'version'=>0),
			array('name'=>Libs_TCPDFInstall::module_name(), 'version'=>0),
			array('name'=>Base_PrintInstall::module_name(), 'version'=>0),
			array('name'=>Base_HelpInstall::module_name(), 'version'=>0),				
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
		);
	}
		
	public function simple_setup() {
		return __('Overview');
	}	
}

?>