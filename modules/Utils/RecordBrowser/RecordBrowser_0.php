<?php
/**
 * RecordBrowserCommon class.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die();

class Utils_RecordBrowser extends Module {
	private $recordset;
	private $addInTable = false;
	private $genericBrowser;
	private $columnOrder = [];
	private $rowsExpandable = true;
	private $customColumns = [];
	private $nolink = false;
	private $absolute = false;
	private $tableTemplate;
	private $tableColumns = [];
	private $tableFields;
	private $recordsCount = 0;
	private $additionalCaption = '';
	private $customDefaults = [];
	private $multipleDefaults = []; 
	private $crits = [];
	private $navigationExecuted = false;
	private $searchCritsCallback;
	private $action = 'browse';
	private $browseModeController;

    private $recent = 0;
    private $caption = '';
    private $icon = '';
    private $full_history = true;

    private $add_button = null;
    private $more_add_button_stuff = '';
    private $changed_view = false;
    private $is_on_main_page = false;
 
    private $custom_filters = array();
    private $default_order = array();
    private $fullscreen_table = false;

    private $switch_to_addon = null;
    
    private $enable_export = false;
	
	private $fields_in_tabs = array();
	private $hide_tab = array();
    private $jump_to_new_record = false;
    
    
    
    public static $tab_param = '';
    public static $clone_result = null;
    public static $clone_tab = null;
    public static $last_record = null;
    public static $rb_obj = null;
    public $record;
    public $adv_search = false;
    
    private $advanced = array();
    public static $browsed_records = null;
    public static $access_override = array('tab'=>'', 'id'=>'');
    public static $mode = 'view';
    
    private $current_field = null;
    private $additional_actions_methods = array();
    private $filter_crits = array();

	private $disabled = [
			'search' => false,
			'browse_mode' => [],			
			'quickjump' => false,
			'filters' => false,
			'headline' => false,
			'actions' => false,
			'pdf' => false,
			'export' => false,
			'pagination' => false,
			'order' => false
	];

	private $force_order = [];
    private $clipboard_pattern = false;
    private $show_add_in_table = false;
    
    public $view_fields_permission;
    public $form = null;
    public $tab;
    public $grid = null;
    private $fixed_columns_class = array('Utils_RecordBrowser__favs', 'Utils_RecordBrowser__watchdog');
    private $include_tab_in_id = false;

	public function new_button($type, $label, $href) {
		if ($this->fullscreen_table)
			Base_ActionBarCommon::add($type, $label, $href);
		else {
			if (!file_exists($type))
				$type = Base_ThemeCommon::get_template_file(Base_ActionBar::module_name(), 'icons/'.$type.'.png');
			$this->more_add_button_stuff .= '<a class="record_browser_button" id="Base_ActionBar" '.$href.'>'.'<img src="'.$type.'">'.
				'<div style="display:inline-block;position: relative;top:-8px;">'.$label.'</div>'.
				'</a>';
		}
	}

    public function set_filter_crits($field, $crits) {
        $this->filter_crits[$field] = $crits;
    }

    public function switch_to_addon($arg) {
        $this->switch_to_addon = $arg;
    }

    public function hide_tab($tab) {
        $this->hide_tab[$tab] = true;
    }

    /**
     * @return array
     * @deprecated use getCustomDefaults
     */
    public function get_custom_defaults(){
        return $this->customDefaults;
    }
    
    public function getCustomDefaults(){
        return $this->customDefaults;
    }
    
    public function get_crits() {
    	return $this->crits;
    }

    public function get_final_crits() {
        if (!$this->displayed()) trigger_error('You need to call display_module() before calling get_final_crits() method.', E_USER_ERROR);
        return $this->get_module_variable('crits_stuff');
    }

    public function enable_export($arg) {
        $this->enable_export = $arg;
    }
    
    /**
     * @param string $caption
     * @deprecated use setCaption
     */
    public function set_caption($caption) {
    	return $this->setCaption($caption);
    }
    
    public function setCaption($caption) {
    	$this->caption = _M($caption);
    	
    	return $this;
    }
    
    public function getCaption() {
    	return _V($this->caption);
    }
    
    public function getHeadline() {
    	if ($this->disabled['headline']) return null;
    	
    	return implode(' - ', array_filter([$this->getCaption(), $this->getAdditionalCaption()])) . $this->get_jump_to_id_button();
    }
    
    /**
     * @param string $icon
     * @deprecated use setIcon
     */
    public function set_icon($icon) {
    	return $this->setIcon($icon);
    }
    
    public function setIcon($icon) {
    	if (!$icon) return;
    	
    	if (is_array($icon)) {
    		$icon = array_values($icon);
    		$icon = Base_ThemeCommon::get_template_file($icon[0], $icon[1]?? null);
    	}
    	
    	$this->icon = $icon;
    	
    	return $this;
    }
    
    public function getIcon() {
    	return $this->icon;
    }

    /**
     * @param string $arg
     * @deprecated use setAdditionalCaption
     */
    public function set_additional_caption($arg) {
    	return $this->setAdditionalCaption($arg);
    }
    
    public function setAdditionalCaption($additionalCaption) {
    	$this->additionalCaption = $additionalCaption;
    	
    	return $this;
    }
    public function getAdditionalCaption() {
    	return $this->additionalCaption;
    }

    public function set_jump_to_new_record($arg = true) {
        $this->jump_to_new_record = $arg;
    }

    public function set_additional_actions_method($callback) {
        $this->additional_actions_methods[] = $callback;
    }

    private function call_additional_actions_methods($row, $gb_row)
    {
        foreach ($this->additional_actions_methods as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, $row, $gb_row, $this);
            }
        }
    }

    /**
     * @param array $order
     * @deprecated use setColumnOrder
     */
    public function set_table_column_order($order) {
    	return $this->setColumnOrder($order);
    }
	
    public function setColumnOrder($columnOrder) {
    	$this->columnOrder = array_flip(array_values($columnOrder));
    	
    	return $this;
    }
	
    public function getColumnOrder() {
    	return $this->columnOrder;
    }

	public function getNolink() {
		return $this->nolink;
	}

	public function setNolink($nolink) {
		$this->nolink = $nolink;
		
		return $this;
	}

	protected function getRecordsCount() {
		return $this->recordsCount;
	}

	protected function setRecordsCount($recordsCount) {
		$this->recordsCount = $recordsCount;
		
		return $this;
	}

	public function getTableTemplate() {
		return $this->tableTemplate;
	}

	public function setTableTemplate($tableTemplate) {
		$this->tableTemplate = $tableTemplate;
		
		return $this;
	}

	public function getAbsolute() {
		return $this->absolute;
	}

	public function setAbsolute($absolute) {
		$this->absolute = $absolute;
		
		return $this;
	}

	/**
	 * @param array|string $callback
	 * @deprecated use setSearchCritsCallback
	 * 
	 */
	public function set_search_calculated_callback($callback) {
		$this->searchCritsCallback = $callback;
	}
	
	public function setSearchCritsCallback($callback) {
		$this->searchCritsCallback = $callback;
		
		return $this;
	}

    public function get_val($field, $record, $links_not_recommended = false) {
        return Utils_RecordBrowserCommon::get_val($this->getTab(), $field, $record, $links_not_recommended);
    }

    /**
     * @param bool $bool
     * @deprecated use setRowsExpandable
     */
    public function set_expandable_rows($bool)
    {
        return $this->setRowsExpandable($bool);
    }
    
    /**
     * Enable or disable expandable rows of the table
     * 
     * @param boolean $expandable
     */
    public function setRowsExpandable($expandable = true)
    {
    	$this->rowsExpandable = $expandable;
    }
    
    public function getRowsExpandable()
    {
    	return $this->rowsExpandable;
    }

    public function disable_search(){$this->disabled['search'] = true;}
    
    public function disable_browse_mode_switch(){$this->disabled['browse_mode'] = true;}

	public function disable_browse_mode($mode) {
		if ($this->disabled['browse_mode'] === true) return;
		
		$this->disabled['browse_mode'] = $this->disabled['browse_mode']?: [];
		
		$this->disabled['browse_mode'][$mode] = true;
	}
    
    public function disable_watchdog(){$this->disable_browse_mode('watchdog');}
    public function disable_fav(){$this->disable_browse_mode('favourites');}
    public function disable_favourites(){$this->disable_browse_mode('favourites');}
    public function disable_filters(){$this->disabled['filters'] = true;}
    public function disable_quickjump(){$this->disabled['quickjump'] = true;}
    public function disable_headline() {$this->disabled['headline'] = true;}
    public function disable_pdf() {$this->disabled['pdf'] = true;}
    public function disable_export() {$this->disabled['export'] = true;}
    public function disable_order() {$this->disabled['order'] = true;}
    public function disable_actions($arg=true) {$this->disabled['actions'] = $arg;}
    public function disable_pagination($arg=true) {$this->disabled['pagination'] = $arg;}

    public function set_button($arg, $arg2=''){
        $this->add_button = $arg;
        $this->more_add_button_stuff = $arg2;
    }

    /**
     * @param array $ar
     * @deprecated
     */
    public function set_header_properties($ar) {
        return $this->setCustomColumns($ar);
    }
    
    /**
     * Modify deafult grid columns
     * Hide / show / set label / set width, etc 
     * 
     * @param array $customColumns
     */
    public function setCustomColumns($customColumns = []) {
    	foreach ($customColumns as $key => $value) {
    		if (is_bool($value)) {
    			$value = ['visible' => $value];
    		}
    		elseif (is_numeric($value)) {
    			$value = ['position' => $value];
    		}
    		
    		$customColumns[$key] = $value;
    	}
    	
    	$this->customColumns = $customColumns;
    }
    
    public function getCustomColumns() {
    	return $this->customColumns;
    }

    public function get_access($action, $record=null){
        return Utils_RecordBrowserCommon::get_access($this->getTab(), $action, $record);
    }

 	public function construct($tab = null, $special = false) {
		Utils_RecordBrowserCommon::$options_limit = Base_User_SettingsCommon::get('Utils_RecordBrowser','enable_autocomplete');
        if (!$special)
			self::$rb_obj = $this;
        
        $this->setTab($this->get_module_variable('tab', $tab));
        
		load_js($this->get_module_dir() . 'main.js');
    }

    /**
     * @param boolean $admin
     * @param boolean $force
     * @deprecated use getRecordset method instead 
     */
    public function init($admin=false, $force=false) {
    	$this->recordset = Utils_RecordBrowser_Recordset::create($this->tab, $force);
    	
        if($this->tab=='__RECORDSETS__' || preg_match('/,/',$this->tab)) $params=array('','',0,0,0);
        else $params = $this->getRecordset()->getProperties();
        if ($params==false) trigger_error('There is no such recordSet as '.$this->tab.'.', E_USER_ERROR);

        $this->clipboard_pattern = $this->getRecordset()->getClipboardPattern();

        //If Caption or icon not specified assign default values
        $this->caption = $this->getRecordset()->getCaption();
        $this->icon = $this->getRecordset()->getIcon();
        $this->full_history = $this->getRecordset()->getProperty('full_history');
    }

    public function check_for_jump() {
    	if($x = Utils_RecordBrowserCommon::check_for_jump()) {
    		self::$browsed_records = $this->get_module_variable('browsed_records');
    	}

        return $x;
    }
	
	public function add_note_button_href($key=null) {
        return Utils_RecordBrowserCommon::create_new_record_href('utils_attachment',array('permission'=>'0','local'=>$key,'func'=>serialize(array('Utils_RecordBrowserCommon','create_default_linked_label')),'args'=>serialize(explode('/',$key))));
	}
	
	public function add_note_button($key=null) {
		$href = $this->add_note_button_href($key);
		return '<a '.Utils_TooltipCommon::open_tag_attrs(__('New Note')).' '.$href.'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_Attachment','icon_small.png').'"></a>';
	}
    // BODY //////////////////////////////////////////////////////////////////////////////////////////////////////
    public function body($def_order=array(), $crits=array(), $cols=array(), $filters_set=array()) {    	
        unset($_SESSION['client']['recordbrowser']['admin_access']);
        if ($this->check_for_jump()) return;
        $this->fullscreen_table=true;
        $this->jump_to_new_record = true;
        if ($this->getRecordset()->getUserAccess('browse') === false) {
            print(__('You are not authorised to browse this data.'));
            return;
        }
        Base_HelpCommon::screen_name('browse_'.$this->getTab());
        if ($this->modeEnabled('watchdog')) {
        	Utils_WatchdogCommon::add_actionbar_change_subscription_button($this->getTab());
        }
        $this->is_on_main_page = true;

        $filters = '';
        if (!$this->disabled['filters']) {
        	$filters = $this->show_filters($filters_set);
        }

        if ($href = $this->getAddRecordHref(true)) {
        	Base_ActionBarCommon::add('add',__('New'), $href);
        }

        $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $crits);
        
        $theme = $this->init_module(Base_Theme::module_name());
        $theme->assign('filters', $filters);
        
        $this->addBrowseModeSelector($theme);      
        
        ob_start();
        $this->show_data($this->crits, $cols, array_merge($def_order, $this->default_order));
        $table = ob_get_clean();

        $theme->assign('table', $table);
        
        $theme->assign('caption', $this->getHeadline());
        
        $theme->assign('icon', $this->getIcon());
        $theme->display('Browsing_records');
    }
    
    protected function addBrowseModeSelector($theme) {
    	if (! $opts = Utils_RecordBrowser_BrowseMode_Controller::getSelectList($this->getRecordset(), $this->disabled['browse_mode'])) {
    		return;
    	}

    	if ($this->getGenericBrowser()->show_all()) {
    		$this->set_module_variable('browse_mode', '__ALL__');
    	}
    	
    	if (! $this->disabled['search'] && $this->getGenericBrowser()->get_module_variable('search')) {
   			$this->set_module_variable('browse_mode', '__ALL__');
    	}

    	$browse_mode = $this->getBrowseModeController()->getKey();

		$form = $this->init_module(Libs_QuickForm::module_name());
		$form->addElement('select', 'browse_mode', '', $opts, [
				'onchange' => $form->get_submit_form_js()
		]);
		$form->setDefaults(compact('browse_mode'));
		
		if ($form->validate()) {
			$vals = $form->exportValues();
			if (isset($opts[$vals['browse_mode']])) {
				$this->setBrowseMode($vals['browse_mode']);
				location([]);
				return;
			}
		}
		
		$form->assign_theme('form', $theme);
	}
    
	public function getBrowseModeController() {
		if ($this->browseModeController) return $this->browseModeController;
		
		$key = $this->get_module_variable('browse_mode', $this->getDefaultBrowseMode());
		
		return $this->browseModeController = Utils_RecordBrowser_BrowseMode_Controller::getController($key);
	}
	
	/**
	 * @deprecated use setBrowseMode
	 */
	public function switch_view($browseMode) {
		return $this->setBrowseMode($browseMode);
	}
	
	public function setBrowseMode($browseMode) {
		$this->setDefaultBrowseMode($browseMode);
        $this->changed_view = true;
        $this->set_module_variable('browse_mode', $browseMode);
    }
    
    public function getDefaultBrowseMode() {
    	return Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(), $this->getTab() . '_default_view')?: '__ALL__';
    }
    
    public function setDefaultBrowseMode($browseMode) {
    	Base_User_SettingsCommon::save(Utils_RecordBrowser::module_name(), $this->getTab() . '_default_view', $browseMode);
    }

    //////////////////////////////////////////////////////////////////////////////////////////
    public function show_filters($filters_set = array(), $f_id='') {
    	$filter_module = $this->init_module(Utils_RecordBrowser_Filters::module_name(), array($this, $this->filter_crits, $this->custom_filters), $this->getTab() . 'filters');
    	
    	$ret = $filter_module->get_filters_html($this->getGenericBrowser()->show_all(), $filters_set, $f_id);

    	$this->crits = $filter_module->get_crits();
    	
    	return $ret;
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function navigate($func){
        $args = func_get_args();
        array_shift($args);
        Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),$func,$args,array(self::$clone_result!==null?self::$clone_tab:$this->getTab()),md5($this->get_path()).'_r');
        $this->navigationExecuted = true;
        return false;
    }
    public function back(){
    	Base_BoxCommon::pop_main();
    }

    public function displayTable($crits = [], $options = []) {
    	$this->help('RecordBrowser', 'main');
    	
    	if ($this->check_for_jump()) return;
    	
    	$this->setAction('browse');
    	
    	$options = array_merge([
    			'order' => [],
    			'limit' => null,
    			'admin' => false,
    			'nolink' => $this->getNolink(),
    	], $options);
    	
    	if (! $this->getRecordset()->getUserAccess('browse', $options['admin'])) {
    		print(__('You are not authorised to access this data.'));
    		return;
    	}
    	
    	if (! $tableColumns = $this->getTableColumns()) {
    		print('Invalid view, no columns to display');
    		return;
    	}
    	
    	//TODO: Georgi Hristov - move this script to default script
    	if ($this->isGridEditEnabled()) load_js('modules/Utils/RecordBrowser/grid.js');
    	
    	$gb = $this->getGenericBrowser();
    	
    	
    	//callback to set specifics of Generic Browser
    	//     	call_user_func($callback, $gb);

    	//buttons
    	//browse modes
    	
    	$gb->set_table_columns( $tableColumns );    	
    	
    	$gb->set_custom_label($this->getTableCustomLabel());

    	$columnAccess = array_fill(0, count($tableColumns), false);
    	
    	/**
    	 * @var Utils_RecordBrowser_Recordset_Record $record
    	 */
    	foreach ($this->getTableRecords($crits, $options) as $record) {
    		$record->process('browse');
    		
    		self::$access_override['id'] = $record['id'];

    		$rowData = [];

    		foreach($tableColumns as $k => $desc) {
    			$value = call_user_func_array($desc['cell_callback'], [$record, $desc, $options]);

    			if ($value === false) {
    				$rowData[] = '';
    				continue;
    			}
    			
    			$columnAccess[$k] = true;
    			
    			$value = is_array($value)? $value: compact('value');
    			
    			if (! $this->getRowsExpandable()) {
    				$value['overflow_box'] = false;
    			}
    			
    			if ($this->getAbsolute()) {    				
    				$value['attrs'] = ($value['attrs']?? '') . ' style="border:1px solid black;"';
    				$value['value'] = '&nbsp;' . $value['value'] . '&nbsp;';
    			}
    			
    			$rowData[] = $value;
    		}
    		
    		$gb_row = $gb->get_new_row();
    		
    		$gb_row->add_data_array($rowData);
    		
    		$this->addTableRowActions($record, $gb_row, $options);
    	}
    	
    	$this->addInTableRow();

    	if (! $this->addInTableEnabled() && $this->getRecordsCount()) {
    		foreach ($columnAccess as $k => $access) {
    			if ($access) continue;
    			
    			$gb->set_column_display($k, false);
    		}
    	}
    	
    	if ($this->getAbsolute()) {
    		$gb->absolute_width(true);
    	}
    	
    	$args = [];
    	if ($template = $this->getTableTemplate()) {
    		//Base_ThemeCommon::get_template_filename('Utils_GenericBrowser','pdf')
    		$args = [$template];
    	}
    	
    	$this->display_module($gb, $args);
    }
    
    protected function getTableCrits($defaultCrits) {
    	return Utils_RecordBrowser_Crits::and($defaultCrits, $this->getSearchCrits());
    }
    
    protected function getTableOrder($defaultOrder) {
    	if (! $this->getBrowseModeController()->getOrder()) {
    		$tableFields = $this->getTableFields();
    		
    		$cleanOrder = array();
    		foreach ($defaultOrder as $k => $v) {
    			if ($k[0] == ':') {
    				$cleanOrder[$k] = $v;
    				continue;
    			}
    			
    			if (! $field = $tableFields[$k]?? []) continue;
    			
    			$cleanOrder[_V($field['name'])] = $v;
    		}
    		
    		$this->getGenericBrowser()->set_default_order($cleanOrder, $this->changed_view);
    	}    	
    	
    	return $this->getGenericBrowser()->get_order();
    }
    
    protected function setTableOptions($crits, $options) {
    	$this->set_module_variable('crits_stuff', $crits?: []);
    	$this->set_module_variable('order_stuff', $options['order']?? []);
    	
    	$options = array_merge([
    			'tab' => $this->getTab(),
    			'crits' => $crits,
    			'cols' => $this->getCustomColumns(),
    	], $options);
    	
    	$key = md5(serialize($options));
    	
    	$_SESSION['client']['utils_recordbrowser'][$key] = $options;
    }
    
    protected function setAction($modeOrAction) {
    	$map = [
    			'browse' => _M('Browse'),
    			'add' => _M('New record'),
    			'edit' => _M('Edit record'),
    			'view' => _M('View record'),
    			'history' => _M('Record history view')
    	];
    	
    	$this->action = $map[$modeOrAction]?? $modeOrAction;
    	
    	return $this;
    }
    
    protected function getAction() {
    	return _V($this->action);
    }
    
    public static function getRecordListOptions($key) {
    	return $_SESSION['client']['utils_recordbrowser'][$key]?? [];
    }
    
    protected function getSearchCrits() {
    	$gb = $this->getGenericBrowser();
    	
    	$search = $gb->get_search_query(true);

    	$ret = is_callable($this->searchCritsCallback)? call_user_func($this->searchCritsCallback, $search): [];

    	if ($gb->is_adv_search_on()) {
    		foreach ($search as $k => $v) {
    			$field = $this->getRecordset()->getField($k);

    			$v = explode(' ', is_array($v)? $v[0]: $v);
    			foreach ($v as $w) {
    				if ($w === '') continue;
    				
    				$ret = Utils_RecordBrowser_Crits::or($ret, $field->getSearchCrits($w));
    			}
    		}
    	} else {
    		$search_var = $gb->get_module_variable('search');
    		$search_words = explode(' ', $search_var['__keyword__']?? '');
    		foreach ($search_words as $word) {
    			if ($word === '') continue;
    			$search_part = Utils_RecordBrowser_Crits::create();
    			foreach ($search as $search_col => $search_col_val) {
    				$field =  $this->getRecordset()->getField($search_col);
    				
    				$search_part = Utils_RecordBrowser_Crits::or($search_part, $field->getSearchCrits($word));
    			}
    			
    			$ret = Utils_RecordBrowser_Crits::and($ret, $search_part);
    		}
    	}
		
		// add quickjump if no other search crits
		if (! $ret && $gb->get_module_variable('quickjump') && $gb->get_module_variable('quickjump_to')) {
			$ret = Utils_RecordBrowser_Crits::create([
					'"~' . $gb->get_module_variable('quickjump') => DB::qstr($gb->get_module_variable('quickjump_to') . '%')
			]);
		}

    	return $ret;
    }
    
    protected function getTableRecords($crits = [], $options = []) {
    	$options = array_merge([
    			'limit' => null,
    			'order' => []
    	], $options);
    	
    	$options['order'] = $this->getTableOrder($options['order']);
    	
    	$crits = $this->getTableCrits($crits);
    	
    	$this->setRecordsCount($this->getRecordset()->count($crits, $options));
    	
    	$this->setTableOptions($crits, $options);
    	
    	$gb = $this->getGenericBrowser();
    	
    	if (! $this->disabled['pagination'] && is_null($options['limit'])) {
    		$options['limit'] = $gb->get_limit($this->getRecordsCount());
    	}
    	
    	$last_offset = $this->get_module_variable('last_offset');

    	while (! $records = $this->getRecordset()->find($crits, $options)) {
    		$limit = $options['limit'];
    		
    		if ($last_offset > $limit['offset'] && ($limit['offset'] - $limit['numrows'])>=0) {
    			$limit['offset'] -= $limit['numrows'];
    		}
    		elseif ($limit['offset'] + $limit['numrows'] < $this->getRecordsCount()) {
    			$limit['offset'] += $limit['numrows'];
    		}
    		else break;
    			
    		$gb->set_module_variable('offset', $limit['offset']);
    		
    		$options['limit'] = $gb->get_limit($this->getRecordsCount());
    	}
    	
    	$this->set_module_variable('last_offset', $limit['offset']);

    	return $records;
    }
    
    protected function getTableCustomLabel() {
    	$custom_label = '';
    	if ($href = $this->getAddRecordHref()) {
    		$custom_label = '<a '.$href.'><span class="record_browser_add_new" '.Utils_TooltipCommon::open_tag_attrs(__('Add new record')).'><img src="'.Base_ThemeCommon::get_template_file(Utils_RecordBrowser::module_name(), 'add.png').'" /><div class="add_new">'.__('Add new').'</div></span></a>';
    	}
    	
    	if ($this->more_add_button_stuff) {
    		$custom_label = $custom_label? '<table><tr><td>'.$custom_label.'</td><td>'.$this->more_add_button_stuff.'</td></tr></table>': $this->more_add_button_stuff;
    	}
    	
    	return $custom_label;
    }
    
    protected function getAddRecordHref($createShortcut = false) {
    	if ($this->add_button === false) return;
    	
    	$href = false;
    	if ($this->multipleDefaults) {
    		$href = Utils_RecordBrowserCommon::create_new_record_href($this->getTab(), $this->multipleDefaults, 'multi', true, true);
    	}
    	else {
    		if ($this->add_button === null) {
    			if (!$this->get_access('add', $this->customDefaults)) return;
    			
    			$args = ['view_entry', 'add', null, $this->customDefaults];
    			$href = $this->create_callback_href([$this, 'navigate'], $args);
    			
    			if ($createShortcut) {
    				Utils_ShortcutCommon::add(['Ctrl', 'N'], 'function(){' . $this->create_callback_href_js([$this, 'navigate'], $args) . '}');
    			}
    		} elseif ($this->add_button !== '') {
    			$href = $this->add_button;
    		}
    	}
    	
    	return $href;
    }
    
    protected function addTableRowActions(Utils_RecordBrowser_Recordset_Record $record, Utils_GenericBrowser_RowObject $gb_row, $options = []) {
    	if ($this->disabled['actions'] === true) return;
    	
		$disabledActions = is_array($this->disabled['actions'])? array_flip($this->disabled['actions']): [];
		
		$options = array_merge([
				'admin' => false,
		], $options);

		if (! isset($disabledActions['view'])) {
			$gb_row->add_action($this->create_callback_href([$this,	'navigate'], ['view_entry', 'view', $record['id']]), __('View'), null, 'view');
		}
		
		if (! isset($disabledActions['edit'])) {			
			if ($record->getUserAccess('edit', $options['admin'])) {
				$gb_row->add_action($this->create_callback_href([$this, 'navigate'], ['view_entry', 'edit', $record['id']]), __('Edit'), null, 'edit');
			}
			else {
				$gb_row->add_action('', __('Edit'), __('You don\'t have permission to edit this record.'), 'edit', 0, true);
			}
		}
		
		if ($options['admin']) {
			if ($record[':active']) {
				$gb_row->add_action($this->create_callback_href([$this, 'set_active'], [$record['id'], false]), __('Deactivate'), null, 'active-on');
			}
			else {				
				$gb_row->add_action($this->create_callback_href([$this, 'set_active'], [$record['id'], true]), __('Activate'), null, 'active-off');
			}
			
			$info = $record->getInfo();
			
			if ($info['edited_on']) {
				$gb_row->add_action($this->create_callback_href([$this, 'navigate'], ['view_edit_history', $record['id']]), __('View edit history'), null, 'history');
			}
			else {
				$gb_row->add_action('', __('This record was never edited'), null, 'history_inactive');
			}
		}
		else {
			if (! isset($disabledActions['delete'])) {
				if ($record->getUserAccess('delete', $options['admin'])) {
					$gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this record?'), [$this, 'delete_record'], [$record['id'], false]), __('Delete'), null, 'delete');
				}
				else {
					$gb_row->add_action('', __('Delete'), __('You don\'t have permission to delete this record'), 'delete', 0, true);
				}
			}
		}
		
		if (! isset($disabledActions['info'])) {
			$gb_row->add_info($this->getBrowseModeController()->getRecordInfo($record) . Utils_RecordBrowserCommon::get_html_record_info($this->getTab(), $info ?? $record['id']));
		}

		$this->call_additional_actions_methods($record, $gb_row);
	}
	
	protected function addInTableRow() {
		if (! $this->addInTableEnabled() || ! $this->getUserFieldAccess('view')) return;

		$form = $this->init_module(Libs_QuickForm::module_name(), null, 'add_in_table__' . $this->getTab());
		
		$tableFields = $this->getTableFields();
		
		$result = $this->createQFfields($form, 'add', $tableFields, true);  
		
		if ($form->isSubmitted()) {
			$this->set_module_variable('force_add_in_table_after_submit', true);
			
			if (is_numeric($result)) {
				location([]);
				return;
			}
			else {
				$this->show_add_in_table = true;
			}
		}
		
		$form->addElement('submit', 'submit_qanr', __('Save'), [
				'style' => 'width:100%;height:19px;',
				'class' => 'button'
		]);
		
		$renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
		$form->accept($renderer);
		$data = $renderer->toArray();
			
		$gb = $this->getGenericBrowser();
			
		$gb->set_prefix($data['javascript'] . '<form ' . $data['attributes'] . '>' . $data['hidden'] . "\n");
			
		$gb->set_postfix("</form>\n");

		$row_data = [];
		$first = true;
		foreach ( $tableFields as $k => $v ) {
			if (isset($data[$k])) {				
				if ($first) eval_js('focus_on_field = "' . $k . '";');
				$first = false;
			}
				
			$row_data[] = isset($data[$k])? [
					'value' => $data[$k]['error'] . $data[$k]['html'],
					'overflow_box' => false
			]: '&nbsp;';
		}

		$gb_row = $gb->get_new_row();
		$gb_row->add_action('', $data['submit_qanr']['html'], '', null, 0, false, 7);
		$gb_row->set_attrs('id="add_in_table_row" style="display:' . ($this->show_add_in_table ? '': 'none') . ';"');
		$gb_row->add_data_array($row_data);
	}
   
    //////////////////////////////////////////////////////////////////////////////////////////
    public function show_data($crits = [], $cols = [], $order = [], $admin = false, $special = false, $pdf = false, $limit = null) {
    	$browseModeColumns = Utils_RecordBrowser_BrowseMode_Controller::getColumns($this->getRecordset(), $pdf || $admin?: $this->disabled['browse_mode']);
    	
    	//hide columns as per $cols
    	$this->setCustomColumns(array_merge($browseModeColumns, $this->getCustomColumns(), $cols));
    	
    	$crits = Utils_RecordBrowser_Crits::and($crits, $this->getBrowseModeController()->getCrits());
    	
    	return $this->displayTable($crits, compact('order', 'limit', 'admin'));
    }
        
    /**
     * @return Utils_GenericBrowser
     */
    public function getGenericBrowser() {
    	if ($this->genericBrowser) return $this->genericBrowser;
    	
    	return $this->genericBrowser = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->getTab());
    }
    
    /**
     * @param boolean $arg
     * @deprecated use enableGridEdit
     */
    public function enable_grid($arg) {
    	return $this->enableGridEdit($arg);
    }
    
    public function enableGridEdit($enable = true) {
    	$this->grid = $enable;
    }
    
    public function isGridEditEnabled() {
    	if (isset($this->grid)) return $this->grid;
    	
    	return $this->grid = Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(), 'grid');
    }
    
    protected function getTableColumns() {
    	if ($this->tableColumns) return $this->tableColumns;
    	
    	$quickjump = !$this->disabled['quickjump']? $this->getRecordset()->getProperty('quickjump'): '';
    	
    	$customColumns = $this->getCustomColumns();
    	
    	$this->tableFields = [];
    	$fieldColumns = [];
    	foreach($this->getRecordset()->getFields() as $field) {
    		$disabled = [
    				'order' => $this->disabled['order'] || $this->force_order || $this->getBrowseModeController()->getOrder(),
    				'quickjump' => $this->disabled['quickjump'] || !$quickjump || $field['name'] !== $quickjump,
    				'search' => $this->disabled['search']
    		];
    		
    		$column = array_merge($field->getGridColumnOptions($this, $disabled), $customColumns[$field['id']]?? []);
    		
    		unset($customColumns[$field['id']]);

    		if (!$column['visible']) continue;

    		$this->tableFields[$field['id']] = $field;

    		if ($this->getAbsolute()) {
    			$column['attrs'] = 'style="border:1px solid black;font-weight:bold;text-align:center;color:white;background-color:gray"';
    			$column['width'] = $column['width']?? 100;
    			if ($column['width'] == 1) $column['width'] = 100;
    		}
    		 
    		$fieldColumns[$field['id']] = $column;
    	}

    	$ret = array_merge($customColumns, $fieldColumns);
    	
    	uasort($ret, function ($col1, $col2) {
    		$pos1 = $col1['position']?? 0;
    		$pos2 = $col2['position']?? 0;
    		
    		return $pos1 > $pos2;
    	});
    	
    	if ($this->getAbsolute()) {
			$max = 0;
			$width_sum = 0;
			foreach ( $ret as $k => $v ) {
				if ($v['width'] > $max) $max = $v['width'];
			}

			foreach ( $ret as $k => $v ) {
				$ret[$k]['width'] = intval($ret[$k]['width']);
				if ($ret[$k]['width'] < $max / 2) $ret[$k]['width'] = $max / 2;
				$width_sum += $ret[$k]['width'];
			}
			$fraction = 0;
			foreach ( $ret as $k => $v ) {
				$ret[$k]['width'] = floor(100 * $v['width'] / $width_sum);
				$fraction += 100 * $v['width'] / $width_sum - $ret[$k]['width'];
				if ($fraction > 1) {
					$ret[$k]['width'] += 1;
					$fraction -= 1;
				}
				$ret[$k]['width'] = $ret[$k]['width'] . '%';
			}
		}

		return $this->tableColumns = array_values($ret);
    }
    
    protected function getTableFields() {
    	if (!isset($this->tableFields)) {
    		$this->getTableColumns();
    	}
    	
    	return $this->tableFields;
    }
        
    public function addInTableEnabled() {
    	return $this->addInTable;
    }
    
    protected function setAddInTable($enabled = true) {
    	$this->addInTable = $enabled;
    }
    
    //////////////////////////////////////////////////////////////////////////////////////////
    public function delete_record($id, $pop_main = true) {
        Utils_RecordBrowserCommon::delete_record($this->getTab(), $id);
        if ($pop_main) {
            return $this->back();
        }
    }
    public function clone_record($id) {
        if (self::$clone_result!==null) {
            if (is_numeric(self::$clone_result)) {
                Utils_RecordBrowserCommon::record_processing($this->getTab(), self::$clone_result, 'cloned', $id);
                Utils_RecordBrowserCommon::new_record_history($this->getTab(),self::$clone_result,'CLONED '.$id);
                $this->navigate('view_entry', 'view', self::$clone_result);
            }
            self::$clone_result = null;
            return false;
        }
        $record = Utils_RecordBrowserCommon::get_record($this->getTab(), $id, false);
        $access = $this->get_access('view',$record);
        if (is_array($access))
            foreach ($access as $k=>$v)
                if (!$v) unset($record[$k]);
		$record = Utils_RecordBrowserCommon::record_processing($this->getTab(), $record, 'cloning', $id);
		unset($record['id']);
        $this->navigate('view_entry', 'add', null, $record);
        return true;
    }
    public function view_entry_with_REQUEST($mode='view', $id = null, $defaults = array(), $show_actions=true, $request=array()) {
        foreach ($request as $k=>$v)
            $_REQUEST[$k] = $v;
        if(isset($_REQUEST['switch_to_addon']))
	        $this->switch_to_addon = $this->get_module_variable('switch_to_addon',$_REQUEST['switch_to_addon']);
        return $this->view_entry($mode, $id, $defaults, $show_actions);
    }
    public function view_entry($mode='view', $id = null, $defaults = array(), $show_actions=true) {
		Base_HelpCommon::screen_name('rb_'.$mode.'_'.$this->getTab());
        if (isset($_SESSION['client']['recordbrowser']['admin_access'])) Utils_RecordBrowserCommon::$admin_access = true;
        self::$mode = $mode;
        if ($this->navigationExecuted) {
            $this->navigationExecuted = false;
            return true;
        }
        if ($this->check_for_jump()) return;
        $theme = $this->init_module(Base_Theme::module_name());
        if ($this->isset_module_variable('id')) {
            $id = $this->get_module_variable('id');
            $this->unset_module_variable('id');
        }
        self::$browsed_records = null;

        $js = ($mode!='view');
        $time = microtime(true);
        if ($this->is_back()) {
            self::$clone_result = 'canceled';
            return $this->back();
        }

		if (is_numeric($id)) {
	        $id = intVal($id);
	    	self::$last_record = $this->record = $this->getRecordset()->findOne($id, ['asHtml' => $mode!=='edit']);
		} else {
			self::$last_record = $this->record = $id;
			$id = isset($this->record['id'])? intVal($this->record['id']): null;
		}
		if ($id===0) $id = null;

        if($mode == 'add') {
        	$this->customDefaults = $this->getRecordset()->getDefaultValues(array_merge($this->customDefaults, $defaults));
		}

		$viewAccess = $this->getUserFieldAccess('view');
		$modeAccess = $mode == 'view'? $viewAccess: $this->getUserFieldAccess($mode);

		if (!$modeAccess || !$viewAccess) {
			if ($mode == 'add') {
				print (!$modeAccess ?
						__('You don\'t have permission to perform this action.')
						: __('You can\'t see any of the records fields.'));
			}
			else {
				print(__('You don\'t have permission to view this record.'));
			}
			
			if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back']))) {
				Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
			}
			return true;
		}
		
		//TODO: Georgi Hristov mode this to the processing method
        if($mode == 'add' || $mode == 'edit') {
            $theme -> assign('click2fill', '<div id="c2fBox"></div>');
            load_js('modules/Utils/RecordBrowser/click2fill.js');
            eval_js('initc2f("'.__('Scan/Edit').'","'.__('Paste data here with Ctrl-v, click button below, then click on separated words in specific order and click in text field where you want put those words. They will replace text in that field.').'")');
            Base_ActionBarCommon::add('clone', __('Click 2 Fill'), 'href="javascript:void(0)" onclick="c2f()"');
        }

