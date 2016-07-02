<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-span
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Overview_SpanInstall extends ModuleInstall {
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
			array('name'=>'Base/Theme','version'=>0));
	}
	
	public static function info() {
		$html="Displays nice bars";
		return array(
			'Description'=>$html,
			'Author'=>'<a href="mailto:ghristov@gmx.de">Georgi Hristov</a>',
			'License'=>'MIT');
	}
	
	public function simple_setup() {
		return array('package' => __('Overview'), 'version'=>self::version);
	}	
}

?>