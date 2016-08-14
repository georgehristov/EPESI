<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-datatables
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_DataTablesInstall extends ModuleInstall {
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
			
		);
	}
	
	public static function info() {
		$html="Displays DataTables";
		return array(
			'Description'=>$html,
			'Author'=>'<a href="mailto:ghristov@gmx.de">Georgi Hristov</a>',
			'License'=>'MIT');
	}
}

?>