<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-datatables
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_DataTablesCommon extends ModuleCommon {

	public static function init($id) {
		return rOpenDev\DataTablesPHP\DataTable::instance($id);
	}
	
	public static function load_library_files() {
		Base_ThemeCommon::load_css(self::module_name(), 'jquery.dataTables');
		load_js('modules/' . self::module_name() . '/js/datatables.js');
		load_js('modules/' . self::module_name() . '/js/dataTables.colResize.js');
	}
	
	public static function call_data_callback($callback_info, $request = null) {
		$args = isset($callback_info['args'])? $callback_info['args']:array();

		return call_user_func_array($callback_info['func'], array($request, $args));
	}
	
	public static function format_response($draw, $info) {
		if (!isset($info['total']) || !isset($info['data']))
			return array('error'=>__('Improper data provided'));
		
		return array(
			'draw' => intval($draw),
			'recordsTotal' => intval($info['total']),
			'recordsFiltered' => isset($info['filtered'])? intval($info['filtered']): count($info['data']),
			'data' => self::data_output($info['columns'], $info['data']));
	}
	
	public static function data_output($columns, $data) {
		$out = [];
		$data = array_values($data); // Reset keys
		for ($i = 0, $ien = count($data); $i<$ien; ++$i) {
			$row = [];
	
			for ($j = 0, $jen = count($columns); $j<$jen; ++$j) {
				$column = $columns[$j];
	
				if (isset($column['formatter'])) {
					if (isset($column['data'])) {
						$row[ isset($column['data']) ? $column['data'] : $j ] = call_user_func($column['formatter'], $data[$i][ self::fromSQLColumn($column) ], $data[$i], $column);
					} else {
						$row[ $j ] = call_user_func($column['formatter'], $data[$i]);
					}
				} else {
					// Compatibility with the json .
					// if preg_match('#\.#', $column['data']) explode ('.', $colum['data'])...
					if (isset($column['data'])) {
						$row[ isset($column['data']) ? $column['data'] : $j ] = isset($data[$i][ self::fromSQLColumn($column) ])? $data[$i][ self::fromSQLColumn($column) ]: '';
					} else {
						$row[ $j ] = isset($data[$i][$j])? $data[$i][$j]: '';
					}
				}
			}
	
			$out[] = $row;
		}
	
		return $out;
	}
	
	public static function fromSQLColumn($column)
	{
		return isset($column['alias']) ? $column['alias'] : (isset($column['sql_name']) ? $column['sql_name'] : $column['data']);
	}
	
	public static function set_callback_info($hash, $callback) {
		$hash .= md5(serialize($callback));
		
		$_SESSION['client']['utils_datatables']['callbacks'][$hash] = $callback;
		
		return $hash;
	}
	
	public static function get_callback_info($hash) {
		if (!isset($_SESSION['client']['utils_datatables']['callbacks'][$hash]))
			return false;
		
			
		return $_SESSION['client']['utils_datatables']['callbacks'][$hash];
	}
	
	public static function get_order($request, $columns) {
		$order = array();
		if (isset($request['order']) && count($request['order'])) {
			for ($i = 0, $ien = count($request['order']);$i<$ien;$i++) {
				$columnIdx = intval($request['order'][$i]['column']);
				$column = $columns[$columnIdx];
				if (!isset($column['orderable']) || $column['orderable'] === true) {
					$order[$column['data']] = $request['order'][$i]['dir'] === 'asc' ? 'ASC' : 'DESC';
				}
			}
		}
	
		return $order;
	}
	
	public static function get_limit($request) {
		$ret = array();
		if (isset($request['start']) && $request['length'] != -1) {
			$ret = array('start'=>intval($request['start']), 'length'=>intval($request['length']));
		}
		return $ret;
	}
	
}

?>