<?php 
class Utils_Overview_Span_Object {
	private $bar_info = array();
	private $rows = array();
	private $order = array();

	public function add_bar($start, $span, $opts = array()) {
		$bar = new Utils_Overview_Span_ObjectBar($start, $span, $opts);

		$key = md5(uniqid(rand(), true));
		$this->bar_info[$key]['object'] = $bar;
		$this->bar_info[$key]['order'] = array('start'=>$start, 'weight'=>$bar->get_weight());

		return $bar;
	}

	private function set_rows() {
		if (!is_array($this->bar_info)) return false;

		$this->sort_bars();

		$this->rows = array();
		foreach ($this->bar_info as $key=>$bar_info) {
			$bar = $bar_info['object'];
			$start = $bar->get_start();
			$span = $bar->get_span();

			$overlaps = true;
				
			foreach ($this->rows as  $row_index=>$bars) {
				$overlaps = false;
				foreach ($bars as $exist_bar) {
					$exist_start = $exist_bar->get_start();
					$exist_span = $exist_bar->get_span();
						
					if ($exist_start + $exist_span > $start) {
						$overlaps = true;
						continue 2;
					}
				}

				if (!$overlaps) {
					$this->rows[$row_index][] = $bar;
					break;
				}
			}
				
			if ($overlaps) $this->rows[] = array($bar);
		}

		return true;
	}

	private function sort_bars() {
		if (!is_array($this->bar_info)) return false;

		usort($this->bar_info, function($a, $b) {
			$a_weight = isset($a['order']['weight'])? $a['order']['weight']: 0;
			$b_weight = isset($b['order']['weight'])? $b['order']['weight']: 0;

			$rdiff = $a_weight - $b_weight;
			if ($rdiff) return $rdiff;

			return $a['order']['start'] - $b['order']['start'];
		});

			return true;
	}

	public function display_rows($bar_height = 20) {
		Base_ThemeCommon::load_css(Utils_Overview_SpanCommon::module_name());

		$this->set_rows();

		$rows_html = array();
		foreach ($this->rows as $row) {
			$rows_html[] = $this->get_row_html($row, $bar_height);
		}
		$theme = Base_ThemeCommon::init_smarty();

		$theme->assign('rows', $rows_html);
		$theme->assign('height', $bar_height . 'px');

		Base_ThemeCommon::display_smarty($theme, Utils_Overview_SpanCommon::module_name(), 'rows');
	}

	private function get_row_html($row, $height) {
		//an array of td widths
		$tds = array();
		$pointer = 0;
		foreach ($row as $bar) {
			$start =  $bar->get_start();
			$span = $bar->get_span();
				
			if ($start > $pointer) {
				$tds[] = array('class'=>'Utils_Overview_Span__blank', 'width'=>$start - $pointer, 'html'=>'&nbsp;');
				$pointer = $start;
			}
				
			$tds[] = array('class'=>'Utils_Overview_Span__bar', 'width'=>$span, 'html'=>$bar->get_html($height));
			$pointer += $span;
		}

		if ($pointer < 100) {
			$tds[] = array('class'=>'Utils_Overview_Span__blank', 'width'=>100 - $pointer, 'html'=>'&nbsp;');
		}

		$theme = Base_ThemeCommon::init_smarty();

		$theme->assign('tds', $tds);

		ob_start();
		Base_ThemeCommon::display_smarty($theme, Utils_Overview_SpanCommon::module_name(), 'row');
		$ret = ob_get_contents();
		ob_end_clean();

		return $ret;
	}
}


class Utils_Overview_Span_ObjectBar {
	private $start;
	private $span;
	private $weight;
	private $label;
	private $attrs;
	private $actions;
	private $color;
	private $ranges;
	private $icon;

	public function __construct($start, $span, $opts = array()){
		$this->set_start($start)->set_span($span)->set_opts($opts);
	}

	public function set_start($value){
		if ($value < 0) $value = 0;
		if ($value > 100) $value = 100;

		$this->start = $value;

		return $this;
	}

	public function get_start(){
		return $this->start;
	}

	public function set_span($value){
		if ($this->start + $value > 100) $value = 100 - $this->start;

		$this->span = $value;

		return $this;
	}

	public function get_span(){
		return $this->span;
	}

	public function set_opts($opts){
		$this->set_weight(isset($opts['weight'])? $opts['weight']: 50);
		$this->set_label(isset($opts['label'])? $opts['label']: '');
		$this->set_attrs(isset($opts['attrs'])? $opts['attrs']: '');
		$this->set_actions(isset($opts['actions'])? $opts['actions']: '');
		$this->set_color(isset($opts['color'])? $opts['color']: '');
		$this->set_ranges(isset($opts['ranges'])? $opts['ranges']: array());
		$this->set_icon(isset($opts['icon'])? $opts['icon']: '');
	}

	public function set_weight($value){
		$this->weight = $value;

		return $this;
	}

	public function get_weight(){
		return $this->weight;
	}

	public function set_label($value){
		$this->label = $value;

		return $this;
	}

	public function set_attrs($value){
		if (!is_array($value)) $value =array($value);

		$this->attrs = $value;

		return $this;
	}

	public function set_color($value){
		$this->color = $value;

		return $this;
	}

	public function set_actions($value){
		$this->actions = $value;

		return $this;
	}

	public function set_ranges($value){
		if (!is_array($value)) $value = array($value);

		foreach ($value as $range) {
			$this->add_range($range);
		}

		return $this;
	}

	public function add_range($value){
		if (is_a('Utils_Overview_Span_ObjectBar', $value))
			$this->ranges[] = $value;

		return $this;
	}

	public function set_icon($value){
		$this->icon = $value;

		return $this;
	}

	public function get_html($height){
		$attrs = $this->attrs;
		if (empty($attrs['style'])) $attrs['style'] = '';
		
		if (!empty($this->color)) $attrs['style'] .= 'background-color:' . $this->color .';';
		$attrs['style'] .= 'overflow: hidden; white-space: nowrap; height:' . $height . 'px;';

		$attrs_text = '';
		foreach ($attrs as $key=>$value) {
			$attrs_text .= ' ';
			if (is_numeric($key)) $attrs_text .= $value;
			else $attrs_text .= $key . '="' . $value . '"';
		}

		$ret = '<div' . $attrs_text . '><span class="Utils_Overview_Span__bar__text">' . $this->label . '</span>';
		$ret .= $this->get_icon_html($height);
		$ret .= '</div>';//<div style="position:absolute; left: 0px; width: 30%;height: 5px; background-color: green;z-index: 10;"></div>

		return $ret;
	}

	public function get_icon_html($bar_height){
		$ret = '';

		if (!empty($this->icon)) {
			$ret = '<span class="Utils_Overview_Span__bar__icon">' . $this->icon . '</span>';
		}

		return $ret;
	}
}


?>