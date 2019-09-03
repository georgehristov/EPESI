<?php
/**
 * Cron Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Cron extends Module {

	public function admin() {
		if ($this->is_back()) {
			$this->parent->reset();
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

        $gb = $this->init_module(Utils_GenericBrowser::module_name(),null,'cron');
        
        $gb->set_table_columns([
				[
						'name' => _M('Description'),
						'width' => 65
				],
				[
						'name' => _M('Last Run'),
						'width' => 20
				],
				[
						'name' => _M('Running'),
						'width' => 15
				],
				[
						'name' => _M('Log'),
						'width' => 15
				],
		]);
        
		$ret = DB::Execute('SELECT * FROM cron ORDER BY last DESC');
		while($row = $ret->FetchRow()) {
			$running = $row['running']? (bool) posix_getpgid($row['running']): false;
			
			$gb_row = $gb->get_new_row();
			
			$gb_row->add_data_array([
					$row['description']?: '???',
					$row['last']? Base_RegionalSettingsCommon::time2reg($row['last']): '---',
					$running?'<span style="color:red">'.__('Yes (pid: %d)', [$row['running']]).'</span>':'<span style="color:green">'.__('No').'</span>',
					$row['log']? '<a '.Utils_TooltipCommon::tooltip_leightbox_mode().' '.Utils_TooltipCommon::open_tag_attrs('<div style="resize:both;text-align:left;"><pre>' . htmlentities($row['log']) . '</pre></div>', false).'>' . __('View') . '</a>': ''
			]);

			if (!$running || !OS_UNIX) continue;
				
			$gb_row->add_action($this->create_confirm_callback_href(__('Do you really want to kill process for %s', [$row['description']?:'???']), [$this, 'kill_job'], $row['running']), 'delete', __('Kill process'));
		}

		$theme = $this->init_module(Base_Theme::module_name());
		
		$new_token_href = $this->create_confirm_callback_href(__('Are you sure?'), [$this, 'new_token']);
		$theme->assign('new_token_href', $new_token_href);
		$theme->assign('wiki_url', 'http://www.epesi.org/Cron');
		$theme->assign('cron_url', Base_CronCommon::get_cron_url());
		$theme->assign('history', $this->get_html_of_module($gb));
        $theme->display();
	}

	public function kill_job($pid)
    {
    	$message = __('Kill signal sent to process');
    	$type = 'normal';
    	
    	if (! posix_kill($pid, 9)) {
    		$message = __('Error occured when sending kill signal to process: %s', [posix_get_last_error()]);
    		$type = 'error';
    	}
    	
    	Base_StatusBarCommon::message($message, $type);
    }
	
    public function new_token()
    {
        Base_CronCommon::generate_token();
    }
	
	public function body() {
	}

	public function caption() {
		return __('Cron');
	}
}

?>
