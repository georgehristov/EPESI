<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-overview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Overview_Table extends Module {
	private $cols = array();
	private $rows = array();
	private $row_callback = array();
	private $row_action_callbacks = array();
	private $row_attrs = array();
	private $categories = array();
	private $print_settings = false;
	private $export_settings = false;
	private $print_key = '';
	private $paging_limit = 50;
	private $search;
	private $order;
	private $default_order = array();
	private $expandable = true;	
	private $scroll_height = false;
	private $resizable_cols = true;
	private $areas = array('header'=>'', 'footer'=>'', 'right'=>'', 'left'=>'');
	private $table_prefix = '';
	private $table_postfix = '';
	private $gb = null;
	private $custom_label = '';
	private $custom_label_args = '';
	private $cols_set = false;
	private $data_table = false;
	private $layout_opts = array();
	
	//display properties
	private $id = '';
	private $mode = '';
	private $applet = false;
	private $icon = array();
	private $filter_values = array();
	private $caption = array();
	private $filters = array();
	
	public function body($opts = array(), $pdf = false) {
		if (!empty($opts))
			$this->set_properties_from_array($opts);

		if (empty($this->cols) && empty($this->rows)) trigger_error('Overview columns or rows not set:' . $this->get_path() . '.', E_USER_ERROR);

		$this->gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'overview_table_' . $this->id);
		
		$cols = $this->get_cols($pdf);

		$this->gb->set_table_columns(array_values($cols));
		
		$this->gb->set_prefix($this->get_prefix($pdf));
		
		$this->gb->set_postfix($this->get_postfix($pdf));
		
		$this->gb->set_custom_label($this->custom_label, $this->custom_label_args);
		
		$this->set_gb_rows($pdf);

		if ($pdf) {
			$this->gb->set_expandable(false);
			$this->gb->set_resizable_columns(false);
			$this->gb->absolute_width(true);
			$this->gb->set_inline_display();

			print ($this->get_area('header'));
				
			$this->gb->body(Base_ThemeCommon::get_template_filename($this->get_type(), 'pdf'));
				
			print ($this->get_area('footer'));
			
			return;
		}		

		$this->add_print_button();
		$this->add_export_button();
	
		$this->gb->set_expandable($this->expandable && !$this->applet);
		$this->gb->set_default_order($this->default_order);
		$this->gb->set_resizable_columns($this->resizable_cols && !$this->data_table);
		
		//define print search
		$this->save_search();
		
		ob_start();
		$this->display_module($this->gb, array($this->get_paging()), 'automatic_display');
		$table = ob_get_contents();
		ob_end_clean();
		
		//define print order
		$this->save_order();
		
		$theme = Base_ThemeCommon::init_smarty();

		foreach (array_keys($this->areas) as $area)
			$theme->assign($area . '_area', $this->get_area($area));
		
		$theme->assign('table', $table);
				
		$scroll_wrapper = $this->init_table_addons($this->get_table_id());
		
		$theme->assign('scroll_wrapper', $scroll_wrapper);
		$theme->assign('scroll_height', $this->scroll_height);
			
		Base_ThemeCommon::display_smarty($theme, $this->get_type(), 'table');		
	}

	public function init_table_addons($table_id) {
		$container_selector = $this->init_layout();
		
		$scroll_wrapper = $this->init_table_scroll($table_id);
		$table_wrapper = $this->init_data_table($table_id, $container_selector);

		return $scroll_wrapper?: $table_wrapper;
	}
	
	public function init_layout() {
		if ($this->applet) return false;
		if (!$this->get_area('right') && !$this->get_area('left')) return false;
		
		load_js($this->get_module_dir() . 'js/jquery.layout-latest.js');
		load_js($this->get_module_dir() . 'js/layout.js');
				
		eval_js('Utils_Overview__layout.init(' . json_encode($this->layout_opts) . ');');
		
		Base_ThemeCommon::load_css($this->get_type(), 'layout');
		
		return array('id'=>'Utils_Overview__layout.center_id', 'resize_event'=>'"layoutpaneonresize"');
	}	
	
	public function init_table_scroll($table_id) {
		if (!$this->scroll_height || $this->data_table) return;
		
		load_js($this->get_module_dir() . 'js/jquery.floatThead.js');
			
		eval_js('jq("'.$table_id.'").floatThead({
					zIndex: 8,
    					scrollContainer: function($table){
       						return $table.closest(".scroll-wrapper");
    					}
					});');
		
		return 'scroll-wrapper';
	}
	
	public function init_data_table($table_id, $container=array()) {
		if (!$this->data_table) return false;
		
		$table_wrapper = 'table-wrapper';
		
		if ($this->applet)
			$container = array('id'=>"jq('$table_id').closest('.$table_wrapper')", 'resize_event'=>0);
		else {
			$container = is_array($container)? $container: array('id'=>$container);
			
			$container['resize_event'] = isset($container['resize_event'])? $container['resize_event']: 0;
			
			$container['id'] = !empty($container['id'])? $container['id'] : '""';
		}

		if (!is_array($this->data_table)) $this->data_table = array();
			
		load_js($this->get_module_dir() . 'js/jquery.dataTables.js');
		load_js($this->get_module_dir() . 'js/datatable.js');
		
		if ($this->resizable_cols) {
			load_js($this->get_module_dir() . 'js/dataTables.colResize.js');
			$this->data_table['dom'] = 'Zlfrtip';
		
			// 				load_js($this->get_module_dir() . 'js/ColReorderWithResize.js');
			// 				$this->data_table['dom'] = 'Rlfrtip';
		}

		eval_js('Utils_Overview__datatable.init("'.$table_id.'", '. json_encode($this->data_table) .', ' . $container['id'] . ', ' . $container['resize_event'] . ');');
		
		return $table_wrapper;
	}
		
	public function get_data_array() {
		$cols = $this->get_cols();
		$categories = $this->get_categories();
		$row_attrs = isset($this->row_attrs)? $this->row_attrs: array();

		$rows = array();		
		foreach ($categories as $category_id) {
			 $rows[$category_id] = $this->get_row_data($category_id, true);
			 if (!empty($row_attrs) && is_callable($row_attrs))
			 	$rows[$category_id]['__style__'] = call_user_func_array($row_attrs, array($category_id, $this->get_filter_values(), array('mode'=>'data')));
		}
		
		return Utils_Overview_TableCommon::sort_data($rows, $cols, $this->order);	
	}
	
	public function set_id($value) {
		$this->id = $value;
	}
	
	public function set_mode($value) {
		$this->mode = $value;
	}
	
	public function get_mode() {
		return $this->mode;
	}
	
	public function set_header($value) {
		$this->areas['header'] = $value;
	}
	
	public function set_footer($value) {
		$this->areas['footer'] = $value;
	}
	
	public function set_right_area($value) {
		$this->areas['right'] = $value;
	}
	
	public function set_left_area($value) {
		$this->areas['left'] = $value;
	}
		
	public function get_area($area, $pdf = false) {
		$ret = $this->areas[$area];
		if (is_callable($ret)) {
			$ret = call_user_func($ret, $this->get_filter_values(), $pdf);
		}
	
		return $ret;
	}
	
	public function set_prefix($arg) {
		$this->table_prefix = $arg;
	}
	
	public function get_prefix($pdf = false) {
		$ret = $this->table_prefix;
		if (is_callable($ret)) {
			$ret = call_user_func($ret, $this->get_filter_values(), $pdf, $this->filters_form, $tab);
		}
	
		return $ret;
	}
	
	public function set_postfix($arg) {
		$this->table_postfix = $arg;
	}
	
	public function get_postfix($pdf = false) {
		$ret = $this->table_postfix;
		if (is_callable($ret)) {
			$ret = call_user_func($ret, $this->get_filter_values(), $pdf, $this->filters_form, $tab);
		}
	
		return $ret;
	}
	
	public function get_paging() {
		$paging = false;
		if (count($this->get_categories()) > $this->paging_limit && !$this->applet) $paging = true;
		
		return $paging;
	}
	
	private function set_cell_actions($cell_data, $pdf = false) {
		if ($pdf || !is_array($cell_data) || !isset($cell_data['actions'])) return $cell_data;
	
		if (!isset($cell_data['value'])) $cell_data['value'] = '';
		
		if (!is_array($cell_data['actions']) || empty($cell_data['actions'])) return $cell_data;
		
		$cell_actions = '';
		foreach ($cell_data['actions'] as $action_settings) {
			$link_attrs = '';
			if (isset($action_settings['link'])) {
				$link_attrs = $action_settings['link'];
			}
			
			$cell_actions .= "<a {$this->get_action_tooltip_attrs($action_settings)} $link_attrs>" . $this->get_action_icon_img($action_settings['icon']) . '</a>';				
		}
		
		$position = '';
		if (isset($cell_data['actions_align'])) {
			switch ($cell_data['actions_align']) {
				case 'left':
					$position = 'left:0px;width:'. 20 * count($cell_data['actions']) .'px;';
					break;
				
				case 'center':
					$position = 'left:0px;width:100%;';
					break;
			}			
		}
		
		$cell_data['value'] = '<div class="Utils_Overview__cell">' . $cell_data['value'] . '<span class="Utils_Overview__cell_actions" style="' . $position . '">' . $cell_actions . '</span></div>';
				
		unset($cell_data['actions']);
		
		return $cell_data;
	}
	
	private function get_action_icon_img($icon) {
		$img = '';
		if (isset($icon)) {
			if (strpos($icon, '/')!==false) {
				$icon_url = $icon;
			}
			else {
				$icon_url = $this->get_action_icon_url($icon);
			}
			$img = '<img align="top" style="margin-left:5px;" src="'.$icon_url.'" />';
		}
		
		return $img;
	}
	
	private function get_action_icon_url($icon) {
		$search_modules = array(
			$this->get_type(),
			Utils_GenericBrowser::module_name(),
			Utils_RecordBrowser::module_name()
		);
		
		$ret = null;
		foreach ($search_modules as $module_type) {
			$ret = Base_ThemeCommon::get_template_file($module_type, $icon . '.png');
			
			if (!empty($ret)) break;
		}
		
		return $ret;
	}
	
	private function get_action_tooltip_attrs($action_settings) {
		$tooltip_attrs = '';
		if (!empty($action_settings['tooltip'])) {
			if (is_array($action_settings['tooltip']) && is_callable($action_settings['tooltip'][0]))
				$tooltip_attrs = call_user_func_array(array('Utils_TooltipCommon', 'ajax_open_tag_attrs'), $action_settings['tooltip']);
			else
				$tooltip_attrs = Utils_TooltipCommon::open_tag_attrs($action_settings['tooltip']);
		}
			
		if ($tooltip_attrs != '' && isset($action_settings['tooltip_leightbox']) && $action_settings['tooltip_leightbox']) {
			$tooltip_attrs .= ' ' . Utils_TooltipCommon::tooltip_leightbox_mode();
		}
		
		return $tooltip_attrs;
	}

	public function set_cols($cols = array()) {
		$this->cols = $cols;
		
		return $this;
	}
	
	public function get_cols($pdf = false) {
		if (!$this->cols_set) {	
			$cols = is_callable($this->cols) ? call_user_func($this->cols, $this->get_filter_values(), $pdf) : $this->cols;
			
			$this->cols = array();
			$set_width = 0;
			foreach ($cols as $id=>&$c) {
				$c['key'] = isset($c['key'])? $c['key']: $id;
				$c['width'] = isset($c['width'])? $c['width']:100;
				
				$this->cols[$c['key']] = $c;
				$set_width += $c['width'];
			}
			
			if ($pdf) {		
				$page_settings = Utils_Overview_TableCommon::page_settings();				
				$page_sizes = $page_settings['format']['mapping'][$pdf['format']];
				
				$page_width = (isset($pdf['orientation']) && $pdf['orientation'] == 'L') ? max($page_sizes) : min($page_sizes);
				
				$table_width_pct = isset($pdf['table_width'])? $pdf['table_width']: 100;
	
				foreach ($this->cols as &$c) {
					$c['attrs'] = 'style="border:1px solid black;font-weight:bold;text-align:center;color:white;background-color:gray;"';
						
					$c['width'] = $page_width / $set_width * $c['width'] * $table_width_pct / 100;
				}
			}
			
			$this->cols_set = true;
		}

		return $this->cols;
	}
	
	public function set_properties_from_array($settings) {
		if (!is_array($settings)) return;
		
		foreach ($settings as $key=>$value)
			if (is_callable(array($this, 'set_' . $key)))
				call_user_func(array($this, 'set_' . $key), $value);
	}

	public function set_icon($icon) {
		$this->icon = $icon;
	
		return $this;
	}
	
	public function set_filter_values($values) {
		if (!is_array($values)) return;
		
		return $this->filter_values = $values;
	}
	
	public function get_filter_values() {
		return array_merge($this->filter_values, array('__mode__'=>$this->get_mode(), '__applet__'=>$this->applet));
	}	

	public function set_caption($caption) {
		$this->caption = $caption;
	
		return $this;
	}
	
	public function get_caption($pdf = false) {
		return is_callable($this->caption) ? call_user_func($this->caption, $this->filters, $this->get_filter_values(), $pdf) : $this->caption;
	}	

	public function set_categories($categories = array()) {
		$this->categories = $categories;
		
		return $this;
	}
	
	public function get_categories($pdf = false) {
		//take the rows keys as categories if categories not set and rows provided
		if (empty($this->categories)) {
			$rows = $this->get_rows($pdf, true);
				
			if (!empty($rows))
				$this->categories = array_keys($rows);
		}
		else	
			$this->categories = is_callable($this->categories) ? call_user_func($this->categories, $this->get_filter_values(), $pdf) : $this->categories;

		return $this->categories;
	}

	public function set_rows($rows = array()) {
		$this->rows = $rows;
		if (!empty($this->rows))
			$this->row_callback = array();
		
		return $this;
	}
	
	public function get_rows($pdf, $use_empty_categories = false) {
		$this->rows = is_callable($this->rows)? call_user_func_array($this->rows, array($use_empty_categories? array(): $this->get_categories($pdf), $this->get_cols($pdf), $this->get_filter_values(), $pdf)): $this->rows;

		return $this->rows;
	}
	
	public function set_row_callback($callback = array()) {
		$this->row_callback = $callback;
		if (!empty($this->row_callback))
			$this->rows = array();
	
		return $this;
	}
	
	public function set_row_actions($row_actions = array()) {
		if (is_callable($row_actions))
			$this->row_action_callbacks[] = $row_actions;
				
		return $this;
	}

	public function set_expandable($value=true) {
		$this->expandable = $value;
	
		return $this;
	}
	
	public function set_applet($value=true) {
		$this->applet = $value;
		$this->resizable_cols = !$value;
		if ($value) {
			$this->disable_print();
			$this->set_scroll_height();
		}
		
		return $this;
	}
	
	public function set_filters($filters = array()) {
		$this->filters = $filters;
		
		return $this;
	}
	
	public function get_filters() {
		return $this->filters;
	}
	
	public function set_data_table($value=true) {
		$this->data_table = $value;
		
		return $this;
	}
	
	public function set_layout_opts($opts=array()) {
		$this->layout_opts = $opts;
	
		return $this;
	}
	
	public function set_scroll_height($value=400) {
		$this->scroll_height = $value;
		
		return $this;
	}
	
	public function set_resizable_cols($value=true) {
		$this->resizable_cols = $value;
	
		return $this;
	}
		
	public function set_default_order(array $arg) {
		$this->default_order = $arg;
	
		return $this;
	}
	
	public function set_print($print_settings = array()) {
		$this->print_settings = $print_settings;
	
		return $this;
	}
	
	public function set_export($export_settings = array()) {
		$this->export_settings = $export_settings;
	
		return $this;
	}
		
	public function disable_print() {
		$this->print_settings = false;
	
		return $this;
	}	
	
	public function disable_export() {
		$this->export_settings = false;
	
		return $this;
	}

	private function add_print_button() {
		if ($this->print_settings === false || $this->applet) return;
	
		$this->init_print_key();
	
		$data = array('tab'=>$this->id, 'mode'=>$this->get_mode(), 'key'=>$this->print_key);
		$printer = Utils_Overview_TableCommon::get_printer($this->id, $data);
		if ($printer) {
			$print_href = $printer->get_href($data);
		}
		else {
			$print_href = 'href="' . $this->get_module_dir() . 'print.php?' . http_build_query(array('cid'=>CID,'key'=>$this->print_key)) . '" target="_blank"';
		}
		
		Base_ActionBarCommon::add('print', __('Print'), $print_href, __('Click to print overview'));
	}
	
	private function add_export_button() {
		if ($this->export_settings === false || $this->applet) return;
	
		$this->init_print_key();
	
		$export_href = 'href="' . $this->get_module_dir() . 'export.php?' . http_build_query(array('cid'=>CID,'key'=>$this->print_key)) . '" target="_blank"';
		Base_ActionBarCommon::add('save', __('Export'), $export_href, __('Click to export overview'));
	}
		
	private function init_print_key() {		
		if (!empty($this->print_key)) return;
		
		$settings = array(
			'filter_values'=>$this->get_filter_values(),
			'caption'=>$this->caption,
			'icon'=>$this->icon,
			'filters'=>$this->filters,
			'cols'=>$this->cols, 
			'rows'=>$this->rows,
			'row_callback'=>$this->row_callback,
			'row_attrs'=>$this->row_attrs,
			'categories'=>$this->categories,
			'print'=>$this->print_settings,
			'export'=>$this->export_settings,
			'header'=>$this->areas['header'],
			'footer'=>$this->areas['footer'],
			'search'=>$this->search,
			'order'=>$this->order			
		);	
		
		//revert to static method of the module for printing
		foreach ($settings as $key=>&$value)
			if (isset($value[0]) && is_object($value[0]) && is_callable(array($value[0], 'get_type'))) $value[0] = $value[0]->get_type();

		$this->print_key = md5(serialize($this->get_parent_type()).serialize($this->get_instance_id()));

		$this->set_print_settings($settings);
				
		return $this;
	}
	
	public function set_print_settings($settings = array()) {
		if(empty($this->print_key))
			return false;
		
		$s = $this->get_print_settings($this->print_key);
		Module::static_set_module_variable($this->module_name(), $this->print_key, array_merge($s, $settings));
		
		return true;
	}
	
	public function get_print_settings($print_key) {
		return Module::static_get_module_variable($this->module_name(), $print_key, array());
	}
	
	public function set_paging_limit($limit) {
		if(!is_numeric($limit))
			trigger_error('Invalid argument passed to set_paging_limit method.',E_USER_ERROR);
		
		$this->paging_limit = $limit;
	
		return $this;
	}
	
	public function force_paging() {
		$this->paging_limit = 0;
	
		return $this;
	}
		
	public function set_search($value) {
		$this->search = $value;
			
		return $this;
	}
	
	public function set_order($value) {
		$this->order = $value;
			
		return $this;
	}
	
	public function save_search() {
		if (isset($this->gb) && $this->gb instanceof Utils_GenericBrowser) {
			$search = $this->gb->get_module_variable('search');
			if (!empty($search)) {
				$this->search = array('keyword'=>$search, 'advanced'=>$this->gb->is_adv_search_on());
				$this->set_print_settings(array('search'=>$this->search));
			}
		}
			
		return $this;
	}
		
	public function save_order() {
		if (isset($this->gb) && $this->gb instanceof Utils_GenericBrowser) {
			$this->order = $this->gb->get_module_variable('order');
			$this->set_print_settings(array('order'=>$this->order));	
		}
		
		return $this;
	}
	
	public function set_custom_label($arg, $args=''){
		$this->custom_label = $arg;
		$this->custom_label_args = $args;
	}	
	
	public function set_disable_paging($value) {		
		if ($value)
			$this->paging_limit = 10000;
	
		return $this;
	}

	public function get_pdf_title() {
		$filters_caption = Utils_OverviewCommon::get_filters_caption($this->filters, $this->get_filter_values());
	
		$ret = empty($filters_caption) ? '' : __('Filters') . ' - ' . $filters_caption;
	
		$search_caption = Utils_Overview_TableCommon::get_search_caption($this->search, $this->get_cols(true));
		if (!empty($search_caption)) {
			$ret .= empty($ret) ? '' : "\n";
			$ret .= __('Search Text') . ' - ' . $search_caption;
		}
	
		return $ret;
	}
	
	public function set_row_attrs($row_attrs = array()) {
		$this->row_attrs = $row_attrs;
		
		return $this;
	}
	
	public function get_table_id() {
		$path = $this->get_gb_path();
		
		if (!$path) return '';
		
		return '#table_'.md5($path);
	}
	
	public function get_gb_path() {
		if (isset($this->gb) && $this->gb instanceof Utils_GenericBrowser) {
			return $this->gb->get_path();
		}
		return '';
	}
	
	private function get_row_attrs_text($category_id, $pdf = false) {
		$row_attrs = isset($this->row_attrs)? $this->row_attrs: array();

		if (!empty($row_attrs) && is_callable($row_attrs))
			$attrs = call_user_func_array($row_attrs, array($category_id, $this->get_filter_values(), $pdf));
		elseif (isset($row_attrs[$category_id]))
			$attrs = $row_attrs[$category_id];
		else
			$attrs = array();

		$attrs = !empty($attrs)? $attrs: array();
		
		$attrs_text = '';
		if (is_string($attrs)) {
			if (!extension_loaded('simplexml'))
				trigger_error('SimpleXML not loaded:' . $this->get_path() . '.', E_USER_ERROR);
			
			$x = (array) new SimpleXMLElement("<element $attrs />");
			$attrs = isset($x['@attributes'])? $x['@attributes']: '';
		}
		if ($pdf) {
			$attrs['style'] = isset($attrs['style'])? $attrs['style']: '';
			$attrs['style'] .= 'page-break-inside:avoid;';
		}
		foreach ($attrs as $name=>$value) {
			$attrs_text .= $name . '=' . '"' . str_ireplace('"', '\"', $value) . '"';
		}		

		return $attrs_text;
	}
		
	private function set_gb_rows($pdf, $tab=null) {	
		if (!is_object($this->gb)) return false;
		
		$categories = $this->get_categories($pdf, $tab);
		$cols = $this->get_cols($pdf, $tab);

		//handle sorting pefore generating pdf
		$rows = array();
		if ($pdf) {
			foreach ($categories as $category_id) 
				$rows[$category_id] = $this->get_row_data($category_id, $pdf, $tab);

			$rows = Utils_Overview_TableCommon::sort_data($rows, $cols, $this->order);
			
			$categories = array_keys($rows);
		}

		foreach ($categories as $category_id) {
			$row_data = isset($rows[$category_id])? $rows[$category_id]: $this->get_row_data($category_id, $pdf);			
			if (empty($row_data)) continue;

			$gb_row = $this->gb->get_new_row();
			$gb_row->add_data_array($this->get_gb_row_data($row_data, $pdf));
			
			$attrs_text = $this->get_row_attrs_text($category_id, $pdf);
			$gb_row->set_attrs($attrs_text);

			$this->set_gb_row_actions($category_id, $gb_row, $pdf, $tab);
		}
		
		return true;
	}	

	private function get_row_data($category_id, $pdf = false) {
		$data = array();
		if (is_callable($this->row_callback)) {
			$this->rows[$category_id] = call_user_func_array($this->row_callback, array($category_id, $this->get_cols($pdf), $this->get_filter_values(), $pdf));
			$data = $this->rows[$category_id];
		}
		else {
			$rows = $this->get_rows($pdf);
			$data = isset($rows[$category_id])? $rows[$category_id]: array();
		}

		foreach ($data as $id=>&$cell) {
			if (!is_array($cell)) $cell = array('value'=>$cell);
			if (!isset($cell['value'])) $cell['value'] = '';
		}
	
		if ($pdf && !$this->check_if_row_fits_array($data, $pdf)) {
			$data = array();
		}

		return $data;
	}
	
	private function get_gb_row_data($row_data, $pdf) {
		$cols = $this->get_cols($pdf);

		$ret = array();
		foreach ($cols as $k=>$v)
			if (isset($row_data[$k]))
				$ret[] = $this->set_cell_actions($row_data[$k], $pdf);
			else
				$ret[] = '';

		return $ret;
	}	

	private function set_gb_row_actions($category_id, $gb_row, $pdf = false) {
		if ($pdf) return array();

		foreach ($this->row_action_callbacks as $callback) {
			if (is_callable($callback))
					call_user_func_array($callback, array($category_id, $gb_row, $this->get_filter_values()));
		}
	}	
		
	private function check_if_row_fits_array($row, $pdf = false) {
		if (empty($row)) return false;
		if (empty($this->search['keyword'])) return true;

		$search = $this->search['keyword'];
		$advanced = (isset($this->search['advanced']) && $this->search['advanced'])? true: false;
		$cols = $this->get_cols($pdf);
		
		if (!$advanced) {
			$ret = true;
			foreach ($cols as $k=>$v) {
				if (!isset($search['__keyword__']) || $search['__keyword__'] == '') return true;
				if (isset($v['search']) && isset($search['__keyword__'])) {
					$ret = false;
					if (stripos(strip_tags($row[$k]['value']), $search['__keyword__']) !== false) return true;
				}
			}
			return $ret;
		}
		else {
			$i = 0;
			foreach ($cols as $k=>$v) {
				if (isset($v['search']) && isset($search[$i]) && stripos(strip_tags($row[$k]['value']), $search[$i]) === false) return false;
				$i++;
			}
			return true;
		}
	}	
}
?>