<?php

/**
 * This file provides cron functionality ... Add it to your cron.
 * 
 * @author Georgi Hristov <ghristov@xsystems.io>
 * @copyright Copyright &copy; 2019, X Systems Ltd
 * @license MIT
 * @version 2.0
 * @package epesi-base
 * 
 * First you need to setup your OS to call this file every minute
 * Add following to your crontab
 * 
 * 		* * * * * www-data /usr/bin/php /var/www/<path-to-epesi>/cron.php
 * 
 * Define a method 'cron' in any of the installed modules
 * The return of the cron method should be an array of cron settings to run
 * 
 * Format (default since cron v2.0):
 * [
 * 		[
 * 			'key' => 'job_key',
 *			'description' => 'A description of the cron job',
 *			'schedule' => '* * * * * *', // crontab valid schedule to run or numeric (run every N minutes)
 *			'func' => [module, method], // callback method to call 
 *			'args' => [] // arguments to supply to the callback method
 * 		]
 * ]
 * 
 * Format (backward compatible):
 * [
 * 		'method' => 3, // call 'method' as callback (the method will be searched in the module where cron method is declared) every 3 minutes
 * 		'module::method' => 5 // directlly calls module::method as callback every 5 minutes
 * ]
 */

define('CID',false);
define('SET_SESSION',false);

