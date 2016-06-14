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

$tcpdf = Utils_Overview_TableCommon::get_pdf($_REQUEST['key']);
	
$header_data = $tcpdf->getHeaderData();
$filename = isset($header_data['title'])? $header_data['title']: 'overview';
	
$buffer = Libs_TCPDFCommon::output($tcpdf);

header('Content-Type: application/pdf');
header('Content-Length: '.strlen($buffer));
header('Content-disposition: inline; filename="'.$filename.'.pdf"');

print($buffer);

?>
