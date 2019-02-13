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
		if (!is_array($type_or_list)) {
			if (!$class) trigger_error("Attempting to register field $type_or_list without associated class", E_USER_ERROR);
			
			$type_or_list = [$type_or_list => $class];
		}

		self::$registry = array_merge(self::$registry, $type_or_list);
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
	
	public static function display_file($r, $nolink=false, $desc=null, $tab=null)
	{
		$labels = [];
		$inline_nodes = [];
		$fileStorageIds = self::decode_multi($r[$desc['id']]);
		$fileHandler = new Utils_RecordBrowser_FileActionHandler();
		foreach($fileStorageIds as $fileStorageId) {
			if(!empty($fileStorageId)) {
				$actions = $fileHandler->getActionUrlsRB($fileStorageId, $tab, $r['id'], $desc['id']);
				$labels[]= Utils_FileStorageCommon::get_file_label($fileStorageId, $nolink, true, $actions);
				$inline_nodes[]= Utils_FileStorageCommon::get_file_inline_node($fileStorageId, $actions);
			}
		}
		$inline_nodes = array_filter($inline_nodes);
		
		return implode('<br>', $labels) . ($inline_nodes? '<hr>': '') . implode('<hr>', $inline_nodes);
	}
	
	public static function QFfield_static_display(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if ($mode !== 'add' && $mode !== 'edit') {
			$value = Utils_RecordBrowserCommon::get_val($desc->getTab(), $desc->getId(), $rb_obj->record, false, $desc);
			$form->addElement('static', $desc->getId(), $desc->getLabel(), $value, ['id' => $desc->getId()]);
			return true;
		}
		return false;
	}
	
	public static function QFfield_hidden(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		$form->addElement('hidden', $field);
		$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_checkbox(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
		
		$el = $form->addElement('advcheckbox', $field, $label, '', ['id' => $field]);
		$el->setValues(['0','1']);
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_calculated(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$form->addElement('static', $field, $label);
		if (!is_array($rb_obj->record))
			$values = $rb_obj->custom_defaults;
		else {
			$values = $rb_obj->record;
			if (is_array($rb_obj->custom_defaults))
				$values = $values + $rb_obj->custom_defaults;
		}
		$val = isset($values[$field]) ?
		Utils_RecordBrowserCommon::get_val($rb_obj->tab, $field, $values, true, $desc)
			: '';
		if (!$val)
			$val = '[' . __('formula') . ']';
		$record_id = isset($rb_obj->record['id']) ? $rb_obj->record['id'] : null;
		$form->setDefaults([$field => '<div class="static_field" id="' . Utils_RecordBrowserCommon::get_calculated_id($rb_obj->tab, $field, $record_id) . '">' . $val . '</div>']);
	}
	
	public static function QFfield_integer(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$form->addElement('text', $field, $label, ['id' => $field]);
		$form->addRule($field, __('Only integer numbers are allowed.'), 'regex', '/^\-?[0-9]*$/');
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_float(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$form->addElement('text', $field, $label, ['id' => $field]);
		$form->addRule($field, __('Only numbers are allowed.'), 'numeric');
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_currency(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$form->addElement('currency', $field, $label, (isset($desc['param']) && is_array($desc['param']))?$desc['param']:[], ['id' => $field]);
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
		// set element value to persist currency over soft submit
		if ($form->isSubmitted() && $form->exportValue('submited') == false) {
			$default = $form->exportValue($field);
			$form->getElement($field)->setValue($default);
		}
	}
	
	public static function QFfield_text(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$maxlength = $desc->getParam();
		$label = $desc->getTooltip($desc->getLabel(), $maxlength);
			
		$form->addElement('text', $field, $label, ['id' => $field, 'maxlength' => $maxlength]);
		$form->addRule($field, __('Maximum length for this field is %s characters.', [$maxlength]), 'maxlength', $maxlength);
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_long_text(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$form->addElement('textarea', $field, $label, ['id' => $field]);
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_date(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$form->addElement('datepicker', $field, $label, ['id' => $field]);
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_timestamp(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$f_param = array('id' => $field);
		if ($desc['param'])
			$f_param['optionIncrement'] = array('i' => $desc['param']);
		$form->addElement('timestamp', $field, $label, $f_param);
		static $rule_defined = false;
		if (!$rule_defined) {
			$form->registerRule('timestamp_required', 'callback', 'timestamp_required', __CLASS__);
			$rule_defined = true;
		}
		if (isset($desc['required']) && $desc['required'])
			$form->addRule($field, __('Field required'), 'timestamp_required');
		if ($mode !== 'add' && $default)
			$form->setDefaults(array($field => $default));
	}
	
	public static function timestamp_required($v) {
		return $v['__datepicker'] !== '' && Base_RegionalSettingsCommon::reg2time($v['__datepicker'], false) !== false;
	}
	
	public static function QFfield_time(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());
			
		$time_format = Base_RegionalSettingsCommon::time_12h() ? 'h:i a' : 'H:i';
		$lang_code = Base_LangCommon::get_lang_code();
		$minute_increment = 5;
		if ($desc['param']) {
			$minute_increment = $desc['param'];
		}
		$form->addElement('timestamp', $field, $label, array('date' => false, 'format' => $time_format, 'optionIncrement' => array('i' => $minute_increment), 'language' => $lang_code, 'id' => $field));
		if ($mode !== 'add' && $default)
			$form->setDefaults(array($field => $default));
	}
	
	public static function QFfield_commondata(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$param = $desc->getParam();
		$label = $desc->getTooltip($desc->getLabel(), $param['array_id']);
			
		$param = explode('::', $desc['param']['array_id']);
		foreach ($param as $k => $v)
			if ($k != 0)
				$param[$k] = self::getFieldId($v);
		$form->addElement($desc['type'], $field, $label, $param, ['empty_option' => true, 'order' => $desc['param']['order']], ['id' => $field]);
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_select(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
		
		//--->backward compatibility
		switch ($desc->getType()) {
			case 'multiselect':
				self::QFfield_multiselect($form, $field, $label, $mode, $default, $desc, $rb_obj);
				return;
			case 'multicommondata':
				self::QFfield_multicommondata($form, $field, $label, $mode, $default, $desc, $rb_obj);
				return;
			
			default:
			break;
		}
		//<---backward compatibility
		
		if (!$desc instanceof Utils_RecordBrowser_Field_Select) return;
		
		$field = $desc->getId();
		$label = $desc->getTooltip($desc->getLabel());

		$record = $rb_obj->record;
		$param = $desc->getParam();
		$multi_adv_params = $desc->callAdvParamsCallback($record);
		$format_callback = $multi_adv_params['format_callback'];

		$tab_crits = $desc->getSelectTabCrits($record);
		$select_options = $desc->getSelectOptions($record);

		if ($param['single_tab']) $label = $desc->getTooltip($label, $param['single_tab'], $tab_crits[$param['single_tab']]);

		if ($desc->record_count > Utils_RecordBrowser_Field_Select::$options_limit) {
			$form->addElement('autoselect', $field, $label, $select_options, array(
					array(
							'Utils_RecordBrowserCommon',
							'automulti_suggestbox'
					),
					array(
							$rb_obj->tab,
							$tab_crits,
							$format_callback,
							$desc['param']
					)
			), $format_callback);
		}
		else {
			$select_options = array(
					'' => '---'
			) + $select_options;
			$form->addElement('select', $field, $label, $select_options, array(
					'id' => $field
			));
		}
		if ($mode !== 'add') $form->setDefaults(array(
				$field => $default
		));
	}
	
	public static function QFfield_multiselect(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
		
		//--->backward compatibility
		if ($desc->getType() == 'multicommondata')
			return self::QFfield_multicommondata($form, $field, $label, $mode, $default, $desc, $rb_obj);
		//<---backward compatibility
			
		if (!$desc instanceof Utils_RecordBrowser_Field_MultiSelect) return;
		
		$field = $desc->getId();
		$param = $desc->getParam();
		
		$record = $rb_obj->record;
		
		$multi_adv_params = $desc->callAdvParamsCallback($record);
		$format_callback = $multi_adv_params['format_callback'];
		
		$tab_crits = $desc->getSelectTabCrits($record);
		$select_options = $desc->getSelectOptions($record);
		
		$tabs = array_keys($tab_crits);
		
		if($param['single_tab'])
			$label = $desc->getTooltip($desc->getLabel(), $param['single_tab'], $tab_crits[$param['single_tab']]);
			
			if ($desc->record_count > Utils_RecordBrowser_Field_Select::$options_limit) {
			$el = $form->addElement('automulti', $field, $label, ['Utils_RecordBrowserCommon', 'automulti_suggestbox'], [$rb_obj->tab, $tab_crits, $format_callback, $desc['param']], $format_callback);
			${'rp_' . $field} = $rb_obj->init_module(Utils_RecordBrowser_RecordPicker::module_name(), []);
			$filters_defaults = isset($multi_adv_params['filters_defaults']) ? $multi_adv_params['filters_defaults'] : [];
			$rb_obj->display_module(${'rp_' . $field}, [$tabs, $field, $format_callback, $param['crits_callback']?:$tab_crits, [], [], [], $filters_defaults]);
			$el->set_search_button('<a ' . ${'rp_' . $field}->create_open_href() . ' ' . Utils_TooltipCommon::open_tag_attrs(__('Advanced Selection')) . ' href="javascript:void(0);"><img border="0" src="' . Base_ThemeCommon::get_template_file('Utils_RecordBrowser', 'icon_zoom.png') . '"></a>');
		}
		else {
			$form->addElement('multiselect', $field, $label, $select_options, ['id' => $field]);
		}
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	public static function QFfield_multicommondata(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
		
		$field = $desc->getId();
		$param = $desc->getParam();
		$label = $desc->getTooltip($desc->getLabel(), $param['array_id']);
		
		if (empty($param['array_id']))
			trigger_error("Commondata array id not set for field: $field", E_USER_ERROR);
			
		$select_options = Utils_CommonDataCommon::get_translated_tree($param['array_id'], $param['order']);
		if (!is_array($select_options))
			$select_options = array();
				
		$form->addElement('multiselect', $field, $label, $select_options, ['id' => $field]);
				
		if ($mode !== 'add')
			$form->setDefaults([$field => $default]);
	}
	
	public static function QFfield_autonumber(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
			
		$field = $desc->getId();
		$param = $desc->getParam();
		$label = $desc->getTooltip($desc->getLabel());

		$value = $default ?: Utils_RecordBrowser_Field_Autonumber::formatStr($param, null);
		$form->addElement('static', $field, $label);
		$record_id = $rb_obj->record['id'] ?? null;
		$field_id = Utils_RecordBrowserCommon::get_calculated_id($rb_obj->tab, $field, $record_id);
		$val = '<div class="static_field" id="' . $field_id . '">' . $value . '</div>';
		$form->setDefaults([$field => $val]);
	}
	
	public static function QFfield_file(&$form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::QFfield_static_display($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
		$record_id = isset($rb_obj->record['id']) ? $rb_obj->record['id']: 'new';
		$module_id = md5($rb_obj->tab . '/' . $record_id . '/' . $desc->getId());
		/** @var Utils_FileUpload_Dropzone $dropzoneField */
		$dropzoneField = Utils_RecordBrowser::$rb_obj->init_module('Utils_FileUpload#Dropzone', null, $module_id);
		$default = $desc->decodeValue($default);
		if ($default) {
			$files = [];
			foreach ( $default as $filestorageId ) {
				$meta = Utils_FileStorageCommon::meta($filestorageId);
				$arr = [
						'filename' => $meta['filename'],
						'type' => $meta['type'],
						'size' => $meta['size']
				];
				$backref = substr($meta['backref'], 0, 3) == 'rb:' ? explode('/', substr($meta['backref'], 3)): [];
				if (count($backref) === 3) {
					list($br_tab, $br_record, $br_field) = $backref;
					$file_handler = new Utils_RecordBrowser_FileActionHandler();
					$actions = $file_handler->getActionUrlsRB($filestorageId, $br_tab, $br_record, $br_field);
					if (isset($actions['preview'])) {
						$arr['file'] = $actions['preview'];
					}
				}
				$files[$filestorageId] = $arr;
			}
			$dropzoneField->set_defaults($files);
		}
		if (isset($desc['param']['max_files']) && $desc['param']['max_files'] !== false) {
			$dropzoneField->set_max_files($desc['param']['max_files']);
		}
		if (isset($desc['param']['accepted_files']) && $desc['param']['accepted_files'] !== false) {
			$dropzoneField->set_accepted_files($desc['param']['accepted_files']);
		}
		$dropzoneField->add_to_form($form, $desc->getId(), $desc->getLabel());
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