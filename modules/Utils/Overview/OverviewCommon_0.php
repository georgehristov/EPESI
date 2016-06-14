<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-overview
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_OverviewCommon extends ModuleCommon {

	public static function get_filters_caption($filters, $module_vars, $include_field_names = true, $separator = ', ', $return_array = false) {
		$filters = empty($filters)? array(): $filters;
		
		$ret = array();
		foreach ($filters as $k=>$v) {
			if (!isset($v['type'])) continue;
			if (isset($v['print']) && !$v['print']) continue;
		
			$v['title'] = isset($v['title']) ? $v['title'] : '';
			$v['select_options'] = isset($v['select_options']) ? $v['select_options'] : null;
		
			if (empty($module_vars[$k])) continue;
		
			$ret[$k][] = ($include_field_names? _V($v['title']) . ': ':'');
			switch ($v['type']) {
				case 'datepicker' :
					$ret[$k] = $return_array? $module_vars[$k]: date('d M Y', strtotime($module_vars[$k]));
					break;
		
				case 'select' :
					$ret[$k] = isset($v['select_options'][$module_vars[$k]])?$v['select_options'][$module_vars[$k]]:'';
					break;
		
				case 'checkbox' :
					$ret[$k] = $module_vars[$k] ? __('Yes') : __('No');
					break;
					
				case 'submit' :
				case 'button' :
					$ret[$k] = '';
					break;
		
				default :
					$ret[$k] = $module_vars[$k];
					break;
			}
		}
		
		$ret = array_filter($ret);
		
		return $return_array? $ret: implode($separator, $ret);
	}
		
	public static function list_overviews($format = '%caption') {
		$modules = ModuleManager::call_common_methods('overviews');
		
		$ret = array();
		foreach ($modules as $module => $info) {
			foreach ($info as $tab_id=>$settings) {
				if (!isset($settings['caption'])) {
					$translated_caption = $settings['caption'] = $tab_id;
				} else {
					$translated_caption = _V($settings['caption']);
				}
				$ret[$tab_id] = str_replace(
					array('%tab', '%orig_caption', '%caption'),
					array($tab_id, $settings['caption'], $translated_caption),
					$format
				);
			}
		}
		return $ret;
	}
	
	public static function get_overview_modes($id) {
		$modules = ModuleManager::call_common_methods('overviews');

		foreach ($modules as $module => $info) {
			foreach ($info as $tab_id=>$settings) {
				if ($tab_id == $id) break 2;
			}
		}	
		
		return isset($settings['modes'])? $settings['modes']: array();
	}
	
	public static function check_overview($id) {
		$overviews = self::list_overviews();
		
		return array_key_exists($id, $overviews);
	}
}

?>