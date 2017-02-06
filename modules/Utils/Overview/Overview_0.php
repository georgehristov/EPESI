<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-overview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Overview extends Module {
	private $id = '';
	private $filters = array();
	private $filters_template = null;
	private $filters_form = null;
	private $submit_form_onchange = true;
	private $onchange = '';
	private $mode_form = null;
	private $hide_filters = true;
	private $caption = '';
	private $hide_caption = false;
	private $display_modes = array();
	private $display_mode_opts = array();
	private $tabs = array();
	private $table_opts = array();
	private $icon = '';
	private $filter_values = array();
	private $applet = false;
	private $do_not_store = array();
	private $table = null;
	
	public function construct($id) {
		if (empty($id))
			trigger_error('Cannot construct overview, overview ID not set:' . $this->get_path() . '.', E_USER_ERROR);
		
		$this->id = $id;
	}
	
	public function set_filter_values($filter_values) {
		//FIXME: calling this method should not be done from the parent module, body parameter should be used instead. This needs to be done more intuitive
		$this->filter_values = $filter_values;
		
		$stored_vals = array_diff_key($this->filter_values, array_flip($this->do_not_store));		
		$stored_vals = array_merge($stored_vals, array('__mode__'=>$this->get_mode(), '__applet__'=>$this->applet));

		return Module::static_set_module_variable($this->get_type(), $this->get_filter_values_var_name(), $stored_vals);
	}
	
	public function get_filter_values($defaults = array()) {
		if (!empty($this->filter_values)) 
			return $this->filter_values;

		$stored_vals = Module::static_get_module_variable($this->get_type(), $this->get_filter_values_var_name(), array());
		$stored_vals = array_diff_key($stored_vals, array_flip($this->do_not_store));

		return array_merge($defaults, $stored_vals);
	}
	
	public function get_filter_values_var_name() {
		return implode('__', array($this->id, $this->get_mode(), 'filter_values'));
	}
	
	public function body($filter_values = array(), $pdf = false) {
		if (!empty($this->display_modes))
			$this->set_display_mode_opt_values();

		if (!empty($filter_values))
			$this->set_filter_values($filter_values);

		if (!$pdf) {			
			if($this->is_back()) 
				Base_BoxCommon::pop_main();
	
			if ($this->get_back_button())
				Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
			
			Base_HelpCommon::screen_name($this->id);
			
			Base_ThemeCommon::load_css($this->get_type(), 'overview');
			
			$res = $this->init_mode_form();

			ob_start();
			$this->display_filters();
			$settings = ob_get_clean();
		}
		
		$theme = Base_ThemeCommon::init_smarty();
		
		$caption = $this->get_caption($pdf);

		if (isset($this->mode_form))
			$this->mode_form->assign_theme('form', $theme);
		
		if (!empty($caption))
			$theme->assign('caption', _V($caption));
		$theme->assign('icon', $this->icon);
		$theme->assign('hide_header', $this->applet || (empty($caption) && $this->hide_filters) || $this->hide_caption || $pdf);
		$theme->assign('hide_form', $this->applet || $this->hide_filters || $pdf);
		$theme->assign('settings', $settings);
		$theme->assign('contents', $this->get_overview_tabs($pdf));
		
		Base_ThemeCommon::display_smarty($theme, $this->get_type(), 'overview');
	}
	
	public function get_overview_tabs($pdf) {
		ob_start();
	
		if (!empty($this->tabs)) {
			foreach($this->tabs as $id=>&$t) {
				$t['func'] = ($t['func'] == '__DEFAULT__')? array($this, 'display_overview_table'): $t['func'];
				$t['args'] = array_merge(array($id, $pdf, $this->get_display_vars()), !empty($t['args'])? $t['args']: array());
			}
			unset($t);
				
			if (count($this->tabs) > 1) {
				$tb = $this->init_module(Utils_TabbedBrowser::module_name());
					
				foreach($this->tabs as $id=>$t)
					$tb->set_tab($t['label'], $t['func'], $t['args']);
					
				$tb->body();
				$tb->tag();		
			}
			else{
				$t = reset($this->tabs);
				if (is_callable($t['func']))
					call_user_func_array($t['func'], $t['args']);
			}
		}
		else 
			$this->table = $this->display_overview_table($this->id, $pdf, $this->get_display_vars(), $this->table_opts);
	
		return ob_get_clean();
	}
	
	public function display_overview_table($id, $pdf, $display_vars, $table_opts) {	
		$table_opts = array_merge($display_vars, $table_opts);
		$table_opts['id'] = $id;
		
		return $this->pack_module(Utils_Overview_Table::module_name(), array($table_opts, $pdf));
	}
		
	public function set_tabs($arg) {
		$this->tabs = $arg;
	}
	
	public function set_filters($filters = array(), $template = null, $hide = false) {
		$this->filters = $filters + $this->filters;
		
		if (!is_null($template))
			$this->filters_template = $template;

		$this->set_hide_filters($hide);
		
		return $this;
	}
	
	public function set_filters_template($template = null) {
		$this->filters_template = $template;

		return $this;
	}
	
	public function get_filters() {
		return $this->filters;
	}
	
	public function set_hide_filters($value) {
		$this->hide_filters = $value;
	
		return $this;
	}
	
	public function set_hide_caption($value) {
		$this->hide_caption = $value;
	
		return $this;
	}
	
	public function get_display_vars() {
		return array(
					'caption'=>$this->caption,
					'icon'=>$this->icon,
					'mode'=>$this->get_mode(),
					'filters'=>$this->filters,
					'applet'=>$this->applet,
					'filter_values'=>$this->filter_values
			);			
	}
		
	public function display_filters() {
		$form_visible = false;
		
		if (empty($this->filters)) return $form_visible;
			
		$this->filters_form = $this->init_module(Libs_QuickForm::module_name());			

		$chained = array();
		$checkboxes = array();
		foreach ($this->filters as $k=>$v) {
			if ($v['type'] != 'hidden') $form_visible = true;

			$defaults[$k] = isset($v['default']) ? $v['default'] : null;
			if (isset($v['store']) && !$v['store'])
				$this->do_not_store[] = $k;				

			if (!isset($v['type'])) continue;
			$v['title'] = isset($v['title']) ? $v['title'] : '';
			$v['select_options'] = isset($v['select_options']) ? $v['select_options'] : null;
			$v['attr'] = isset($v['attr']) ? $v['attr'] : array();

			//create chained selects
			if ($v['type'] == 'select' && isset($v['chained']['fields']) && isset($v['chained']['url'])) {
				$chained_prev_ids = is_array($v['chained']['fields'])? $v['chained']['fields']: array($v['chained']['fields']);
				$chained_params = isset($v['chained']['chained_params'])? $v['chained']['chained_params']: array();
			
				$v['select_options'] = is_callable($v['chained']['select_options_callback'])? call_user_func_array($v['chained']['select_options_callback'], array_intersect_key($this->filters_form->validate()?$this->filters_form->exportValues():$this->get_filter_values($defaults), array_flip($chained_prev_ids))):array();

				$chained[$k] = array($k, $chained_prev_ids, $v['chained']['url'], $chained_params, $defaults[$k]);
			}
			
			$attr = array_merge(array('id'=>$k), $v['attr']);
			if ($this->submit_form_onchange && (!isset($v['disable_onchange']) || !$v['disable_onchange'])) {
				$attr['onchange'] = isset($attr['onchange'])? $attr['onchange'] . ';': '';
				$attr['onchange'] .= $this->onchange?: $this->filters_form->get_submit_form_js();
			}
				
			if (isset($v['style'])) $attr['style'] = $v['style'];

			switch (true) {
				case $v['type'] == 'hidden':
				case $v['type'] == 'button':
				case $v['type'] == 'datepicker':
					$this->filters_form->addElement($v['type'], $k, _V($v['title']), $attr);
				break;
				
				default:
					$this->filters_form->addElement($v['type'], $k, _V($v['title']), $v['select_options'], $attr);
					if ($v['type'] == 'checkbox') $checkboxes[$k] = 0;
				break;
			}			
			if (isset($v['required']) && $v['required'])
				$this->filters_form->addRule($k, __('Field required'), 'required');
		}	
		
		$filter_values = ($this->filters_form->validate() && $this->filters_form->exportValue('submited'))? $this->filters_form->exportValues(): $this->get_filter_values($defaults);

		$filter_values = array_merge($checkboxes, $filter_values);

		$this->set_filter_values($filter_values);

		foreach ($chained as $k=>$params)			
			call_user_func_array(array('Utils_ChainedSelectCommon', 'create'), array_merge($params, array($filter_values[$k])));		

		$this->filters_form->setDefaults($filter_values);

		if (empty($this->filters_template))
			$this->filters_form->display_as_row();
		else {
			$t = Base_ThemeCommon::init_smarty();
			$this->filters_form->add_error_closing_buttons();
			$this->filters_form->assign_theme('form', $t);
			Base_ThemeCommon::load_css($this->get_parent_type(), $this->filters_template, false);
			Base_ThemeCommon::display_smarty($t, $this->get_parent_type(), $this->filters_template);
		}

		$this->hide_filters |= !$form_visible; 
	}
	
	public function get_submit_form_href($submited=true, $indicator=null) {
		if (!isset($this->filters_form) || !($this->filters_form instanceof Libs_QuickForm)) return '';
		
		return 'href="javascript:void(0)" onclick="' . $this->filters_form->get_submit_form_js($submited, $indicator) . '"';
	}
	
	public function is_filters_form_submitted() {
		if (!isset($this->filters_form) || !($this->filters_form instanceof Libs_QuickForm)) return false;
		
		return $this->filters_form->validate() && $this->filters_form->exportValue('submited');
	}

	public function set_properties_from_array($settings) {
		if (!is_array($settings)) return;
		
		foreach ($settings as $key=>$value)
			if (is_callable(array($this, 'set_' . $key)))
				call_user_func(array($this, 'set_' . $key), $value);
			elseif (is_callable(array('Utils_Overview_Table', 'set_' . $key)))
				$this->table_opts[$key] = $value;
	}

	public function set_caption($caption, $hidden = false) {
		$this->caption = $caption;		
		$this->hide_caption = $hidden;

		return $this;
	}
	
	public function get_caption($pdf = false) {
		return is_callable($this->caption) ? call_user_func($this->caption, $this->filters, $this->filter_values, $pdf) : $this->caption;
	}
	
	public function set_applet($value=true) {
		$this->applet = $value;
		
		return $this;
	}
	
	public function set_back_button($value=true) {
		$this->set_module_variable('back_button', $value);
	
		return $this;
	}
	
	public function get_back_button($default=false) {
		return $this->get_module_variable('back_button', $default);
	}

	public function set_icon($icon) {
		$this->icon = $icon;
		
		return $this;
	}
	
	public function set_submit_form_onchange($value = true){
		$this->submit_form_onchange = $value;
		
		return $this;
	}
	
	public function set_onchange($value = ''){
		$this->onchange = $value;
	
		return $this;
	}
	
	public function set_display_modes($display_modes, $default_mode = ''){
		if (!is_array($display_modes)) return;
		
		$this->display_modes = $display_modes;

		if (!array_key_exists($default_mode, $display_modes)) {
			$display_mode_keys = array_keys($display_modes);
			$default_mode = array_shift($display_mode_keys);
		}
		$mode = $this->get_mode($default_mode);

		$this->switch_mode($mode);
	}
	
	public function switch_mode($mode){		
		$this->set_module_variable('__mode__', $mode);
	}
	
	public function set_display_mode_opts($values){
		$this->display_mode_opts = $values;
		
		return $this;
	}
	
	public function set_display_mode_opt_values(){
		$display_mode = $this->get_mode();
		
		if (is_callable($this->display_mode_opts))
			$display_mode_opts = call_user_func($this->display_mode_opts, $display_mode);
		elseif (isset($this->display_mode_opts[$display_mode])) {
			$display_mode_opts = $this->display_mode_opts[$display_mode];
		}
		else return;
		
		$this->set_properties_from_array($display_mode_opts);
	}
	
	public function get_mode($default_mode = null){
		return $this->get_module_variable('__mode__', $default_mode);
	}	

	public function init_mode_form() {
		if (empty($this->display_modes)) return null;
	
		$this->mode_form =  $this->init_module(Libs_QuickForm::module_name());
		$this->mode_form->addElement('select', '__mode__', null, $this->display_modes, array('onchange'=>$this->mode_form->get_submit_form_js()));
		$this->mode_form->setDefaults(array('__mode__'=>$this->get_mode()));
	
		if ($this->mode_form->validate()) {
			$vals = $this->mode_form->exportValues();
			if (isset($vals['__mode__']) && isset($this->display_modes[$vals['__mode__']])) {
				$this->switch_mode($vals['__mode__']);
				location(array());
			}
		}
	
		return true;
	}
	
	public function & __call($method, array $args=array()) {
		$ret = false;
		
		if (is_callable(array('parent', '__call')))
			$ret = parent::__call($method, $args);
		
		if (method_exists('Utils_Overview_Table', $method)) {
			$key = str_ireplace('set_', '', $method);
			$this->table_opts[$key] = reset($args);
		}	

		return $ret;
	}
	
	public function get_table_id() {
		if (isset($this->table) && $this->table instanceof Utils_Overview_Table) {
			return $this->table->get_table_id();
		}
		return '';
	}
}
?>