//        if ($mode!='add' && !$this->record[':active'] && !Base_AclCommon::i_am_admin()) return $this->back();

        $tb = $this->init_module(Utils_TabbedBrowser::module_name(), null, 'recordbrowser_addons/'.$this->getTab().'/'.$id);
		if ($mode=='history') $tb->set_inline_display();
        self::$tab_param = $tb->get_path();

        $this->form = $form = $this->init_module(Libs_QuickForm::module_name(),null, $mode.'/'.$this->getTab().'/'.$id);
        if(Base_User_SettingsCommon::get($this->get_type(), 'confirm_leave') && ($mode == 'add' || $mode == 'edit'))
        	$form->set_confirm_leave_page();

        $result = $this->createQFfields($form, $mode);    
            
        if (is_numeric($result)) {
        	if ($mode == 'add') {
        		self::$clone_result = $result;
        		self::$clone_tab = $this->getTab();
        	}
        	
        	return $this->back();
        }

        $this->setAction($mode);
        
        if ($mode == 'edit') {
            $this->set_module_variable('edit_start_time', $time);
        }

        if ($show_actions !== false) {
            if ($mode=='view') {
                if ($this->get_access('edit',$this->record)) {
                    Base_ActionBarCommon::add('edit', __('Edit'), $this->create_callback_href([$this,'navigate'], ['view_entry','edit',$id]));
                    Utils_ShortcutCommon::add(['Ctrl','E'], 'function(){'.$this->create_callback_href_js([$this,'navigate'], ['view_entry','edit',$id]).'}');
                }
                if ($this->get_access('delete',$this->record)) {
                    Base_ActionBarCommon::add('delete', __('Delete'), $this->create_confirm_callback_href(__('Are you sure you want to delete this record?'),array($this,'delete_record'),array($id)));
                }
                if ($this->get_access('add',$this->record)) {
                    Base_ActionBarCommon::add('clone',__('Clone'), $this->create_confirm_callback_href(__('You are about to create a copy of this record. Do you want to continue?'),array($this,'clone_record'),array($id)));
                }
                if($this->get_access('print',$this->record)) {
                    /** @var Base_Print_Printer $printer */
                	if ($printer = $this->getRecordset()->getPrinter()) {
                        Base_ActionBarCommon::add('print', __('Print'), $printer->get_href(array('tab' => $this->getTab(), 'record_id' => $this->record['id'])));
                    }
                }
                if ($show_actions===true || (is_array($show_actions) && (!isset($show_actions['back']) || $show_actions['back'])))
                    Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
                
            } elseif($mode!='history') {
            	Utils_ShortcutCommon::add(['Ctrl','S'], 'function(){'.$form->get_submit_form_js().'}');
                
            	Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
                Base_ActionBarCommon::add('delete', __('Cancel'), $this->create_back_href());
            }
            //Utils_ShortcutCommon::add(array('esc'), 'function(){'.$this->create_back_href_js().'}');
        }

        if ($mode!='add') {
            $theme -> assign('info_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(Utils_RecordBrowserCommon::get_html_record_info($this->getTab(), $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','info.png').'" /></a>');

			if ($mode!='history') {
				//TODO: move to BrowseMode controller
				if ($this->modeEnabled('favorites'))
					$theme -> assign('fav_tooltip', Utils_RecordBrowserCommon::get_fav_button($this->getTab(), $id));
					if ($this->modeEnabled('watchdog'))
					$theme -> assign('subscription_tooltip', Utils_WatchdogCommon::get_change_subscription_icon($this->getTab(), $id));
				if ($this->full_history) {
					$info = Utils_RecordBrowserCommon::get_record_info($this->getTab(), $id);
					if ($info['edited_on']===null) $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(__('This record was never edited')).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history_inactive.png').'" /></a>');
					else $theme -> assign('history_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(__('Click to view edit history of currently displayed record')).' '.$this->create_callback_href(array($this,'navigate'), array('view_edit_history', $id)).'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','history.png').'" /></a>');
				}
				if ($this->clipboard_pattern) {
					$theme -> assign('clipboard_tooltip', '<a '.Utils_TooltipCommon::open_tag_attrs(__('Click to export values to copy')).' '.Libs_LeightboxCommon::get_open_href('clipboard').'><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','clipboard.png').'" /></a>');
					$record = Utils_RecordBrowserCommon::get_record($this->tab, $id);
					/* for every field name store its value */
					$data = Utils_RecordBrowserCommon::get_record_vals($this->tab, $record, true, array_column($this->table_rows, 'id'));
					
					$text = Utils_RecordBrowserCommon::replace_clipboard_pattern($this->clipboard_pattern, array_filter($data));
					
					load_js($this->get_module_dir() . 'selecttext.js');
					/* remove all php new lines, replace <br>|<br/> to new lines and quote all special chars */
					$ftext = htmlspecialchars(preg_replace('#<[bB][rR]/?>#', "\n", str_replace("\n", '', $text)));
					$flash_copy = '<object width="60" height="20">'.
							'<param name="FlashVars" value="txtToCopy='.$ftext.'">'.
							'<param name="movie" value="'.$this->get_module_dir().'copyButton.swf">'.
							'<embed src="'.$this->get_module_dir().'copyButton.swf" flashvars="txtToCopy='.$ftext.'" width="60" height="20">'.
							'</embed>'.
							'</object>';
					$text = '<h3>'.__('Click Copy under the box or move mouse over box below to select text and hit Ctrl-c to copy it.').'</h3><div onmouseover="fnSelect(this)" style="border: 1px solid gray; margin: 15px; padding: 20px;">'.$text.'</div>'.$flash_copy;
					
					Libs_LeightboxCommon::display('clipboard',$text,__('Copy'));
				}
			}
        }

		if ($mode == 'view') {
			$dp = $this->getRecordset()->process($this->record, 'display');
			
			if ($dp && is_array($dp)) {
				foreach ($dp as $k => $v) $theme->assign($k, $v);
			}				
		}

        if ($mode=='view' || $mode=='history') $form->freeze();
        
        $renderer = new HTML_QuickForm_Renderer_TCMSArraySmarty();
        $form->accept($renderer);
        $data = $renderer->toArray();

        print ($data['javascript'].'<form '.$data['attributes'].'>'.$data['hidden']."\n");

        $last_page = DB::GetOne('SELECT MIN(position) FROM '.$this->getTab().'_field WHERE type = \'page_split\' AND field != \'General\'');
		if (!$last_page) $last_page = DB::GetOne('SELECT MAX(position) FROM '.$this->getTab().'_field')+1;
        $label = DB::GetRow('SELECT field, param FROM '.$this->getTab().'_field WHERE position=%s', array($last_page));
		if ($label) {
			$cols = $label['param'];
			$label = $label['field'];
		} else $cols = false;

        $this->view_entry_details(1, $last_page, $data, $theme, true);
        $result = DB::Execute('SELECT position, field, param FROM '.$this->getTab().'_field WHERE type = \'page_split\' AND position > %d ORDER BY position', array($last_page));
        $row = true;
        if ($mode=='view')
            print("</form>\n");
        $tab_counter = 0;
		$additional_tabs = 0;
		$default_tab = 0;
        while ($row) {
            $row = $result->FetchRow();
            if ($row) $pos = $row['position'];
            else $pos = DB::GetOne('SELECT MAX(position) FROM '.$this->getTab().'_field WHERE active=1')+1;

            $valid_page = false;
			$hide_page = ($mode=='view' && Base_User_SettingsCommon::get(Utils_RecordBrowser::module_name(),'hide_empty'));
            foreach($this->getRecordset()->getFields() as $args) {
                if (!isset($data[$args['id']]) || $data[$args['id']]['type']=='hidden') continue;
                if ($args['position'] >= $last_page && ($pos+1 == -1 || $args['position'] < $pos+1)) {
                    $valid_page = true;
					if ($hide_page && !$this->field_is_empty($this->record, $args['id'])) $hide_page = false;
                    break;
                }
            }
            if ($valid_page && $pos - $last_page>1 && !isset($this->hide_tab[$label])) {
                $translated_label = _V($label);
                $tb->set_tab($translated_label, array($this, 'view_entry_details'), array($last_page, $pos + 1, $data, null, false, $cols, _V($label)), $js); // TRSL
				if ($hide_page) {
					eval_js('$("'.$tb->get_tab_id(_V($label)).'").style.display="none";');
					if ($default_tab === $tab_counter) $default_tab = $tab_counter + 1;
				} else
					$additional_tabs++;

				$tab_counter++;
			}
            $cols = $row['param'];
            $last_page = $pos;
            if ($row) $label = $row['field'];
        }
		if ($default_tab!==null) $tb->set_default_tab($default_tab);
        if ($mode!='history') {
            $ret = DB::Execute('SELECT * FROM recordbrowser_addon WHERE tab=%s AND enabled=1 ORDER BY pos', array($this->getTab()));
            $addons_mod = array();
            while ($row = $ret->FetchRow()) {
                if (ModuleManager::is_installed($row['module'])==-1) continue;
                if (is_callable(explode('::',$row['label']))) {
                    $result = call_user_func(explode('::',$row['label']), $this->record, $this);
                    if (!isset($result['show'])) $result['show']=true;
					if (($mode=='add' || $mode=='edit') && (!isset($result['show_in_edit']) || !$result['show_in_edit'])) continue;
                    if ($result['show']==false) continue;
                    if (!isset($result['label'])) $result['label']='';
                    $row['label'] = $result['label'];
                    if (!isset($result['icon'])) $result['icon']='';
                    $row['icon'] = $result['icon'];
                } else {
					if ($mode=='add' || $mode=='edit') continue;
					$labels = explode('#',$row['label']);
					foreach($labels as $i=>$label) $labels[$i] = _V($label); // translate labels from database
					$row['label'] = implode('#',$labels);
				}
                $mod_id = md5(serialize($row));
				if (method_exists($row['module'].'Common',$row['func'].'_access') && !call_user_func(array($row['module'].'Common',$row['func'].'_access'), $this->record, $this)) continue;
                $addons_mod[$mod_id] = $this->init_module($row['module']);
                if (!method_exists($addons_mod[$mod_id],$row['func'])) $tb->set_tab($row['label'],array($this, 'broken_addon'), array(), $js);
                else {
                	$tb->set_tab($row['label'],array($this, 'display_module'), array(& $addons_mod[$mod_id], array($this->record, $this), $row['func']), $js);
                	if (isset($row['icon']) && $row['icon']) $tb->tab_icon($row['label'], $row['icon']);
                }                
                $tab_counter++;
            }
        }
        if ($additional_tabs==0 && ($mode=='add' || $mode=='edit' || $mode=='history'))
            print("</form>\n");
        $this->display_module($tb);
        $tb->tag();
		
		foreach ($this->fields_in_tabs as $label=>$fields) {
			$highlight = false;
			foreach ($fields as $f) {
				$err = $form->getElementError($f);
				if ($err) {
					$highlight = true;
					break;
				}
			}
			if ($highlight)
				$tb->tab_icon($label, Base_ThemeCommon::get_template_file('Utils_RecordBrowser','notify_error.png'));
		}
		
        if ($this->switch_to_addon) {
    	    $this->set_module_variable('switch_to_addon', false);
            $tb->switch_tab($this->switch_to_addon);
        }
        
        if ($additional_tabs!=0 && ($mode=='add' || $mode=='edit' || $mode=='history'))
            print("</form>\n");

        return true;
    } //view_entry
	
	public function getUserFieldAccess($mode) {
// 		if ($mode != 'add'/*  && !$this->record */) {
// 			return false;
// 		}
		
		$record = $this->record?? $this->customDefaults;
		
		if (Acl::i_am_admin()) {
			Utils_RecordBrowserCommon::$admin_access = true;
			$access = $this->getRecordset()->getUserValuesAccess($mode, $record, true);
		}
		else {
			$access = $this->getRecordset()->getUserValuesAccess($mode, $record);
		}
				
		return $access;
	}
	
	public function field_is_empty($r, $f) {
		if (is_array($r[$f])) return empty($r[$f]);
		return $r[$f]=='';
	}

    public function broken_addon(){
        print('Addon is broken, please contact system administrator.');
    }

    public function view_entry_details($from, $to, $form_data, $theme=null, $main_page = false, $cols = 2, $tab_label = null){
        if ($theme==null) $theme = $this->init_module(Base_Theme::module_name());
        $fields = array();
        $longfields = array();

        foreach($this->getRecordset()->getFields() as $desc) {
            if (!isset($form_data[$desc['id']]) || $form_data[$desc['id']]['type']=='hidden') continue;
            if ($desc['position'] >= $from && ($to == -1 || $desc['position'] < $to)) {
				if ($tab_label) $this->fields_in_tabs[$tab_label][] = $desc['id'];
                
				$opts = $this->get_field_display_options($desc, $form_data);
				
				if (!$opts) continue;
				
                if ($desc['type']<>'long text') $fields[$desc['id']] = $opts; else $longfields[$desc['id']] = $opts;
            }
        }
        if ($cols==0) $cols=2;
        $theme->assign('fields', $fields);
        $theme->assign('cols', $cols);
        $theme->assign('longfields', $longfields);
        $theme->assign('action', self::$mode=='history'?'view':self::$mode);
        $theme->assign('form_data', $form_data);
        $theme->assign('required_note', __('Indicates required fields.'));

        $theme->assign('caption',_V($this->caption) . $this->get_jump_to_id_button());
        $theme->assign('icon',$this->getIcon());

        $theme->assign('main_page',$main_page);

        if ($main_page) {
            $tpl = DB::GetOne('SELECT tpl FROM recordbrowser_table_properties WHERE tab=%s', array($this->getTab()));
            $theme->assign('raw_data',$this->record);
        } else {
            $tpl = '';
            if (self::$mode=='view') print('<form>');
        }
		if ($tpl) Base_ThemeCommon::load_css('Utils_RecordBrowser','View_entry');
        $theme->display(($tpl!=='')?$tpl:'View_entry', ($tpl!==''));
        if (!$main_page && self::$mode=='view') print('</form>');
    }
    
    public function get_field_display_options($desc, $form_data = array()) {
    	/** @var Base_Theme $ftheme */
    	static $ftheme;
    	
    	$field_form_data = isset($form_data[$desc['id']])? $form_data[$desc['id']]: array();

    	$default_field_form_data = array('label'=>'', 'html'=>'', 'error'=>null, 'frozen'=>false);
    	$field_form_data = array_merge($default_field_form_data, $field_form_data);
    	
    	$help = isset($desc['help']) && $desc['help']? array(
    			'icon' => Base_ThemeCommon::get_icon('info'), 
    			'text' => Utils_TooltipCommon::open_tag_attrs(_V($desc['help']), false))
    		: false;
    	
    	$ret = array('label'=>$field_form_data['label'],
    			'element'=>$desc['id'],
    			'advanced'=>$this->advanced[$desc['id']]?? '',
    			'html'=>$field_form_data['html'],
    			'style'=>$desc['style'].($field_form_data['frozen']?' frozen':''),
    			'error'=>$field_form_data['error'],
    			'required'=>isset($desc['required'])? $desc['required']: null,
    			'type'=>$desc['type'],
    			'help' => $help);
    	
    	if (!$ftheme)
    		$ftheme = $this->init_module(Base_Theme::module_name());

    	$ftheme->assign('f', $ret);
    	$ftheme->assign('form_data', $form_data);
    	$ftheme->assign('action', self::$mode);
    	
    	$default_field_template = self::module_name() . '/single_field';
    	
    	$field_template = $desc['template']?: $default_field_template;    	
    	$field_template = is_callable($field_template)? call_user_func($field_template, $desc['id'], self::$mode): $field_template;
    	
    	if (!$field_template) return false;
    	
    	$ret['full_field'] = $ftheme->get_html($field_template, true);

    	return $ret;
    }

    public function check_new_record_access($data) {
		$ret = array();
        if (is_array(Utils_RecordBrowser::$last_record))
		    foreach (Utils_RecordBrowser::$last_record as $k=>$v) if (!isset($data[$k])) $data[$k] = $v;
		$access = Utils_RecordBrowser_Access::create($this->getTab(),'add');
		if ($access->isFullGrant()) return [];
		if ($access->isFullDeny()) {
			$fields = array_keys($data);
			$first_field = reset($fields);
			return array($first_field=>__('Access denied'));
		}
        $required_crits = array();
		foreach($access->getRuleCrits() as $crits) {
		    $problems = array();
            if (!Utils_RecordBrowserCommon::check_record_against_crits($this->getTab(), $data, $crits, $problems)) {
                foreach ($problems as $c) {
                    if ($c instanceof Utils_RecordBrowser_Recordset_Query_Crits_Single) {
                        list($f, $subf) = Utils_RecordBrowser_CritsSingle::parse_subfield($c->get_field());
                        $ret[$f] = __('Invalid value');
                    }
                }
                $required_crits[] = Utils_RecordBrowserCommon::crits_to_words($this->getTab(), $crits);
            }
            if($problems) continue;
            return array();
	   	}
    	if (!$required_crits) return array();
    	
        /** @var Base_Theme $th */
        $th = $this->init_module(Base_Theme::module_name());
        $th->assign('crits', $required_crits);
        $th->display('required_crits_to_add');
		return $ret;
    }

    protected function createQFfields($form, $mode, $forFields = null, $for_grid=false) {    	
    	$dp = $this->getRecordset()->process($mode == 'add'? $this->customDefaults: $this->record, $mode=='view' || $mode=='history'? 'view': $mode.'ing');
    	
    	if ($dp===false) return false;
    	
    	if (is_array($dp)) {
    		$defaults = $this->customDefaults = self::$last_record = $this->record = $dp;
    	}
    		
    	self::$last_record = self::$last_record?: $defaults;
    		
    	if ($mode == 'add') {
    		$form->addFormRule([$this, 'check_new_record_access']);
    		
    		$form->setDefaults($defaults);
    	}
    	
    	$record = $this->record;
    	
    	$viewAccess = $this->getUserFieldAccess('view');
    	$modeAccess = $mode == 'view'? $viewAccess: $this->getUserFieldAccess($mode);

		foreach ( $this->getRecordset()->getFields('processing_order') as $field ) {
			// check permissions
			if (!$this->checkFieldAccess($field['id'], $viewAccess)) continue;
			
			// check visible cols
			if ($forFields !== null && ! isset($forFields[$field['id']])) continue;
			
			// set default value to '' if not set at all
			$record[$field['id']] = $record[$field['id']] ?? '';
			
			if ($for_grid) {
				$nk = '__grid_' . $field['id'];
				$record[$nk] = $record[$field['id']];
				$field['id'] = $nk;
			}

			$field->createQFfield($form, $mode, $record, $this->customDefaults, $this);
			
			if (($mode==='edit' || $mode==='add') && ! $this->checkFieldAccess($field['id'], $modeAccess)) {
				$form->freeze($field['id']);
			}
		}
	
		if ($form->exportValue('submited') && $form->validate()) {
			$values = $form->exportValues();
			
			/**
			 * @var Utils_FileUpload_Dropzone $file_module
			 */
			foreach (Utils_FileUpload_Dropzone::get_registered_file_fields($form) as $file_field => $file_module) {
				$files = [];
				$uploaded_files = $file_module->get_uploaded_files();
				foreach ($uploaded_files['existing'] as $file) {
					if (isset($uploaded_files['delete'][$file['file_id']])) continue;
					$files[] = $file['file_id'];
				}
				foreach ($uploaded_files['add'] as $file) {
					$files[] = [
							'filename' => $file['name'],
							'file' => $file['file']
					];
				}
				$values[$file_field] = $files;
				$file_module->clear_uploaded_files();
			}
			
			foreach ($defaults as $k => $v) {
				if (!isset($values[$k]) && ! $this->checkFieldAccess($k, $viewAccess)) $values[$k] = $v;
				if (! $this->checkFieldAccess($k, $modeAccess)) $values[$k] = $v;
			}
			
			$values[':id'] = $this->record[':id']?? null;
			foreach ($this->customDefaults as $k => $v) {
				$values[$k] = $values[$k]?? $v;
			}

			$record = $this->getRecordset()->entry($values);
			
			$editStartTime = date('Y-m-d H:i:s', $this->get_module_variable('edit_start_time'));
			
			if (! $record->wasModified($editStartTime)) {
				return $record->save()->getId();
			}

			$this->dirty_read_changes($record->getId(), $editStartTime);
		}
		
		$form->add_error_closing_buttons();
	}
    
    protected function checkFieldAccess($fieldId, $access) {
    	return $access !== false && ($access[$fieldId]?? true);
    }
    
    public function update_record($id,$values) {
        Utils_RecordBrowserCommon::update_record($this->getTab(), $id, $values);
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    
    public function dirty_read_changes($id, $time_from) {
        print('<b>'.__('The following changes were applied to this record while you were editing it.').'<br/>'.__('Please revise this data and make sure to keep this record most accurate.').'</b><br>');
        $gb_cha = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->getTab().'__changes');
        $table_columns_changes = array( array('name'=>__('Date'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Username'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Field'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Old value'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('New value'), 'width'=>10, 'wrapmode'=>'nowrap'));
        $gb_cha->set_table_columns( $table_columns_changes );

        $created = Utils_RecordBrowserCommon::get_record($this->getTab(), $id, true);
        $field_hash = array();
        foreach($this->getRecordset()->getFields() as $field => $args)
            $field_hash[$args['id']] = $field;
        $ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->getTab().'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.edited_on>=%T AND c.'.$this->getTab().'_id=%d ORDER BY edited_on DESC',array($time_from,$id));
        while ($row = $ret->FetchRow()) {
            $changed = array();
            $ret2 = DB::Execute('SELECT * FROM '.$this->getTab().'_edit_history_data WHERE edit_id=%d',array($row['id']));
            while($row2 = $ret2->FetchRow()) {
                if (isset($changed[$row2['field']])) {
                    if (is_array($changed[$row2['field']]))
                        array_unshift($changed[$row2['field']], $row2['old_value']);
                    else
                        $changed[$row2['field']] = array($row2['old_value'], $changed[$row2['field']]);
                } else {
                    $changed[$row2['field']] = $row2['old_value'];
                }
                if (is_array($changed[$row2['field']]))
                    sort($changed[$row2['field']]);
            }
            foreach($changed as $k=>$v) {
                $new = $this->get_val($field_hash[$k], $created);
                $created[$k] = $v;
                $old = $this->get_val($field_hash[$k], $created);
                $gb_row = $gb_cha->get_new_row();
//              eval_js('apply_changes_to_'.$k.'=function(){element = document.getElementsByName(\''.$k.'\')[0].value=\''.$v.'\';};');
//              $gb_row->add_action('href="javascript:apply_changes_to_'.$k.'()"', 'Apply', null, 'apply');
                $gb_row->add_data(
                    Base_RegionalSettingsCommon::time2reg($row['edited_on']),
                    $row['edited_by']!==null?Base_UserCommon::get_user_label($row['edited_by']):'',
                    $field_hash[$k],
                    $old,
                    $new
                );
            }
        }
        $theme = $this->init_module(Base_Theme::module_name());
        $theme->assign('table',$this->get_html_of_module($gb_cha));
        $theme->assign('label',__('Recent Changes'));
        $theme->display('View_dirty_read');
    }
    public function view_edit_history($id){
        if ($this->is_back())
            return $this->back();

		$tb = $this->init_module('Utils_TabbedBrowser');		
        $gb_cha = $this->init_module(Utils_GenericBrowser::module_name(), null, $this->getTab().'__changes');
		$form = $this->init_module('Libs_QuickForm');

        $table_columns_changes = array( array('name'=>__('Date'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Username'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Field'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('Old value'), 'width'=>10, 'wrapmode'=>'nowrap'),
                                        array('name'=>__('New value'), 'width'=>10, 'wrapmode'=>'nowrap'));

        $gb_cha->set_table_columns( $table_columns_changes );

        $gb_cha->set_inline_display();

        $created = Utils_RecordBrowserCommon::get_record($this->getTab(), $id, true);
        $access = $this->get_access('view', $created);

        $edited = DB::GetRow('SELECT ul.login, c.edited_on FROM '.$this->getTab().'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->getTab().'_id=%d ORDER BY edited_on DESC',array($id));
        
        $ret = DB::Execute('SELECT ul.login, c.id, c.edited_on, c.edited_by FROM '.$this->getTab().'_edit_history AS c LEFT JOIN user_login AS ul ON ul.id=c.edited_by WHERE c.'.$this->getTab().'_id=%d ORDER BY edited_on DESC, id DESC',array($id));
		$dates_select = array();
		$tb_path = escapeJS($tb->get_path());
        while ($row = $ret->FetchRow()) {
			$user = Base_UserCommon::get_user_label($row['edited_by']);
			$date_and_time = Base_RegionalSettingsCommon::time2reg($row['edited_on']);
            $changed = array();
            $ret2 = DB::Execute('SELECT * FROM '.$this->getTab().'_edit_history_data WHERE edit_id=%d',array($row['id']));
            while($row2 = $ret2->FetchRow()) {
                if ($row2['field']!='id' && (!isset($access[$row2['field']]) || !$access[$row2['field']])) continue;
                $changed[$row2['field']] = $row2['old_value'];
                $last_row = $row2;
                $dates_select[$row['edited_on']] = $date_and_time;
            }
            foreach($changed as $k=>$v) {
                if ($k=='id') {
					$gb_cha->add_row(
						$date_and_time, 
						$user, 
						array('value'=>_V($last_row['old_value']), 'attrs'=>'colspan="3" style="text-align:center;font-weight:bold;"'),
						array('value'=>'', 'dummy'=>true),
						array('value'=>'', 'dummy'=>true)
					);
                } else {
                	if (!$field = $this->getRecordset()->getField($k, true)) continue;
                    
                    $new = $this->get_val($k, $created);                        
                    $created[$k] = $field->decodeValue($v);
                    $old = $this->get_val($k, $created);
					$gb_row = $gb_cha->get_new_row();
					$gb_row->add_action('href="javascript:void(0);" onclick="Utils_RecordBrowser.history.jump(\''.$row['edited_on'].'\',\''.$this->getTab().'\','.$created['id'].',\''.$form->get_name().'\');tabbed_browser_switch(1,2,null,\''.$tb_path.'\')"','View');
                    $gb_row->add_data(
				            $date_and_time,
				            $row['edited_by']!==null?$user:'',
		                    $field->getLabel(), // TRSL
				            $old,
				            $new
                   	);
                }
            }
        }

		$gb_row = $gb_cha->get_new_row();
		$gb_row->add_data(
			Base_RegionalSettingsCommon::time2reg($created['created_on']),
			$created['created_by']!==null?Base_UserCommon::get_user_label($created['created_by']):'',
			array('value'=>__('RECORD CREATED'), 'attrs'=>'colspan="3" style="text-align:center;font-weight:bold;"'),
			array('value'=>'', 'dummy'=>true),
			array('value'=>'', 'dummy'=>true)
		);


//		$tb->set_tab(__('Record historical view'), array($this, 'record_historical_view'), array($created, $access, $form, $dates_select), true);
		$tb->start_tab(__('Changes History'));
		$this->display_module($gb_cha);
		$tb->end_tab();

		$tb->start_tab(__('Record historical view'));
		$dates_select[$created['created_on']] = Base_RegionalSettingsCommon::time2reg($created['created_on']);
        foreach($this->getRecordset()->getFields() as $field => $args) {
            if (!$access[$args['id']]) continue;
            $val = $this->get_val($field, $created, false, $args);
        }
		$form->addElement('select', 'historical_view_pick_date', __('View the record as of'), $dates_select, array('onChange'=>'Utils_RecordBrowser.history.load("'.$this->getTab().'",'.$created['id'].',"'.$form->get_name().'");', 'id'=>'historical_view_pick_date'));
		$form->setDefaults(array('historical_view_pick_date'=>$created['created_on']));
		$form->display();
		$this->view_entry('history', $created);
		$tb->end_tab();

		
		$this->display_module($tb);
        Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
        return true;
    }
	
	public function record_historical_view($created, $access, $form, $dates_select) {
	}

    public function set_active($id, $state=true){
        Utils_RecordBrowserCommon::set_active($this->getTab(), $id, $state);
        return false;
    }
    /**
     * @param array $arg
     * @param boolean $multiple
     * @deprecated use setDefaults or setMultipleDefaults
     */
    public function set_defaults($arg, $multiple=false) {
    	return $multiple? $this->setMultipleDefaults($arg): $this->setDefaults($arg);
    }
    
    public function setDefaults($defaults) {
    	$this->customDefaults = array_merge($this->customDefaults, $defaults?: []);
    	
    	return $this;
    }
    
    public function setMultipleDefaults($multipleDefaults) {
    	$values = [];
    	foreach ($multipleDefaults as $label => $options) {
    		$options['label'] = $options['label']?? $label;
    		
    		$values[] = array_merge([
    				'icon' => '',
    				'defaults' => []
    		], $options);
    	}
    	
    	$this->multipleDefaults = $values;
    	
    	return $this;
    }
	public function crm_perspective_default() {
		return '__PERSPECTIVE__';
	}
    public function set_filters_defaults($arg, $merge = false, $overwrite = false) {
        if (!$overwrite && $this->isset_module_variable('def_filter')) return;
        if (!$merge) $this->set_filters(array());
        $f = $this->get_filters();
        if(is_array($arg)) {
            foreach ($arg as $k => $v) {
                if (!array_key_exists($k, $f) || $overwrite) {
                    $f[$k] = $v;
                }
            }
        }
        $this->set_filters($f);
    }
    public function set_filters($filters, $merge = false, $override_saved = false) {
        $current_filters = $merge ? $this->get_filters($override_saved) : array();
        $filters = array_merge($current_filters, $filters);
        if ($override_saved) {
            $this->set_module_variable('def_filter_over', $filters);
        } else {
            $this->set_module_variable('def_filter', $filters);
        }
    }
    public function get_filters($override_saved = false) {
        $filter_var = $override_saved ? 'def_filter_over' : 'def_filter';
    	return $this->get_module_variable($filter_var, array());
    }
    public function set_default_order($arg){
        foreach ($arg as $k=>$v)
            $this->default_order[$k] = $v;
    }
    public function force_order($arg){
        $this->force_order = $arg;
    }
    public function caption(){
        return $this->getCaption() . ': ' . _V($this->action);
    }
    public function recordpicker($element, $format, $crits=array(), $cols=array(), $order=array(), $filters=array(), $select_form = '') {
        $this->set_module_variable('element',$element);
        $this->set_module_variable('format_func',$format);
        $theme = $this->init_module(Base_Theme::module_name());
        Base_ThemeCommon::load_css($this->get_type(),'Browsing_records');
        $theme->assign('filters', $this->show_filters($filters, $element));
        $theme->assign('disabled', '');
        $theme->assign('select_form', $select_form);
        $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $crits);
        $this->include_tab_in_id = $select_form? true: false;
        $theme->assign('table', $this->show_data($this->crits, $cols, $order, false, true));
        if ($this->recordsCount>=10000) {
            $theme->assign('select_all', array('js'=>'', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'', 'label'=>__('Deselect all')));
        } else {
            load_js('modules/Utils/RecordBrowser/RecordPicker/select_all.js');
            $theme->assign('select_all', array('js'=>'RecordPicker_select_all(1,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'RecordPicker_select_all(0,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Deselect all')));
        }
        $theme->assign('close_leightbox', array('js'=>'leightbox_deactivate(\'rpicker_leightbox_'.$element.'\');', 'label'=>__('Commit Selection')));
        load_js('modules/Utils/RecordBrowser/rpicker.js');

        $rpicker_ind = $this->get_module_variable('rpicker_ind');
        foreach($rpicker_ind as $v) {
            eval_js('rpicker_init(\''.$element.'\',\''.$v.'\')');
        }
        $theme->display('Record_picker');
    }
    public function recordpicker_fs($crits, $cols, $order, $filters, $path) {
		self::$browsed_records = array();
        $theme = $this->init_module(Base_Theme::module_name());
        Base_ThemeCommon::load_css($this->get_type(),'Browsing_records');
        $this->set_module_variable('rp_fs_path',$path);
        $selected = Module::static_get_module_variable($path,'selected',array());
        $theme->assign('filters', $this->show_filters($filters));
        $theme->assign('disabled', '');
        $this->crits = Utils_RecordBrowserCommon::merge_crits($this->crits, $crits);
        $theme->assign('table', $this->show_data($this->crits, $cols, $order, false, true));
        if ($this->recordsCount>=10000) {
            $theme->assign('disabled', '_disabled');
            $theme->assign('select_all', array('js'=>'', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'', 'label'=>__('Deselect all')));
        } else {
            load_js('modules/Utils/RecordBrowser/RecordPickerFS/select_all.js');
            $theme->assign('select_all', array('js'=>'RecordPicker_select_all(1,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Select all')));
            $theme->assign('deselect_all', array('js'=>'RecordPicker_select_all(0,\''.$this->get_path().'\',\''.__('Processing...').'\');', 'label'=>__('Deselect all')));
        }

        load_js('modules/Utils/RecordBrowser/rpicker_fs.js');
        if (isset(self::$browsed_records['records'])) {
            foreach(self::$browsed_records['records'] as $id=>$i) {
                eval_js('rpicker_fs_init('.$id.','.(isset($selected[$id]) && $selected[$id]?1:0).',\''.$this->get_path().'\')');
            }
        }
/*
        $rpicker_ind = $this->get_module_variable('rpicker_ind');
        $init_func = 'init_all_rpicker_'.$element.' = function(id, cstring){';
        foreach($rpicker_ind as $v)
            $init_func .= 'rpicker_init(\''.$element.'\','.$v.');';
        $init_func .= '}';
        eval_js($init_func.';init_all_rpicker_'.$element.'();');*/
        $theme->display('Record_picker');
    }
    public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

        $form = $this->init_module(Libs_QuickForm::module_name(), null, 'pick_recordset');
        $opts = Utils_RecordBrowserCommon::list_installed_recordsets('%caption (%tab)');
		asort($opts);
		$first = array_keys($opts);
		$first = reset($first);
        $form->addElement('select', 'recordset', __('Recordset'), $opts, array('onchange'=>$form->get_submit_form_js()));
        if ($form->validate()) {
            $tab = $form->exportValue('recordset');
            $this->set_module_variable('admin_browse_recordset', $tab);
        }
        $tab = $this->get_module_variable('admin_browse_recordset', $first);
        $form->setDefaults(array('recordset'=>$tab));
        $form->display_as_column();
        if ($tab) {
        	$this->pack_module('Utils_RecordBrowser#Admin', null, null, $tab);
		}
        $custom_recordsets_module = 'Utils/RecordBrowser/CustomRecordsets';
        if (ModuleManager::is_installed($custom_recordsets_module) >= 0) {
            $href = $this->create_callback_href(array('Base_BoxCommon', 'push_module'), array($custom_recordsets_module, 'admin'));
            Base_ActionBarCommon::add('settings', __('Custom Recordsets'), $href);
        }
    }

    public function enable_quick_new_records($button = true, $force_show = null) {
        $this->setAddInTable();
		$href = 'href="javascript:void(0);" onclick="$(\'add_in_table_row\').style.display=($(\'add_in_table_row\').style.display==\'none\'?\'\':\'none\');if(focus_on_field)if($(focus_on_field))focus_by_id(focus_on_field);"';
        if ($button) $this->add_button = $href;
        if ($force_show===null) $this->show_add_in_table = Base_User_SettingsCommon::get('Utils_RecordBrowser','add_in_table_shown');
        else $this->show_add_in_table = $force_show;
        if ($this->get_module_variable('force_add_in_table_after_submit', false)) {
            $this->show_add_in_table = true;
            $this->set_module_variable('force_add_in_table_after_submit', false);
        }
        Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){if (jq("#add_in_table_row").is(":visible")) jq("input[name=submit_qanr]").click();}');
		return $href;
    }
	
    public function set_custom_filter($arg, $spec){
        $this->custom_filters[$arg] = $spec;
    }

    public function set_no_limit_in_mini_view($arg){
        $this->set_module_variable('no_limit_in_mini_view',$arg);
    }

    public function mini_view($cols, $crits, $order, $info=null, $limit=null, $conf = array('actions_edit'=>true, 'actions_info'=>true), & $opts = array()){
        unset($_SESSION['client']['recordbrowser']['admin_access']);
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),$this->getTab(),$this->getTab());
        $field_hash = array();
        foreach($this->getRecordset()->getFields() as $field => $args)
            $field_hash[$args['id']] = $field;
        $header = array();
        $callbacks = array();
        foreach($cols as $k=>$v) {
            if (isset($v['callback'])) $callbacks[] = $v['callback'];
            else $callbacks[] = null;
            if (is_array($v)) {
                $arr = array('name'=>_V($field_hash[$v['field']])); // TRSL
				if (isset($v['width'])) $arr['width'] = $v['width'];
                $cols[$k] = $v['field'];
            } else {
                $arr = array('name'=>_V($field_hash[$v])); // TRSL
                $cols[$k] = $v;
            }
            if (isset($v['label'])) $arr['name'] = $v['label'];
            $arr['wrapmode'] = 'nowrap';
            $header[] = $arr;
        }
        $gb->set_table_columns($header);
        $gb->set_fixed_columns_class($this->fixed_columns_class);

        $clean_order = array();
        foreach($order as $k=>$v) {
    	    if ($k==':Visited_on') $field_hash[$k] = $k;
    	    if ($k==':Fav') $field_hash[$k] = $k;
    	    if ($k==':Edited_on') $field_hash[$k] = $k;
            if ($k==':id') $field_hash[$k] = $k;
            $clean_order[] = array('column'=>$field_hash[$k],'order'=>$field_hash[$k],'direction'=>$v);
        }
        if ($limit!=null && !isset($conf['force_limit'])) {
            $limit = array('offset'=>0, 'numrows'=>$limit);
            $records_qty = Utils_RecordBrowserCommon::get_records_count($this->getTab(), $crits);
            if ($records_qty>$limit['numrows']) {
                if ($this->get_module_variable('no_limit_in_mini_view',false)) {
                    $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('Display first %d records', array($limit['numrows']))).' '.$this->create_callback_href(array($this, 'set_no_limit_in_mini_view'), array(false)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','show_some.png').'" border="0"></a>';
                    $limit = null;
                } else {
                    print(__('Displaying %s of %s records', array($limit['numrows'], $records_qty)));
                    $opts['actions'][] = '<a '.Utils_TooltipCommon::open_tag_attrs(__('Display all records')).' '.$this->create_callback_href(array($this, 'set_no_limit_in_mini_view'), array(true)).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','show_all.png').'" border="0"></a>';
                }
            }
        }
        $records = Utils_RecordBrowserCommon::get_records($this->getTab(), $crits, array(), $clean_order, $limit);
        foreach($records as $v) {
            $gb_row = $gb->get_new_row();
            $arr = array();
            foreach($cols as $k=>$w) {
                if (!isset($callbacks[$k])) $s = $this->get_val($field_hash[$w], $v);
                else $s = call_user_func($callbacks[$k], $v, false, $this->getRecordset()->getField($w),$this->getTab());
                $arr[] = $s;
            }
            $gb_row->add_data_array($arr);
            if (is_callable($info)) {
                $additional_info = call_user_func($info, $v);
            } else $additional_info = '';
            if (!is_array($additional_info) && isset($additional_info)) $additional_info = array('notes'=>$additional_info);
            if (isset($additional_info['notes'])) $additional_info['notes'] = $additional_info['notes'].'<hr />';
            if (isset($additional_info['row_attrs'])) $gb_row->set_attrs($additional_info['row_attrs']);
            if (isset($conf['actions_info']) && $conf['actions_info']) $gb_row->add_info($additional_info['notes'].Utils_RecordBrowserCommon::get_html_record_info($this->getTab(), $v['id']));
            if (isset($conf['actions_view']) && $conf['actions_view']) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'view',$v['id'])),'View');
            if (isset($conf['actions_edit']) && $conf['actions_edit']) if ($this->get_access('edit',$v)) $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_entry', 'edit',$v['id'])),'Edit');
            if (isset($conf['actions_delete']) && $conf['actions_delete']) if ($this->get_access('delete',$v)) $gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this record?'),array($this,'delete_record'),array($v['id'], false)),'Delete');
            if (isset($conf['actions_history']) && $conf['actions_history']) {
                $r_info = Utils_RecordBrowserCommon::get_record_info($this->getTab(), $v['id']);
                if ($r_info['edited_on']===null) $gb_row->add_action('','This record was never edited',null,'history_inactive');
                else $gb_row->add_action($this->create_callback_href(array($this,'navigate'),array('view_edit_history', $v['id'])),'View edit history',null,'history');
            }
            $this->call_additional_actions_methods($v, $gb_row);
        }
        $this->display_module($gb);
    }
	
	public function get_jump_to_id_button() {
        if (!$this->getRecordset()->getProperty('jump_to_id')) return '';

		$link = Module::create_href_js(Utils_RecordBrowserCommon::get_record_href_array($this->getTab(), '__ID__'));
		if (isset($_REQUEST['__jump_to_RB_record'])) Base_StatusBarCommon::message(__('Record not found'), 'warning');
		$link = str_replace('__ID__', '\'+this.value+\'', $link);
		return ' <a '.Utils_TooltipCommon::open_tag_attrs(__('Jump to record by ID')).' href="javascript:void(0);" onclick="jump_to_record_id(\''.$this->getTab().'\')"><img border="0" src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','jump_to.png').'"></a><input type="text" id="jump_to_record_input" style="display:none;width:50px;" onkeypress="if(event.keyCode==13)'.$link.'">';
	}

    public function search_by_id_form($label) {
        $message = '';
        $form = $this->init_module(Libs_QuickForm::module_name());
        $theme = $this->init_module(Base_Theme::module_name());
        $form->addElement('text', 'record_id', $label);
        $form->addRule('record_id', __('Must be a number'), 'numeric');
        $form->addRule('record_id', __('Field required'), 'required');
        $ret = false;
		if ($form->isSubmitted())
            $ret = true;
        if ($form->validate()) {
            $id = $form->exportValue('record_id');
            if (!is_numeric($id)) trigger_error('Invalid id',E_USER_ERROR);
            $r = Utils_RecordBrowserCommon::get_record($this->getTab(),$id);
            if (!$r || empty($r)) $message = __('There is no such record').'<br>';
            else if (!$r[':active']) $message = __('This record was deleted from the system').'<br>';
            else {
                Base_BoxCommon::push_module(Utils_RecordBrowser::module_name(),'view_entry',array('view', $id),array($this->getTab()));
                return;
            }
        }
        $form->assign_theme('form', $theme);
        $theme->assign('message', $message);
        $theme->display('search_by_id');
        return $ret;
    }
	
	public function setTab($tab) {		
		$this->tab = $tab;
		
		$this->set_module_variable('tab', $tab);
		
		if ($tab) {
			Utils_RecordBrowser_Recordset::exists($tab);
			
			$this->getRecordset(true);
		}		
	}
	
	public function getTab() {
		return $this->getRecordset()->getTab();
	}
	
	public function getRecordset($force = false) {
		if (!$this->recordset || $force) {
			$this->recordset = Utils_RecordBrowser_Recordset::create($this->tab, $force);
			
			if($this->tab=='__RECORDSETS__' || preg_match('/,/',$this->tab)) $params= ['','',0,0,0];
			else $params = $this->recordset->getProperties();
			
			if (!$params) trigger_error('There is no such recordset as '.$this->tab.'.', E_USER_ERROR);

			$this->clipboard_pattern = $this->recordset->getClipboardPattern();
			$this->setCaption($this->recordset->getCaption());
			$this->setIcon($this->recordset->getIcon());
			$this->full_history = $this->recordset->getProperty('full_history');
		}
		
		return $this->recordset;
	}
	
	public function modeEnabled($name) {
		switch ($name) {
			case 'watchdog':
				$avalable = Utils_WatchdogCommon::category_exists($this->getTab());
			break;
			
			default:
				$avalable = $this->getRecordset()->getProperty($name);
			break;
		}
		
		$disabled = $this->disabled[$name]?? false;
		
		return $avalable && !$disabled;
	}
}
?>