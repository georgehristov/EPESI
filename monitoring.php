<?php
/**
 * This file provides cron functionality... Add it to your cron.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 */
define('CID',1);
define('SET_SESSION',false);
if (php_sapi_name() == 'cli') {
    define('EPESI_DIR', '/');
    if (isset($argv[1])) {
        define('DATA_DIR', $argv[1]);
    }
} elseif (!isset($_GET['token'])) {
    die('Missing token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
} else {
    defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);
    require_once('include/include_path.php');
    require_once('include/data_dir.php');
    if(!file_exists(DATA_DIR.'/cron_token.php'))
        die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
    require_once(DATA_DIR.'/cron_token.php');
    if(CRON_TOKEN!=$_GET['token'])
        die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
}
require_once('include.php');

ModuleManager::load_modules();
Base_AclCommon::set_sa_user();

function test_database() {
    $up = epesi_requires_update();
    if($up===null) {
        die('error: database');
    } elseif($up===true) {
        die('error: version');
    }
}


function test_session() {
    $tag = microtime(1);
    $session_id = session_id();
    DBSession::open('','');
    DBSession::read($session_id);
    $_SESSION['monitoring'] = $tag;
    $_SESSION['client']['monitoring'] = $tag;
    DBSession::write($session_id,'');
    $_SESSION = array();
    DBSession::read($session_id);
    if(!isset($_SESSION['monitoring']) || !isset($_SESSION['client']['monitoring']) || $_SESSION['monitoring'] != $tag || $_SESSION['client']['monitoring'] != $tag) {
        die('error: session');
    }
}

function test_data_directory() {
    $tag = (string)microtime(1);
    if(!is_writable(DATA_DIR)) {
        die('error: data directory now writable');
    }
    $test_file = DATA_DIR.'/monitoring_test_file.txt';
    file_put_contents($test_file, $tag);
    if(file_get_contents($test_file) != $tag) {
        die('error: data directory write/read error');
    }
    unlink($test_file);
}

$t = microtime(1);
if(isset($_GET['type'])) {
    if(in_array($_GET['type'],array('database','session','data_directory'))) {
        call_user_func('test_'.$_GET['type']);
    } else {
        die('Invalid test type: '.$_GET['type']);
    }
} else {
    test_database();
    test_session();
    test_data_directory();
}

die(round((microtime(1)-$t)*1000));