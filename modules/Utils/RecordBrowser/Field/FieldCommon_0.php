<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2016, Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Field
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_FieldCommon extends ModuleCommon {
	protected static $registry = [];
	
	public static function sort_by_processing_order($fields) {
		uasort($fields, array(__CLASS__, 'processing_order_compare'));
	}
	
	private static function processing_order_compare($f1, $f2) {
		return $f1->get_processing_order() > $f2->get_processing_order();
	}	

	public static function get_callbacks($tab, $field) {
		static $cache;
		
		if (!isset($cache[$tab])) {
			$result = DB::Execute("SELECT * FROM {$tab}_callback");
			while ($row = $result->FetchRow())
				$cache[$tab][$row['field']][$row['freezed']? 'display_callback': 'QFfield_callback'] = $row['callback'];
		}

		return array(
			'display_callback'=>$cache[$tab][$field]['display_callback']?? false, 
			'QFfield_callback'=>$cache[$tab][$field]['QFfield_callback']?? false
		);
	}
	
	public static function register($type_or_list, $class = null) {
		if (is_array($type_or_list)) {
			self::$registry = array_merge(self::$registry, $type_or_list);
			return;
		}
		
		self::$registry[$type_or_list] = $class;
	}
	
	public static function get_registered_fields() {
		return self::$registry;
	}
	
	////////////////////////////
	// default display callbacks
	
	public static function display_select($record, $nolink=false, $desc=null, $tab=null) {
		$ret = '---';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$val = $record[$desc['id']];
			$commondata_sep = '/';
			if ((is_array($val) && empty($val))) return $ret;
			
			$param = Utils_RecordBrowserCommon::decode_select_param($desc['param']);
			
			if(!$param['array_id'] && $param['single_tab'] == '__COMMON__') return;
			
			if (!is_array($val)) $val = array($val);
			
			$ret = '';
			foreach ($val as $v) {
				$ret .= ($ret!=='')? '<br>': '';
				
				if ($param['single_tab'] == '__COMMON__') {
					$array_id = $param['array_id'];
					$path = explode('/', $v);
					$tooltip = '';
					$res = '';
					if (count($path) > 1) {
						$res .= Utils_CommonDataCommon::get_value($array_id . '/' . $path[0], true);
						if (count($path) > 2) {
							$res .= $commondata_sep . '...';
							$tooltip = '';
							$full_path = $array_id;
							foreach ($path as $w) {
								$full_path .= '/' . $w;
								$tooltip .= ($tooltip? $commondata_sep: '').Utils_CommonDataCommon::get_value($full_path, true);
							}
						}
						$res .= $commondata_sep;
					}
					$label = Utils_CommonDataCommon::get_value($array_id . '/' . $v, true);
					if (!$label) continue;
					$res .= $label;
					$res = Utils_RecordBrowserCommon::no_wrap($res);
					if ($tooltip) $res = '<span '.Utils_TooltipCommon::open_tag_attrs($tooltip, false) . '>' . $res . '</span>';
				} else {
					$tab_id = Utils_RecordBrowserCommon::decode_record_token($v, $param['single_tab']);
					
					if (!$tab_id) continue;
					
					list($select_tab, $id) = $tab_id;
					
					if ($param['cols']) {
						$res = Utils_RecordBrowserCommon::create_linked_label($select_tab, $param['cols'], $id, $nolink);
					} else {
						$res = Utils_RecordBrowserCommon::create_default_linked_label($select_tab, $id, $nolink);
					}
				}
				
				$ret .= $res;
			}
		}
		
		return $ret;
	}
	public static function display_multiselect($record, $nolink=false, $desc=null, $tab=null) {
		return self::display_select($record, $nolink, $desc, $tab);
	}
	public static function display_multicommondata($record, $nolink=false, $desc=null, $tab=null) {
		return self::display_select($record, $nolink, $desc, $tab);
	}
	public static function display_commondata($record, $nolink=false, $desc=null, $tab=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$arr = explode('::', $desc['param']['array_id']);
			$path = array_shift($arr);
			foreach($arr as $v) $path .= '/' . $record[Utils_RecordBrowserCommon::get_field_id($v)];
			$path .= '/' . $record[$desc['id']];
			$ret = Utils_CommonDataCommon::get_value($path, true);
		}
		
		return $ret;
	}
	public static function display_autonumber($record, $nolink=false, $desc=null, $tab=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$ret = $record[$desc['id']];
			
			if (!$nolink && isset($record['id']) && $record['id'])
				$ret = Utils_RecordBrowserCommon::record_link_open_tag_r($tab, $record) . $ret . Utils_RecordBrowserCommon::record_link_close_tag();
		}
		
		return $ret;
	}
	public static function display_currency($record, $nolink=false, $desc=null, $tab=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$val = Utils_CurrencyFieldCommon::get_values($record[$desc['id']]);
			$ret = Utils_CurrencyFieldCommon::format($val[0], $val[1]);
		}
		
		return $ret;
	}
	public static function display_checkbox($record, $nolink=false, $desc=null, $tab=null) {
		$ret = '';
		if (isset($desc['id']) && array_key_exists($desc['id'], $record)) {
			$ret = $record[$desc['id']]? __('Yes'): __('No');
		}
		
		return $ret;
	}
	public static function display_checkbox_icon($record, $nolink, $desc=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$ret = '<img src="'.Base_ThemeCommon::get_template_file('images', ($record[$desc['id']]? 'checkbox_on': 'checkbox_off') . '.png') .'">';;
		}
		
		return $ret;
	}
	public static function display_date($record, $nolink, $desc=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$ret = Base_RegionalSettingsCommon::time2reg($record[$desc['id']], false, true, false);
		}
		
		return $ret;
	}
	public static function display_timestamp($record, $nolink, $desc=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$ret = Base_RegionalSettingsCommon::time2reg($record[$desc['id']], 'without_seconds');
		}
		
		return $ret;
	}
	public static function display_time($record, $nolink, $desc=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']])) {
			$ret = $record[$desc['id']] !== '' && $record[$desc['id']] !== false
			? Base_RegionalSettingsCommon::time2reg($record[$desc['id']], 'without_seconds', false)
			: '---';
		}
		
		return $ret;
	}
	public static function display_long_text($record, $nolink, $desc=null) {
		$ret = '';
		if (isset($desc['id']) && isset($record[$desc['id']]) && $record[$desc['id']]!=='') {
			$ret = Utils_RecordBrowserCommon::format_long_text($record[$desc['id']]);
		}
		
		return $ret;
	}
}

