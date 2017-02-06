<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-overview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Overview_TableCommon extends ModuleCommon {

	/*	print_settings (use Utils_Overview::enable_print)
	 *  - mode [html, data]: pass onto header/footer in which format the result should be returned
	 * 	- orientation [L, P]: page orientation, default L
	 * 	- logo : image href for custom logo, default EPESI setting
	 * 	- language: TCPDF language array
	 * 	- font: print font, default TCPDF default font
	 *  - font_size: print font size, default 9
	 *  - font_family: print font_family
	 *  - printed_by [true, false]: print "printed by" footer
	 *  - table_width: width of GB table in % of the page width
	 */
	public static function page_settings() {
		return array(
				'mode'=>'html',
				'orientation'=>array(
						'mapping'=>array('L'=>'Landscape', 'P'=>'Portrait'),
						'default'=>'P'
				),
				'format'=>array(
						'mapping'=>array(
								'A4'=>array(890, 660),
								'LETTER'=>array(890, 660),
								'LEGAL'=>array(890, 660)
						),
						'default'=>Base_User_SettingsCommon::get(Libs_TCPDF::module_name(),'page_format')
				),
				'logo'=>array(
						'default'=>null
				),
				'language'=>array(
						'default'=>null
				),
				'font'=>array(
						'default'=>Libs_TCPDFCommon::$default_font
				),
				'font_family'=>array(
						'default'=>''
				),
				'font_size'=>array(
						'default'=>9
				),
				'printed_by'=>array(
						'default'=>true
				),
		);
	}
	
	public static function export_settings() {
		return array(
				'template'=>array(
						'default'=>''
				),
				'mapping_row'=>array(
						'default'=>1
				),
				'start_row'=>array(
						'default'=>2
				),
		);
	}
	
	public static function get_pdf($print_key) {
		$ov = ModuleManager::new_instance('Utils_Overview_Table', null, 'overview');
	
		$settings = $ov->get_print_settings($print_key);

		ob_start();
	
		$pdf = self::get_export_settings($settings['print']);
	
		$ov->body($settings, $pdf);
	
		$html = ob_get_clean();
	
		$tcpdf = Libs_TCPDFCommon::new_pdf($pdf['orientation'], 'mm', $pdf['format']);
	
		$caption = $ov->get_caption($pdf);

		$title = $ov->get_pdf_title();
	
		if (!defined('PDF_HEADER_LOGO_WIDTH')) define ('PDF_HEADER_LOGO_WIDTH', 90);
	
		Libs_TCPDFCommon::prepare_header($tcpdf, $caption, $title, $pdf['printed_by'], $pdf['logo'], $pdf['language']);
		Libs_TCPDFCommon::add_page($tcpdf);
		Libs_TCPDFCommon::SetFont($tcpdf, $pdf['font'], $pdf['font_family'], $pdf['font_size']);
	
		$html = Libs_TCPDFCommon::stripHTML(str_replace(array('<br>','&nbsp;'),array('<br/>',' '),$html));
		Libs_TCPDFCommon::writeHTML($tcpdf, $html, false);
	
		return $tcpdf;
	}
	
	public static function get_xls($print_key) {
		$ov = ModuleManager::new_instance('Utils_Overview_Table', null, 'overview');
	
		$settings = $ov->get_print_settings($print_key);
		$ov->set_properties_from_array($settings);
	
		$export_table = $ov->get_data_array();
	
		$export = self::get_export_settings($settings['export'], 'xls');
	
		if (file_exists($export['template'])) {
			try {
				$xls = Libs_PHPExcelCommon::load($export['template']);
			} catch(Exception $e) {
				print(__('Error:').'<br>'.$e->getMessage());
	
				return false;
			}
	
			$mapping_row = $export['mapping_row'];
			$fields = reset($xls->getActiveSheet()->rangeToArray('A'.$mapping_row.':Z'.$mapping_row));
		}
		else {
			require_once 'modules/Libs/PHPExcel/lib/PHPExcel.php';
			$xls = new PHPExcel();
			$cols = $ov->get_cols();
	
			$col_names = array();
			foreach ($cols as $field=>$c) {
				$col_names[$field] = array('value'=>$c['name']);
			}
	
			//add column names as first row of the export table
			$export_table = array_merge(array($col_names), $export_table);
	
			$fields = array_keys($cols);
		}
	
		$xls_row = $xls_start_row = $export['start_row'];
	
		//write the data rows
		foreach ($export_table as $row) {
			$output_row = array();
			foreach($fields as $field) {
				$value = isset($row[$field]['value'])?strip_tags($row[$field]['value']):'';
				$output_row[] = str_ireplace(array('&nbsp;'), array(' '), $value);
			}
	
			$xls->getActiveSheet()->fromArray($output_row, NULL, 'A'.$xls_row);
	
			$xls_row++;
		}
	
		//set the column styles
		for ($i = 0; $i <count($fields); $i++) {
			$xls->getActiveSheet()->duplicateStyle($xls->getActiveSheet()->getStyle(self::num2char($i).$xls_start_row),self::num2char($i).($xls_start_row+1).':'.self::num2char($i).$xls_row);
		}
	
		return $xls;
	}
	
	public static function get_data($print_key) {
		$ov = ModuleManager::new_instance('Utils_Overview_Table', null, 'overview');
	
		$settings = $ov->get_print_settings($print_key);
		$settings['print']['mode'] = 'data';
	
		$ov->set_properties_from_array($settings);
	
		$pdf = self::get_export_settings($settings['print'], 'data');
	
		$ret = array();
		$ret['main'] = $ov->get_data_array();
	
		$filters = Utils_OverviewCommon::get_filters_caption($ov->get_filters(), $ov->get_filter_values(), false, '', true);
	
		$ret['filters'] = array($filters);
		$ret['header'] = $ov->get_area('header', $pdf);
		$ret['footer'] = $ov->get_area('footer', $pdf);
	
		return $ret;
	}
	
	public static function get_export_settings($module_settings, $mode = 'pdf') {
		$module_settings = empty($module_settings)? array(): $module_settings;
	
		switch ($mode) {
			case 'pdf':
				$export_settings = self::page_settings();
				break;
					
			case 'xls':
				$export_settings = self::export_settings();
				break;
					
			case 'data':
				$export_settings = self::page_settings();
				break;
	
			default:
				return array();
				break;
		}
	
		$module_settings = is_array($module_settings)? $module_settings: array();
		foreach ($export_settings as $parameter=>$settings) {
			$default = isset($settings['default'])? $settings['default']: null;
	
			$module_settings[$parameter] = isset($module_settings[$parameter])? $module_settings[$parameter]: $default;
	
			if (!isset($settings['mapping'])) continue;
	
			if (!array_key_exists($module_settings[$parameter], $settings['mapping']))
				$module_settings[$parameter] = $default;
		}
	
		return $module_settings;
	}
	
	public static function sort_data($data, $cols, $order) {
		if (empty($order)) return $data;
	
		$order = $order[0];
	
		$col = array();
		foreach ($data as $category_id=>$row_data) {
			foreach ($row_data as $col_id=>$cell)
				if (isset($cols[$col_id]['order']) && $cols[$col_id]['order'] == $order['order']) {
					if (is_array($cell)) {
						if (isset($cell['order_value']))
							$xxx = $cell['order_value'];
						else
							$xxx = $cell['value'];
					}
					else
						$xxx = $cell;
					if (isset($cols[$col_id]['order_preg'])) {
						$ret = array();
						preg_match($cols[$col_id]['order_preg'], $xxx, $ret);
						$xxx = isset($ret[1]) ? $ret[1] : '';
					}
					$xxx = strip_tags(strtolower($xxx));
					$col[$category_id] = $xxx;
				}
		}
	
		asort($col);
		$data2 = array();
	
		foreach ($col as $category_id=>$v) {
			$data2[$category_id] = $data[$category_id];
		}
		if ($order['direction'] != 'ASC') {
			$data2 = array_reverse($data2);
		}
	
		return $data2;
	}		

	public static function get_search_caption($search, $cols) {
		$ret = '';
		if (!empty($search)) {
			if (isset($search['advanced']) && $search['advanced']) {
				$i = 0;
				foreach ($cols as $k=>$v) {
					if (isset($v['search']) && isset($search['keyword'][$i])) $ret .= _V($v['name']) . ': ' . $search['keyword'][$i];
					$i++;
				}
			}
			else {
				$ret = $search['keyword']['__keyword__'];
			}
		}
	
		return $ret;
	}

	public static function num2char($num) {
		$numeric = $num % 26;
		$letter = chr(65 + $numeric);
		$num2 = intval($num / 26);
		if ($num2 > 0) {
			return num2char($num2 - 1) . $letter;
		} else {
			return $letter;
		}
	}	

	public static function set_printer($id,$class) {
		Base_PrintCommon::register_printer(new $class());
		$exists = DB::GetOne('SELECT 1 FROM overview_table_properties WHERE id=%s', array($id));
	
		if ($exists)
			DB::Execute('UPDATE overview_table_properties SET printer=%s WHERE id=%s', array($class, $id));
		else
			DB::Execute('INSERT INTO overview_table_properties SET id=%s, printer=%s', array($id, $class));
	}
	
	public static function unset_printer($id) {
		return DB::Execute('DELETE FROM overview_table_properties WHERE id=%s', array($id));
	}
	
	public static function get_printer($id, $data) {
		$class = DB::GetOne('SELECT printer FROM overview_table_properties WHERE id=%s',$id);
		if($class && class_exists($class)) {
			$printer = new $class();
			$templates = $printer->default_templates($data);
			if (!empty($templates)) return $printer;
		}
		return false;
	}
}

?>