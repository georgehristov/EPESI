<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-overview
 */

if(!isset($_REQUEST['cid']) || !isset($_REQUEST['key'])) die('Invalid usage - missing param');
$cid = $_REQUEST['cid'];

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../../include.php');

ModuleManager::load_modules();

set_time_limit(0);

$xls = Utils_Overview_TableCommon::get_xls($_REQUEST['key']);

if (empty($xls) || !$xls instanceof PHPExcel) {
	print(__('Cound not generate export file.'));
	exit();
}

$filename = 'overview_export';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;');//filename=\"'.$filename.'.xlsx\"
header('Pragma: no-cache');
header('Expires: 0');

$xlsWriter = PHPExcel_IOFactory::createWriter($xls, 'Excel2007');

ob_end_clean();

$xlsWriter->save('php://output');
readfile('php://output');

?>
