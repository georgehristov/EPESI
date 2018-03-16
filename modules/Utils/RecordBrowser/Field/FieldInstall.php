<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Field
 */

defined("_VALID_ACCESS") || die();

class Utils_RecordBrowser_FieldInstall extends ModuleInstall {
	public function install() {
		Base_ThemeCommon::install_default_theme($this->get_type());
		
		return true;
	}
	
	public function uninstall() {
        Base_ThemeCommon::uninstall_default_theme($this->get_type());
        
		return true;
	}
	
	public function requires($v) {
		return array(
			array('name'=>Utils_RecordBrowserInstall::module_name(), 'version'=>0),
		);
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'<a href="mailto:ghristov@gmx.de">Georgi Hristov</a>',
			'License'=>'MIT');
	}
	
	public function simple_setup() {
		return __('EPESI Core');
	}
}

?>
