<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_MultiSelect extends Utils_RecordBrowser_Field_Select {
	protected $multiselect = true;
	
	public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
		if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
		 
		$field = $this->getId();
		$desc = $this;
		$param = $this->getParam();
		 
		$record = $rb_obj->record;
		
		$multi_adv_params = $this->callAdvParamsCallback($record);
		$format_callback = $multi_adv_params['format_callback'];
	
		$tab_crits = $this->getSelectTabCrits($record);
		$select_options = $this->getSelectOptions($record);
		
		$tabs = array_keys($tab_crits);		 
		
		if($param['single_tab'])
			$label = $this->getTooltip($this->getLabel(), $param['single_tab'], $tab_crits[$param['single_tab']]);
	
		if ($this->record_count > self::$options_limit) {
			$el = $form->addElement('automulti', $field, $label, array('Utils_RecordBrowserCommon', 'automulti_suggestbox'), array($rb_obj->tab, $tab_crits, $format_callback, $desc['param']), $format_callback);
			${'rp_' . $field} = $rb_obj->init_module(Utils_RecordBrowser_RecordPicker::module_name(), array());
			$filters_defaults = isset($multi_adv_params['filters_defaults']) ? $multi_adv_params['filters_defaults'] : array();
			$rb_obj->display_module(${'rp_' . $field}, array($tabs, $field, $format_callback, $param['crits_callback']?:$tab_crits, array(), array(), array(), $filters_defaults));
			$el->set_search_button('<a ' . ${'rp_' . $field}->create_open_href() . ' ' . Utils_TooltipCommon::open_tag_attrs(__('Advanced Selection')) . ' href="javascript:void(0);"><img border="0" src="' . Base_ThemeCommon::get_template_file('Utils_RecordBrowser', 'icon_zoom.png') . '"></a>');
		}
		else {
			$form->addElement('multiselect', $field, $label, $select_options, array('id' => $field));
		}
		if ($mode !== 'add')
			$form->setDefaults(array($field => $default));
	}	

	public function isOrderable() {
		return false;
	}	

	public function getQuickjump($advanced = false) {
		return true;
	}
	
	public function isSearchable($advanced = false){
		return true;
	}
	
	public function defaultValue() {
		return [];
	}
		
	public static function decodeValue($value, $htmlspecialchars = true) {
		return Utils_RecordBrowserCommon::decode_multi($value);
	}
		
	public static function encodeValue($value) {
		return Utils_RecordBrowserCommon::encode_multi($value);
	}
}
