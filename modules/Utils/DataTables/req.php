<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-datatables
 */

if(!isset($_GET['callback_id']) || !isset($_GET['cid']))
	die(json_encode(array('error'=>'Invalid request'.print_r($_GET,true))));

define('JS_OUTPUT',1);
define('CID',$_GET['cid']); 
define('READ_ONLY_SESSION',1); 
require_once('../../../include.php');
ModuleManager::load_modules();

$callback_info = Utils_DataTablesCommon::get_callback_info($_GET['callback_id']);
if (!$callback_info) 
	die (json_encode(array('error'=>__('Session expired. Please reload page!')))); 

$info = Utils_DataTablesCommon::call_data_callback($callback_info, $_REQUEST);
$json = Utils_DataTablesCommon::format_response($_REQUEST['draw'], $info);

print(json_encode($json));
?>