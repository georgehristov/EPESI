<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_MultiSelect extends Utils_RecordBrowser_Recordset_Field_Select {
	protected $multiselect = true;
	
	public static function typeKey() {
		return 'multiselect';
	}
	
	public static function typeLabel() {
		return _M('Multi Select');
	}
	
	public function gridColumnOptions(Utils_RecordBrowser $recordBrowser) {
		return array_merge(parent::gridColumnOptions($recordBrowser), [
				'order' => false,
		]);
	}
	
	public function defaultValue($mode) {
		return [];
	}
		
	public static function decodeValue($value, $options = []) {
		return Utils_RecordBrowserCommon::decode_multi($value);
	}
		
	public static function encodeValue($value, $options = []) {
		return Utils_RecordBrowserCommon::encode_multi($value);
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		return Utils_RecordBrowser_Recordset_Field_Select::defaultDisplayCallback($record, $nolink, $desc, $tab);
	}
	
	public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;

		// --->backward compatibility
		if ($desc->getType() == 'multicommondata') return Utils_RecordBrowser_Recordset_Field_MultiCommonData::defaultQFfieldCallback($form, $desc, $mode, $default, $rb_obj);
		// <---backward compatibility

		if (! $desc instanceof Utils_RecordBrowser_Recordset_Field_MultiSelect) return;

		$param = $desc->getParam();

		$record = $rb_obj->record;

		$multi_adv_params = $desc->callAdvParamsCallback($record);
		$format_callback = $multi_adv_params['format_callback'];

		$tab_crits = $desc->getSelectTabCrits($record);
		$select_options = $desc->getSelectOptions($record);

		$tabs = array_keys($tab_crits);

		if ($param['single_tab']) $label = $desc->getTooltip($desc->getLabel(), $param['single_tab'], $tab_crits[$param['single_tab']]);

		if ($desc->record_count > Utils_RecordBrowser_Recordset_Field_Select::$options_limit) {
			$el = $form->addElement('automulti', $field, $label, [
					'Utils_RecordBrowserCommon',
					'automulti_suggestbox'
			], [
					$rb_obj->tab,
					$tab_crits,
					$format_callback,
					$desc['param']
			], $format_callback);
			${'rp_' . $field} = $rb_obj->init_module(Utils_RecordBrowser_RecordPicker::module_name(), []);
			$filters_defaults = $multi_adv_params['filters_defaults']?? [];
			$rb_obj->display_module(${'rp_' . $field}, [
					$tabs,
					$field,
					$format_callback,
					$param['crits_callback'] ?: $tab_crits,
					[],
					[],
					[],
					$filters_defaults
			]);
			
			$el->set_search_button('<a ' . ${'rp_' . $field}->create_open_href() . ' ' . Utils_TooltipCommon::open_tag_attrs(__('Advanced Selection')) . ' href="javascript:void(0);"><img border="0" src="' . Base_ThemeCommon::get_template_file('Utils_RecordBrowser', 'icon_zoom.png') . '"></a>');
		}
		else {
			$form->addElement('multiselect', $field, $label, $select_options, ['id' => $field]);
		}
		
		if ($mode !== 'add') $form->setDefaults([
				$field => $default
		]);
	}   
}