Utils_RecordBrowser_FieldCommon::register([
		'text' => Utils_RecordBrowser_Field_Text::class,
		'long text' => Utils_RecordBrowser_Field_LongText::class,
		'select' => Utils_RecordBrowser_Field_Select::class,
		'multiselect' => Utils_RecordBrowser_Field_MultiSelect::class,
		'commondata' => Utils_RecordBrowser_Field_CommonData::class,
		'multicommondata' => Utils_RecordBrowser_Field_MultiCommonData::class,
		'float' => Utils_RecordBrowser_Field_Float::class,
		'integer' => Utils_RecordBrowser_Field_Integer::class,
		'date' => Utils_RecordBrowser_Field_Date::class,
		'time' => Utils_RecordBrowser_Field_Time::class,
		'timestamp' => Utils_RecordBrowser_Field_Timestamp::class,
		'currency' => Utils_RecordBrowser_Field_Currency::class,
		'checkbox' => Utils_RecordBrowser_Field_Checkbox::class,
		'calculated' => Utils_RecordBrowser_Field_Calculated::class,
		'autonumber' => Utils_RecordBrowser_Field_Autonumber::class,
		'currency' => Utils_RecordBrowser_Field_Currency::class,
		'hidden' => Utils_RecordBrowser_Field_Hidden::class,
]);

?>