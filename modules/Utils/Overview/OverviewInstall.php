<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-overview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_OverviewInstall extends ModuleInstall {
	const version = '1.0';

	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());		
		
		return true;
	}

	public function uninstall() {
		Base_ThemeCommon::uninstall_default_theme($this->get_type());
		
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
			array('name'=>Utils_ChainedSelectInstall::module_name(), 'version'=>0),
			array('name'=>Base_PrintInstall::module_name(), 'version'=>0),
			array('name'=>Base_HelpInstall::module_name(), 'version'=>0),				
			array('name'=>Base_ThemeInstall::module_name(),'version'=>0),
			array('name'=>Utils_TabbedBrowserInstall::module_name(),'version'=>0),
		);
	}
	
	public static function info() {
		$html="Displays overview";
		return array(
			'Description'=>$html,
			'Author'=>'<a href="mailto:ghristov@gmx.de">Georgi Hristov</a>',
			'License'=>'MIT');
	}
	
	public function simple_setup() {
		return __('Overview');
	}	
}

?>