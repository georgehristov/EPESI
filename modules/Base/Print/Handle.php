<?php

class Base_Print_Handle
{
    private static $epesi_loaded = false;

    public static function create()
    {
    	return new static();
    }
    
    public function handle_request()
    {
        $this->load_epesi();
        
        $this->create_printer()->print();
    }

    protected function load_epesi()
    {
        if (self::$epesi_loaded) return;

        define('CID', $_REQUEST['cid']?? false);
        define('READ_ONLY_SESSION', true);
        require_once '../../../include.php';

        ModuleManager::load_modules();
        self::$epesi_loaded = true;

        if (!Base_AclCommon::is_user()) {
            throw new ErrorException('Not logged in');
        }
    }

    protected function create_printer()
    {
    	$printer = Base_Print_Printer::create(self::param('data', true), self::param('printer', true));
        
        if (!$printer instanceof Base_Print_Printer) {
        	throw new ErrorException('Printer must be descendant of class Base_Print_Printer');
        }
        
        return $printer->set_template(self::param('tpl'));
    }
    
    protected static function param($name, $required = false)
    {
        $val = null;
        if (!isset($_REQUEST[$name])) {
            if ($required) {
                throw new Exception('Invalid usage - missing param');
            }
        } else {
            $val = $_REQUEST[$name];
        }
        return $val;
    }
}

if (!defined('_VALID_ACCESS')) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    try {
    	Base_Print_Handle::create()->handle_request();
    } catch (Exception $ex) {
        die($ex->getMessage());
    }
}