if (php_sapi_name() == 'cli') {
	define('EPESI_DIR', '/');
	if (isset($argv[1])) {
		define('DATA_DIR', $argv[1]);
	}
}
elseif (! isset($_GET['token'])) {
	die('Missing token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
}
else {
	defined("_VALID_ACCESS") || define("_VALID_ACCESS", true);
	require_once ('include/include_path.php');
	require_once ('include/data_dir.php');
	if (! file_exists(DATA_DIR . '/cron_token.php')) {
		die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
	}
	require_once (DATA_DIR . '/cron_token.php');
	if (CRON_TOKEN != $_GET['token']) {
		die('Invalid token in URL - please go to Administrator Panel->Cron and copy valid cron URL.');
	}
}

require_once('include.php');

if (file_exists(DATA_DIR . '/maintenance_mode.php')) die('Cron does not run in maintenance mode.');

set_time_limit(6 * 60 * 60);
ini_set('memory_limit', '512M');

ModuleManager::load_modules();

Acl::set_sa_user();

class EpesiCron {
	/**
	 * @var integer interval in seconds to check if all jobs completed
	 */
	protected static $monitorInterval = 5;
	/**
	 * @var \Cron\Report\CronReport
	 */
	protected $report;
	/**
	 * @var \Cron\Job\JobInterface[]
	 */
	protected $jobs;
	
	public static function create() {
		return new static();
	}

	/**
	 * Run all crons defined in the modules if they are due
	 */
	public function run() {
		$resolver = new \Cron\Resolver\ArrayResolver();
		$resolver->addJobs($this->getJobs());
		
		$cron = new \Cron\Cron();
		$cron->setExecutor(new \Cron\Executor\Executor());
		$cron->setResolver($resolver);
		
		$this->report = $cron->run();
		
		return $this;
	}
		
	/**
	 * Monitor the execution of the jobs and log the result on job completion
	 */
	public function monitor() {
		if (! $this->getReport()) return;
		
		$jobs = $this->getJobs();
		
		while ($jobs) {
			foreach ($jobs as $i => $job) {
				if (! $job->isRunning()) {
					unset($jobs[$i]);
				}
				
				sleep(self::$monitorInterval);
			}
		}
		
		return $this;
	}
	
	/**
	 * @param \Cron\Job\JobInterface $job
	 * @return \Cron\Report\ReportInterface|NULL
	 */
	public function getReport($job = null) {
		return $job? $this->report->getReport($job): $this->report;
	}
	
	/**
	 * @param \Cron\Job\JobInterface $job
	 */
	public function log() {
		foreach ($this->getReport()->getReports() as $report) {
			$report->log();
		}
	}

	/**
	 * @return \Cron\Job\JobInterface[]
	 */
	protected function getJobs() {
		if (isset($this->jobs)) return $this->jobs;
		
		$ret = ModuleManager::call_common_methods('cron');
		
		$this->jobs = [];
		foreach($ret as $moduleName => $obj) {
			if (!$obj) continue;
			
			foreach ( is_array($obj) ? $obj: [] as $key => $opts ) {
				$func = strpos($key, '::')? $key: $moduleName . 'Common::' . $key;
				
				$opts = array_merge([
						'description' => $func,
						'schedule' => 0,
						'func' => $func,
						'args' => []
				], is_array($opts)? $opts: [
						'schedule' => $opts,
				]);

				$this->jobs[] = EpesiJob::create()->setOptions($opts);
			}
		}
		
		return $this->jobs;
	}
}


class EpesiJobReport extends \Cron\Report\JobReport {
	protected $log;
	
	/**
	 * @param \Cron\Report\ReportInterface $report
	 */
	public function log() {
		$this->save();
		
		epesi_log(print_r([
				'Cron' => $this->getJob()->getDescription(),
				'Callback' => array_filter($this->getJob()->getCallback()?: []),
				'Started' => date('Y-m-d H:m:i', $this->getStartTime()),
				'Ended' => date('Y-m-d H:m:i', $this->getEndTime()),
				'Successful' => $this->isSuccessful()? '<Yes>': '<No>',
				'Output' => $this->getOutput()?: '<None>',
				'Error' => $this->getError()?: '<None>',
		], true), 'cron.log');
	}
	
	public function save() {
		$job = $this->getJob();
		
		$pid = $job->isRunning()? $job->getProcess()->getPid(): 0;
		
		if ($this->read()) {
			DB::Execute('UPDATE cron SET last=%d, running=%d WHERE token=%s', [$this->getStartTime(), $pid, $job->getToken()]);
		}
		else {
			DB::Execute('INSERT INTO cron (token, last, running, description) VALUES (%s,%d,%d,%s)', [$job->getToken(), $this->getStartTime(), $pid, $job->getDescription()]);
		}
	}
	
	public function read($force = false) {
		if (!$force && $this->log) return $this->log;
		
		return $this->log = DB::GetRow('SELECT * FROM cron WHERE token=%s', [$this->getJob()->getToken()]);
	}
}

class EpesiJob extends \Cron\Job\PhpJob {
	protected $token;
	protected $log;
	protected $description;
	protected $callback;
	
	public static function create() {
		return new static();
	}
	
	public function run(\Cron\Report\JobReport $report)
	{
		parent::run($report);
		
		$report->save();
	}
	
	/**
	 * @return EpesiJobReport
	 */
	public function createReport()
	{
		return new EpesiJobReport($this);
	}
	
	public function setOptions($options) {
		$this->setDescription($options['description']);		
		$this->setCallback($options['func'], $options['args']);
		
		$schedule = is_numeric($options['schedule'])? "*/$options[schedule] * * * * *": $options['schedule'];
		
		$this->setSchedule(new \Cron\Schedule\CrontabSchedule($schedule));
		
		return $this;
	}
	
	public function setCallback($func, $args = []) {
		$args = is_array($args)? $args: [$args];
		
		$this->callback = compact('func', 'args');
		
		$func = var_export($func, true);
		$args = var_export($args, true);
		
		$this->setToken(md5(serialize([$func, $args])));
		
		$this->setScript("<?php
			define('CID', false);
			define('SET_SESSION',false);
				
			require_once('include.php');
				
			set_time_limit(0);
			ini_set('memory_limit', '512M');
				
			ModuleManager::load_modules();
				
			Acl::set_sa_user();
				
 			call_user_func_array($func, $args); ?>");
		
		return $this;
	}
	
	public function getCallback() {
		return $this->callback;
	}
	
	public function setToken($token) {
		$this->token = $token;

		if ($pid = $this->readPid()) {
			$this->setPid($pid);
		}
		
		return $this;
	}
	
	public function getToken() {
		return $this->token;
	}
	
	public function readPid() {
		return DB::GetOne('SELECT running FROM cron WHERE token=%s', [$this->getToken()])?: 0;
	}

	public function setDescription($description) {
		$this->description = $description;
		
		return $this;
	}
	
	public function getDescription() {
		return $this->description;
	}
}

EpesiCron::create()->run()->monitor()->log();

