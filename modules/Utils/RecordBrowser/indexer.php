<?php
if (!isset($_GET['cid']) || !isset($_GET['token']))
	die('Invalid request');

define('CID',$_GET['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
if(!isset($_SESSION['rb_indexer_token']) || $_SESSION['rb_indexer_token']!=$_GET['token'])
    die('Invalid token');

$total = 0;
if(@file_get_contents(DATA_DIR.'/Utils_RecordBrowser/last')<time()-120) {
    ModuleManager::load_modules();
    Base_AclCommon::set_sa_user();

    Utils_RecordBrowserCommon::indexer(30,$total);
    if($total==0) file_put_contents(DATA_DIR.'/Utils_RecordBrowser/last',time(),LOCK_EX);
}

$timeout = $total? 10000: 180000;

print ("setTimeout('Utils_RecordBrowser.index(\"$_GET[token]\")', $timeout);");

?>
