<?php
/**
 * RecordBrowserCommon class.
 *
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2019, X Systems Ltd
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die();

class Utils_RecordBrowser_Admin extends Module {
	public const ACCESS_RESTRICT = 0;
	public const ACCESS_VIEW = 1;
	public const ACCESS_FULL = 2;
	
	private $recordset;
	private $current_field;
	
	public function construct($tab) {
		$this->setTab($tab);
	}
	
    public function body() {    	
    	load_js($this->get_module_dir() . 'main.js');
    	
    	$_SESSION['client']['recordbrowser']['admin_access'] = $this->check_section_access('records', self::ACCESS_FULL);
    	Utils_RecordBrowserCommon::$admin_access = $this->check_section_access('records', self::ACCESS_FULL);

        $tb = $this->init_module(Utils_TabbedBrowser::module_name());
		$tabs = [
				'fields' => [
						'func' => [$this, 'setup_loader'],
						'label' => __('Manage Fields'),
				],
				'records' => [
						'func' => [$this, 'show_data'],
						'label' => __('Manage Records'),
						'args' => [
								[],
								[],
								[],
								$this->check_section_access('records', self::ACCESS_FULL)
						]
				],
				'addons' => [
						'func' => [$this, 'manage_addons'],
						'label' => __('Manage Addons'),
				],
				'permissions' => [
						'func' => [$this, 'manage_permissions'],
						'label' => __('Permissions'),
				],
				'settings' => [
						'func' => [$this, 'settings'],
						'label' => __('Settings'),
				],
				'pattern' => [
						'func' => [$this, 'setup_clipboard_pattern'],
						'label' => __('Clipboard Pattern'),
				]
		];
		foreach ( $tabs as $section => $t ) {
			if (! $this->check_section_access($section)) continue;
			
			$tb->set_tab($t['label'], $t['func'], $t['args']?? []);
		}

        $tb->body();
        $tb->tag();
    }
    
    public static function get_access_levels_select_list() {
    	return [
    			self::ACCESS_RESTRICT => __('No access'),
    			self::ACCESS_VIEW => __('View'),
    			self::ACCESS_FULL => __('Full')
    	];
    }
    
    public function check_section_access($section, $level = self::ACCESS_VIEW) {
    	return Base_AdminCommon::get_access(Utils_RecordBrowser::module_name(), $section) >= $level?: self::ACCESS_VIEW;
    }
    
    public function settings() {
    	$full_access = $this->check_section_access('settings', self::ACCESS_FULL);

        $form = $this->init_module(Libs_QuickForm::module_name());
        $form->addElement('text', 'caption', __('Caption'));
        
        if ($callback = $this->getRecordset()->getProperty('description_callback')) {
            echo '<div style="color:red; padding: 1em;">' . __('Description Fields take precedence over callback. Leave them empty to use callback') . '</div>';
            
            $form->addElement('static', '', __('Description Callback'), is_array($callback)? implode('::', $callback): $callback)->freeze();
        }
        
        $form->addElement('text', 'description_pattern', __('Description Pattern'), [
				'placeholder' => __('Record description pattern e.g. %%{{first_name}} %%{{last_name}}')
		]);
		$form->addElement('select', 'favorites', __('Favorites'), [
				__('No'),
				__('Yes')
		]);
        
        $recent_values = ['[' . __('Deactivate') . ']'];
        foreach ([5, 10, 15, 20, 25] as $rv) { $recent_values[$rv] =  __('%d Records', [$rv]) ; }
        $form->addElement('select', 'recent', __('Recent'), $recent_values);
		
		$form->addElement('select', 'full_history', __('History'), [
				__('No'),
				__('Yes')
		]);
		$form->addElement('select', 'jump_to_id', __('Jump to ID'), [
				__('No'),
				__('Yes')
		]);
		$form->addElement('select', 'search_include', __('Search'), [
				__('Exclude'),
				__('Include by default'),
				__('Include optional')
		]);
		$form->addElement('select', 'search_priority', __('Search priority'), [
				- 2 => __('Lowest'),
				- 1 => __('Low'),
				0 => __('Default'),
				1 => __('High'),
				2 => __('Highest')
		]);

		if (! $full_access) {
			$form->freeze();
		}
		
		if ($defaults = $this->getRecordset()->getProperties()) {
			$form->setDefaults($defaults);
		}
		
        $form->display_as_column();
        
        if (! $full_access) return;
            
        $clear_index_href = $this->create_confirm_callback_href(__('Are you sure?'), [$this, 'clear_search_index']);
        echo "<a $clear_index_href>" . __('Clear search index') . "</a>";
            
        if ($form->validate()) {
        	$this->getRecordset()->setProperties($form->exportValues());
        }
        
        Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
    }

    public function clear_search_index()
    {
    	if (! $this->getRecordset()->clearSearchIndex()) return;

    	Base_StatusBarCommon::message(__('Index cleared for this table. Indexing again - it may take some time.'));
    }

    public function manage_addons() {
    	$full_access = $this->check_section_access('addons', self::ACCESS_FULL);

        $gb = $this->init_module(Utils_GenericBrowser::module_name(),'manage_addons'.$this->getTab(), 'manage_addons'.$this->getTab());
        
        $gb->set_table_columns([
				[
						'name' => __('Addon caption')
				],
				[
						'name' => __('Called method')
				]
		]);
        
        $first = true;
        $gb_row = null;
        foreach ($this->getRecordset()->getAddons() as $addon) {
            if (isset($gb_row) && $full_access) $gb_row->add_action($this->create_callback_href([$this, 'move_addon'],[$addon['tab'],$addon['pos']-1, +1]), __('Move down'), null, 'move-down');
            $gb_row = $gb->get_new_row();
            $gb_row->add_data($addon['label'], $addon['module'].' -> '.$addon['func'].'()');
			
			if (! $full_access) continue;
				
			$gb_row->add_action($this->create_callback_href([$this, 'set_addon_active'], [$addon['tab'], $addon['pos'], !$addon['enabled']]), $addon['enabled']?__('Deactivate'):__('Activate'), null, 'active-'.($addon['enabled']?'on':'off'));

			if (!$first) $gb_row->add_action($this->create_callback_href([$this, 'move_addon'], [$addon['tab'],$addon['pos'], -1]), __('Move up'), null, 'move-up');
			$first = false;
        }
        $this->display_module($gb);
    }
    
    public function set_addon_active($tab, $pos, $v) {
    	DB::Execute('UPDATE recordbrowser_addon SET enabled=%d WHERE tab=%s AND pos=%d', array($v?1:0, $tab, $pos));
    	return false;
    }
    
    public function move_addon($tab, $pos, $v) {
    	DB::StartTrans();
    	DB::Execute('UPDATE recordbrowser_addon SET pos=0 WHERE tab=%s AND pos=%d', array($tab, $pos));
    	DB::Execute('UPDATE recordbrowser_addon SET pos=%d WHERE tab=%s AND pos=%d', array($pos, $tab, $pos+$v));
    	DB::Execute('UPDATE recordbrowser_addon SET pos=%d WHERE tab=%s AND pos=0', array($pos+$v, $tab));
    	DB::CompleteTrans();
    	return false;
    }

    public function new_page() {
        DB::StartTrans();
        $max_f = DB::GetOne('SELECT MAX(position) FROM '.$this->getTab().'_field');
        $max_p = DB::GetOne('SELECT MAX(processing_order) FROM '.$this->getTab().'_field');
        $num = 1;
        do {
            $num++;
            $x = DB::GetOne('SELECT position FROM '.$this->getTab().'_field WHERE type = \'page_split\' AND field = %s', array('Details '.$num));
        } while ($x!==false && $x!==null);
        DB::Execute('INSERT INTO '.$this->getTab().'_field (field, type, extra, position, processing_order) VALUES(%s, \'page_split\', 1, %d, %d)', array('Details '.$num, $max_f+1, $max_p+1));
        DB::CompleteTrans();
    }
    public function delete_page($id) {
        DB::StartTrans();
        $p = DB::GetOne('SELECT position FROM '.$this->getTab().'_field WHERE field=%s', array($id));
        $po = DB::GetOne('SELECT processing_order FROM '.$this->getTab().'_field WHERE field=%s', array($id));
        DB::Execute('UPDATE '.$this->getTab().'_field SET position = position-1 WHERE position > %d', array($p));
        DB::Execute('UPDATE '.$this->getTab().'_field SET processing_order = processing_order-1 WHERE processing_order > %d', array($po));
        DB::Execute('DELETE FROM '.$this->getTab().'_field WHERE field=%s', array($id));
        DB::CompleteTrans();
    }
    public function edit_page($id) {
        if ($this->is_back())
            return false;

        $form = $this->init_module(Libs_QuickForm::module_name(), null, 'edit_page');

        $form->addElement('header', null, __('Edit page properties'));
        $form->addElement('text', 'label', __('Label'));
        $this->current_field = $id;
        $form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
        $form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
        $form->addRule('label', __('Field required'), 'required');
        $form->addRule('label', __('Field or Page with this name already exists.'), 'check_if_column_exists');
        $form->addRule('label', __('Only letters, numbers and space are allowed.'), 'regex', '/^[a-zA-Z ]*$/');
        $form->addRule('label', __('"ID" as page name is not allowed.'), 'check_if_no_id');
        $form->setDefaults(array('label'=>$id));

        if($form->validate()) {
            $data = $form->exportValues();
            foreach($data as $key=>$val)
                $data[$key] = htmlspecialchars($val);
            DB::Execute('UPDATE '.$this->getTab().'_field SET field=%s WHERE field=%s',
                        array($data['label'], $id));
            return false;
        }
        $form->display();
		Base_ActionBarCommon::add('back',__('Cancel'),$this->create_back_href());
		Base_ActionBarCommon::add('save',__('Save'),$form->get_submit_form_href());

        return true;
    }
    public function setup_clipboard_pattern() {
    	$full_access = $this->check_section_access('pattern', self::ACCESS_FULL);
        
		$form = $this->init_module(Libs_QuickForm::module_name());
        
        $entry = $this->getRecordset()->getClipboardPatternEntry();
        
		$form->addElement('select', 'enabled', __('Enabled'), [
				__('No'),
				__('Yes')
		]);
        
        $info = '<b>'.__('This is an html pattern. All html tags are allowed.').'<br/>'.
	        __('Use &lt;pre&gt; some text &lt;/pre&gt; to generate text identical as you typed it.').'<br/><br/>'.
	        __('Conditional use:').'<br/>'.__('%%{lorem {keyword} ipsum {keyword2}}').'<br/>'.
	        __('lorem ipsum will be shown only when at least one of keywords has a value. Nested conditions are allowed.').'<br/><br/>'.
	        __('Normal use:').'<br/>'.__('%%{{keyword}}').'<br/><br/>'.
	        __('Keywords').':<br/></b>';
        
		foreach ( $this->getRecordset()->getHash() as $id => $name ) {
			$info .= '<b>' . $id . '</b> - ' . $name . ', ';
		}
        
        $label = '<img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser', 'info.png').'" '.Utils_TooltipCommon::open_tag_attrs($info).'/> '.__('Pattern');
        
        $textarea = $form->addElement('textarea', 'pattern', $label);
        $textarea->setRows(12);
        $textarea->setCols(80);
		
		if ($full_access) {
			Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		} else {
			$form->freeze();
		}
		
		$form->setDefaults($entry ? [
				'enabled' => $entry['enabled'] ? 1: 0,
				'pattern' => $entry['pattern']
		]: [
				'enabled' => 0
		]);

        $form->display_as_column();
        
        if ($full_access && $form->validate()) {
			$this->getRecordset()->setClipboardPattern($form->exportValue('pattern'), [
					'enabled' => $form->exportValue('enabled'),
					'force' => true
			]);
		}
    }
    public function setup_loader() {
        if (isset($_REQUEST['field_pos'])) {
            list($field, $position) = $_REQUEST['field_pos'];
            // adjust position
            $position += 2;
            Utils_RecordBrowserCommon::change_field_position($this->getTab(), $field, $position);
        }
        
        $full_access = $this->check_section_access('fields', self::ACCESS_FULL);

		if ($full_access) {
			Base_ActionBarCommon::add('add',__('New field'),$this->create_callback_href([$this, 'view_field']));
			Base_ActionBarCommon::add('add',__('New page'),$this->create_callback_href([$this, 'new_page']));
		}
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'fields');
        $gb->set_table_columns(array(
            array('name'=>__('Field'), 'width'=>20),
            array('name'=>__('Caption'), 'width'=>20),
            array('name'=>__('Help Message'), 'width'=>12),
            array('name'=>__('Type'), 'width'=>10),
            array('name'=>__('Table view'), 'width'=>5),
            array('name'=>__('Tooltip'), 'width'=>5),
            array('name'=>__('Required'), 'width'=>5),
            array('name'=>__('Filter'), 'width'=>5),
            array('name'=>__('Export'), 'width'=>5),
            array('name'=>__('Parameters'), 'width'=>27),
            array('name'=>__('Value display function'), 'width'=>5),
            array('name'=>__('Field generator function'), 'width'=>5)
		));
		
		$adminFields = $this->getRecordset()->getAdminFields();
        //read database
		$rows = end($adminFields);
		$rows = $rows['position'];
		foreach($adminFields as $field => $args) {
            $gb_row = $gb->get_new_row();
			if ($full_access) {
				if ($args['type'] != 'page_split') {
					$gb_row->add_action($this->create_callback_href(array($this, 'view_field'),array('edit',$field)), __('Edit'));
				} elseif ($field!='General') {
					$gb_row->add_action($this->create_callback_href(array($this, 'delete_page'),array($field)), __('Delete'));
					$gb_row->add_action($this->create_callback_href(array($this, 'edit_page'),array($field)), __('Edit'));
				}
				if ($args['type']!=='page_split' && $args['extra']){
					if ($args['active']) $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, false)), __('Deactivate'), null, 'active-on');
					else $gb_row->add_action($this->create_callback_href(array($this, 'set_field_active'),array($field, true)), __('Activate'), null, 'active-off');
				}
                if ($field != 'General') {
                    $gb_row->add_action('class="move-handle"', __('Move'), __('Drag to change field position'), 'move-up-down');
                    $gb_row->set_attrs("field_name=\"$field\" class=\"sortable\"");
                }
			}
            switch ($args['type']) {
				case 'text':
					$args['param'] = __('Length').' '.$args['param'];
					break;
				case 'select':
				case 'multiselect':
					$reg = $args['param'];
					if (!$reg['single_tab']) {
						$param = __('Source').': Record Sets'.'<br/>';
						$param .= $reg['crits_callback']? __('Crits callback').': '. (implode('::', $reg['crits_callback'])): '';
						$args['param'] = $param;
						break;
					} else {
						$param = __('Source').': Record Set'.'<br/>';
						$param .= __('Recordset').': '.Utils_RecordBrowserCommon::get_caption($reg['single_tab']).' ('.$reg['single_tab'].')<br/>';
						$fs = array_map('_V', $reg['cols']);
						$param .= __('Related field(s)').': '.(implode(', ',$fs)).'<br/>';
						$param .= $reg['crits_callback']? __('Crits callback').': '. (implode('::', $reg['crits_callback'])): '';
						$args['param'] = $param;
						break;
					}
				case 'commondata':
				case 'multicommondata':
					if ($args['type']=='commondata') $args['type'] = 'select';
					if ($args['type']=='multicommondata') $args['type'] = 'multiselect';
					$param = __('Source').': CommonData'.'<br/>';
					$param .= __('Table').': '.$args['param']['array_id'].'<br/>';
					$param .= __('Order by').': '._V(ucfirst($args['param']['order']));
					$args['param'] = $param;
					break;
                case 'time':
                case 'timestamp':
                    $interval = $args['param'] ? $args['param'] : __('Default');
                    $args['param'] = __('Minutes Interval') . ': ' . $interval;
                    break;
				default:
					$args['param'] = '';
			}
			$types = Utils_RecordBrowser_Recordset_Field::getRegistrySelectList();
			
            if ($args['type'] == 'page_split')
                    $gb_row->add_data(
                        array('style'=>'background-color: #DFDFFF;', 'value'=>$field),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>$args['name']),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>__('Page Split')),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>''),
                        array('style'=>'background-color: #DFDFFF;', 'value'=>'')
                    );
                else {
                	if ($callback = $args['display_callback']) {
                        $d_c = '<b>Yes</b>';
                        if(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)::([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable(array($match[1],$match[2]))) $d_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } elseif(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable($match[1])) $d_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } else
                            $d_c = '<b>PHP</b>';
                        $d_c = Utils_TooltipCommon::create($d_c, $callback, false);
                    } else $d_c = '';
                    if ($callback = $args['QFfield_callback']) {
                        $QF_c = '<b>Yes</b>';
                        if(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)::([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable(array($match[1],$match[2]))) $QF_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } elseif(preg_match('/^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/',$callback,$match)) {
                            if(!is_callable($match[1])) $QF_c = '<span style="color:red;font-weight:bold;">Invalid!</span>';
                        } else
                            $QF_c = '<b>PHP</b>';
                        $QF_c = Utils_TooltipCommon::create($QF_c, $callback, false);
                    } else $QF_c = '';
                    $gb_row->add_data(
                        $field,
                        $args['name'],
                        $args['help'],
                        $types[$args['type']]??$args['type'],
                        $args['visible']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['tooltip']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['required']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['filter']?'<b>'.__('Yes').'</b>':__('No'),
                        $args['export']?'<b>'.__('Yes').'</b>':__('No'),
                        is_array($args['param'])? serialize($args['param']): $args['param'],
						$d_c,
						$QF_c
                    );
				}
        }
        $this->display_module($gb);

        // sorting
        load_js($this->get_module_dir() . 'sort_fields.js');
        $table_md5 = md5($gb->get_path());
        eval_js("rb_admin_sort_fields_init(\"$table_md5\")");
    }
    //////////////////////////////////////////////////////////////////////////////////////////
    public function set_field_active($field, $set=true) {
        DB::Execute('UPDATE '.$this->getTab().'_field SET active=%d WHERE field=%s',array($set?1:0,$field));
        return false;
    } //submit_delete_field
    //////////////////////////////////////////////////////////////////////////////////////////
	private $admin_field_mode = '';
	private $admin_field_type = '';
	private $admin_field_name = '';
	private $admin_field = '';
    public function view_field($action = 'add', $field = null) {
        if (!$action) $action = 'add';
        if ($this->is_back()) return false;
        if ($this->check_for_jump()) return;
        $data_type = array(
        	null=>'---',
            'autonumber'=>__('Autonumber'),
            'currency'=>__('Currency'),
            'checkbox'=>__('Checkbox'),
            'date'=>__('Date'),
            'time' => __('Time'),
            'timestamp' => __('Timestamp'),
            'integer'=>__('Integer'),
            'float'=>__('Float'),
            'text'=>__('Text'),
            'long text'=>__('Long text'),
            'select'=>__('Select field'),
            'calculated'=>__('Calculated'),
            'file'=>__('File')
	
        );
        natcasesort($data_type);

        $form = $this->init_module(Libs_QuickForm::module_name());

        switch ($action) {
            case 'add': $form->addElement('header', null, __('Add new field'));
                        break;
            case 'edit': $form->addElement('header', null, __('Edit field properties'));
                        break;
        }
        $form->addElement('text', 'field', __('Field'), array('maxlength'=>32));
        $form->registerRule('check_if_column_exists', 'callback', 'check_if_column_exists', $this);
        $this->current_field = $field;
        $form->registerRule('check_if_no_id', 'callback', 'check_if_no_id', $this);
        $form->addRule('field', __('Field required'), 'required');
        $form->addRule('field', __('Field with this name already exists.'), 'check_if_column_exists');
        $form->addRule('field', __('Field length cannot be over 32 characters.'), 'maxlength', 32);
        $form->addRule('field', __('Invalid field name.'), 'regex', '/^[a-zA-Z][a-zA-Z \(\)\%0-9]*$/');
        $form->addRule('field', __('Invalid field name.'), 'check_if_no_id');

        $form->addElement('text', 'caption', __('Caption'), array('maxlength'=>255, 'placeholder' => __('Leave empty to use default label')));

        if ($action=='edit') {
        	$row = $this->getRecordset()->getField($field);

			switch ($row['type']) {
				case 'select':
				case 'multiselect':
					$row['select_data_type'] = 'select';
					$row['select_type'] = $row['type'];
					$param = $row['param'];
					if ($param['single_tab']=='__COMMON__') {
						$row['data_source'] = 'commondata';
						$order = $param['order'];
                        if (strlen($order) <= 1) $order = $order ? 'key' : 'value';
						$row['order_by'] = $order;
						$row['commondata_table'] = $param['array_id'];
					} else {
                        $row['label_field'] = implode(',', $param['cols']);
						$row['data_source'] = 'rset';
						$row['rset'] = $param['select_tabs'];
					}
					break;
				case 'commondata':
					$row['select_data_type'] = 'select';
					$row['select_type'] = 'select';
					$row['data_source'] = 'commondata';
					$param = $row['param'];
					$form->setDefaults(array('order_by'=>$param['order'], 'commondata_table'=>$param['array_id']));
					break;
                case 'autonumber':
                    $row['select_data_type'] = 'autonumber';
                    Utils_RecordBrowserCommon::decode_autonumber_param($row['param'], $autonumber_prefix, $autonumber_pad_length, $autonumber_pad_mask);
                    $row['autonumber_prefix'] = $autonumber_prefix;
                    $row['autonumber_pad_length'] = $autonumber_pad_length;
                    $row['autonumber_pad_mask'] = $autonumber_pad_mask;
                    break;
				case 'text':
                    $row['select_data_type'] = $row['type'];
					$row['text_length'] = $row['param'];
                    break;
                case 'time':
                case 'timestamp':
                    $row['select_data_type'] = $row['type'];
                    $row['minute_increment'] = $row['param'];
                    break;
				default:
					$row['select_data_type'] = $row['type'];
					if (!isset($data_type[$row['type']]))
						$data_type[$row['type']] = _V(ucfirst($row['type'])); // ****** - field type
			}
			if (!isset($row['rset'])) $row['rset'] = array('contact');
			if (!isset($row['data_source'])) $row['data_source'] = 'commondata';
			$form->setDefaults($row->getArrayCopy());
            $selected_data = $row['type'];
			$this->admin_field_type = $row['select_data_type'];
			$this->admin_field = $row;
        } else {
            $selected_data = $form->exportValue('select_data_type');
            $form->setDefaults(array('visible'=>1,
                'autonumber_prefix'=>'#',
                'autonumber_pad_length'=>'6',
                'autonumber_pad_mask'=>'0'));
        }
		$this->admin_field_mode = $action;
		$this->admin_field_name = $field;
		
		$form->addElement('select', 'select_data_type', __('Data Type'), $data_type, array('id'=>'select_data_type'));

		$form->addElement('text', 'text_length', __('Maximum Length'), array('id'=>'length'));
        $minute_increment_values = array(1=>1,2=>2,5=>5,10=>10,15=>15,20=>20,30=>30,60=>__('Full hours'));
		$form->addElement('select', 'minute_increment', __('Minutes Interval'), $minute_increment_values, array('id'=>'minute_increment'));

		$form->addElement('select', 'data_source', __('Source of Data'), array('rset'=>__('Recordset'), 'commondata'=>__('CommonData')), array('id'=>'data_source'));
		$form->addElement('select', 'select_type', __('Type'), array('select'=>__('Single value selection'), 'multiselect'=>__('Multiple values selection')), array('id'=>'select_type'));
		$form->addElement('select', 'order_by', __('Order by'), array('key'=>__('Key'), 'value'=>__('Value'), 'position' => __('Position')), array('id'=>'order_by'));
		$form->addElement('text', 'commondata_table', __('CommonData table'), array('id'=>'commondata_table'));

		$tables = Utils_RecordBrowserCommon::list_installed_recordsets();
		asort($tables);
		$form->addElement('multiselect', 'rset', '<span id="rset_label">'.__('Recordset').'</span>', $tables, array('id'=>'rset'));
		$form->addElement('text', 'label_field', __('Related field(s)'), array('id'=>'label_field'));

		$form->addFormRule(array($this, 'check_field_definitions'));

		$form->addElement('checkbox', 'visible', __('Table view'));
		$form->addElement('checkbox', 'tooltip', __('Tooltip view'));
		$form->addElement('checkbox', 'required', __('Required'), null, array('id'=>'required'));
		$form->addElement('checkbox', 'filter', __('Filter enabled'), null, array('id' => 'filter'));
		$form->addElement('checkbox', 'export', __('Export'));
        
        $form->addElement('text', 'autonumber_prefix', __('Prefix string'), array('id' => 'autonumber_prefix'));
        $form->addRule('autonumber_prefix', __('Double underscore is not allowed'), 'callback', array('Utils_RecordBrowser', 'qf_rule_without_double_underscore'));
        $form->addElement('text', 'autonumber_pad_length', __('Pad length'), array('id' => 'autonumber_pad_length'));
        $form->addRule('autonumber_pad_length', __('Only integer numbers are allowed.'), 'regex', '/^[0-9]*$/');
        $form->addElement('text', 'autonumber_pad_mask', __('Pad character'), array('id' => 'autonumber_pad_mask'));
        $form->addRule('autonumber_pad_mask', __('Double underscore is not allowed'), 'callback', array('Utils_RecordBrowser', 'qf_rule_without_double_underscore'));

        $ck = $form->addElement('ckeditor', 'help', __('Help Message'));
        $ck->setFCKProps(null, null, false);

		$form->addElement('checkbox', 'advanced', __('Edit advanced properties'), null, array('id'=>'advanced'));
        $icon = '<img src="' . Base_ThemeCommon::get_icon('info') . '" alt="info">';
        $txt = 'Callback returning the template or template file to use for the field';
        $form->addElement('textarea', 'template', __('Field template') . Utils_TooltipCommon::create($icon, $txt, false), array('maxlength'=>16000, 'style'=>'width:97%', 'id'=>'template'));
        $txt = '<ul><li>&lt;Class name&gt;::&ltmethod name&gt</li><li>&ltfunction name&gt</li><li>PHP:<br />- $record (array)<br />- $links_not_recommended (bool)<br />- $field (array)<br />return "value to display";</li></ul>';
		$form->addElement('textarea', 'display_callback', __('Value display function') . Utils_TooltipCommon::create($icon, $txt, false), array('maxlength'=>16000, 'style'=>'width:97%', 'id'=>'display_callback'));
        $txt = '<ul><li>&lt;Class name&gt;::&ltmethod name&gt</li><li>&ltfunction name&gt</li><li>PHP:<br />- $form (QuickForm object)<br />- $field (string)<br />- $label (string)<br />- $mode (string)<br />- $default (mixed)<br />- $desc (array)<br />- $rb_obj (RB object)<br />- $display_callback_table (array)</li></ul>';
		$form->addElement('textarea', 'QFfield_callback', __('Field generator function') . Utils_TooltipCommon::create($icon, $txt, false), array('maxlength'=>16000, 'style'=>'width:97%', 'id'=>'QFfield_callback'));
		
        if ($action=='edit') {
			$form->freeze('field');
			$form->freeze('select_data_type');
			$form->freeze('data_source');
			$form->freeze('rset');
		
			$display_callbacback = DB::GetOne('SELECT callback FROM '.$this->getTab().'_callback WHERE freezed=1 AND field=%s', array($field));
			$QFfield_callbacback = DB::GetOne('SELECT callback FROM '.$this->getTab().'_callback WHERE freezed=0 AND field=%s', array($field));
			$form->setDefaults(array('display_callback'=>$display_callbacback));
			$form->setDefaults(array('QFfield_callback'=>$QFfield_callbacback));
		}

        if ($form->validate()) {
            $data = $form->exportValues();
            $data['caption'] = trim($data['caption']);
            $data['field'] = trim($data['field']);
            $data['template'] = trim($data['template']);
			$type = DB::GetOne('SELECT type FROM '.$this->getTab().'_field WHERE field=%s', array($field));
			if (!isset($data['select_data_type'])) $data['select_data_type'] = $type;
            if ($action=='add')
                $field = $data['field'];
            $id = preg_replace('/[^a-z0-9]/','_',strtolower($field));
            $new_id = preg_replace('/[^a-z0-9]/','_',strtolower($data['field']));
            if (preg_match('/^[a-z0-9_]*$/',$id)==0) trigger_error('Invalid column name: '.$field);
            if (preg_match('/^[a-z0-9_]*$/',$new_id)==0) trigger_error('Invalid new column name: '.$data['field']);
			$param = '';
			switch ($data['select_data_type']) {
                case 'autonumber':
                    $data['required'] = false;
                    $data['filter'] = false;
                    $param = Utils_RecordBrowserCommon::encode_autonumber_param(
                            $data['autonumber_prefix'],
                            $data['autonumber_pad_length'],
                            $data['autonumber_pad_mask']);
                    // delete field and add again later to generate values
                    if ($action != 'add') {
                        Utils_RecordBrowserCommon::delete_record_field($this->getTab(), $field);
                        $action = 'add';
                        $field = $data['field'];
                    }
                    break;
				case 'checkbox': 
				case 'calculated': 
							$data['required'] = false;
							break;
				case 'text': if ($action=='add') $param = $data['text_length'];
							else {
								if ($data['text_length']<$row['param']) trigger_error('Invalid field length', E_USER_ERROR);
								$param = $data['text_length'];
								if ($data['text_length']!=$row['param']) {
									if(DB::is_postgresql())
										DB::Execute('ALTER TABLE '.$this->getTab().'_data_1 ALTER COLUMN f_'.$id.' TYPE VARCHAR('.$param.')');
									else
										DB::Execute('ALTER TABLE '.$this->getTab().'_data_1 MODIFY f_'.$id.' VARCHAR('.$param.')');
								}
							}
							break;
				case 'select':
							if ($data['data_source']=='commondata') {
								if ($data['select_type']=='select') {
									$param = Utils_RecordBrowserCommon::encode_commondata_param(array('order'=>$data['order_by'], 'array_id'=>$data['commondata_table']));
									$data['select_data_type'] = 'commondata';
								} else {
									$param = '__COMMON__::'.$data['commondata_table'].'::'.$data['order_by'];
									$data['select_data_type'] = 'multiselect';
								}
							} else {
								$data['select_data_type'] = $data['select_type'];
								if (!isset($row) || !isset($row['param'])) $row['param'] = ';::';
								$props = explode(';', $row['param']);
                                $change_param = false;
								if($data['rset']) {
								    $fs = explode(',', $data['label_field']);
								    if($data['label_field']) foreach($data['rset'] as $rset) {
        								$ret = $this->detranslate_field_names($rset, $fs);
	        							if (!empty($ret)) trigger_error('Invalid fields: '.implode(',',$fs));
	        						    }
	        						    $data['rset'] = implode(',',$data['rset']);
	        						    $data['label_field'] = implode('|',$fs);
                                    $change_param = true;
								} else if ($action == 'add') {
								    $data['rset'] = '__RECORDSETS__';
								    $data['label_field'] = '';
                                    $change_param = true;
								}
                                if ($change_param) {
                                    $props[0] = $data['rset'].'::'.$data['label_field'];
                                    $param = implode(';', $props);
                                } else {
                                    $param = $row['param'];
                                }
							}
							if (isset($row) && isset($row['type']) && $row['type']=='multiselect' && $data['select_type']=='select') {
								$ret = DB::Execute('SELECT id, f_'.$id.' AS v FROM '.$this->getTab().'_data_1 WHERE f_'.$id.' IS NOT NULL');
								while ($rr = $ret->FetchRow()) {
									$v = Utils_RecordBrowserCommon::decode_multi($rr['v']);
									$v = array_pop($v);
									DB::Execute('UPDATE '.$this->getTab().'_data_1 SET f_'.$id.'=%s WHERE id=%d', array($v, $rr['id']));
								}
							}
							if (isset($row) && isset($row['type'])  && $row['type']!='multiselect' && $data['select_type']=='multiselect') {
								if(DB::is_postgresql())
									DB::Execute('ALTER TABLE '.$this->getTab().'_data_1 ALTER COLUMN f_'.$id.' TYPE TEXT');
								else
									DB::Execute('ALTER TABLE '.$this->getTab().'_data_1 MODIFY f_'.$id.' TEXT');
								$ret = DB::Execute('SELECT id, f_'.$id.' AS v FROM '.$this->getTab().'_data_1 WHERE f_'.$id.' IS NOT NULL');
								while ($rr = $ret->FetchRow()) {
									$v = Utils_RecordBrowserCommon::encode_multi($rr['v']);
									DB::Execute('UPDATE '.$this->getTab().'_data_1 SET f_'.$id.'=%s WHERE id=%d', array($v, $rr['id']));
								}
							}
							break;
                case 'time':
                case 'timestamp':
                    $param = $data['minute_increment'];
                    break;
				default:	if (isset($row) && isset($row['param']))
								$param = $row['param'];
							break;
			}
            if ($action=='add') {
                $id = $new_id;
                if (in_array($data['select_data_type'], array('time','timestamp','currency','integer')))
                    $style = $data['select_data_type'];
                else
                    $style = '';
                $new_field_data = array('name' => $data['field'], 'type' => $data['select_data_type'], 'param' => $param, 'style' => $style);
                if (isset($this->admin_field['position']) && $this->admin_field['position']) {
                    $new_field_data['position'] = (int) $this->admin_field['position'];
                }
                Utils_RecordBrowserCommon::new_record_field($this->getTab(), $new_field_data);
            }
            if(!isset($data['visible']) || $data['visible'] == '') $data['visible'] = 0;
            if(!isset($data['required']) || $data['required'] == '') $data['required'] = 0;
            if(!isset($data['filter']) || $data['filter'] == '') $data['filter'] = 0;
            if(!isset($data['export']) || $data['export'] == '') $data['export'] = 0;
            if(!isset($data['tooltip']) || $data['tooltip'] == '') $data['tooltip'] = 0;

            foreach($data as $key=>$val)
                if (is_string($val) && $key != 'help' && $key != 'QFfield_callback' && $key != 'display_callback') $data[$key] = htmlspecialchars($val);

/*            DB::StartTrans();
            if ($id!=$new_id) {
                Utils_RecordBrowserCommon::check_table_name($this->getTab());
                if(DB::is_postgresql())
                    DB::Execute('ALTER TABLE '.$this->getTab().'_data_1 RENAME COLUMN f_'.$id.' TO f_'.$new_id);
                else {
                    $old_param = DB::GetOne('SELECT param FROM '.$this->getTab().'_field WHERE field=%s', array($field));
                    DB::RenameColumn($this->getTab().'_data_1', 'f_'.$id, 'f_'.$new_id, Utils_RecordBrowserCommon::actual_db_type($type, $old_param));
                }
            }*/
            DB::Execute('UPDATE '.$this->getTab().'_field SET caption=%s, param=%s, type=%s, field=%s, visible=%d, required=%d, filter=%d, export=%d, tooltip=%d, help=%s, template=%s WHERE field=%s',
                        array($data['caption'], $param, $data['select_data_type'], $data['field'], $data['visible'], $data['required'], $data['filter'], $data['export'], $data['tooltip'], $data['help'], $data['template'], $field));
/*            DB::Execute('UPDATE '.$this->getTab().'_edit_history_data SET field=%s WHERE field=%s',
                        array($new_id, $id));
            DB::CompleteTrans();*/
			
			DB::Execute('DELETE FROM '.$this->getTab().'_callback WHERE freezed=1 AND field=%s', array($field));
			if ($data['display_callback'])
				DB::Execute('INSERT INTO '.$this->getTab().'_callback (callback,freezed,field) VALUES (%s,1,%s)', array($data['display_callback'], $data['field']));
				
			DB::Execute('DELETE FROM '.$this->getTab().'_callback WHERE freezed=0 AND field=%s', array($field));
			if ($data['QFfield_callback'])
				DB::Execute('INSERT INTO '.$this->getTab().'_callback (callback,freezed,field) VALUES (%s,0,%s)', array($data['QFfield_callback'], $data['field']));

            return false;
        }
        $form->display_as_column();

        $autohide_mapping = array(
        		'select_data_type' => array(
		        		array('values'=>'text',
		        				'mode'=>'show',
		        				'fields'=>array('length')
		        		),
		        		array('values'=>'select',
		        				'mode'=>'show',
		        				'fields'=>array('data_source', 'select_type', 'commondata_table', 'order_by', 'rset_label', 'label_field')
		        		),
		        		array('values'=>'autonumber',
		        				'mode'=>'show',
		        				'fields'=>array('autonumber_prefix', 'autonumber_pad_length', 'autonumber_pad_mask')
		        		),
		        		array('values'=>array('time', 'timestamp'),
		        				'mode'=>'show',
		        				'fields'=>array('minute_increment')
		        		),
		        		array('values'=>array('checkbox', 'autonumber'),
		        				'mode'=>'hide',
		        				'fields'=>array('required')
		        		),
		    	),
	        	'data_source' => array(
		        		array('values'=>'rset',
		        				'mode'=>'show',
		        				'fields'=>array('rset_label', 'label_field')
		        		),
		        		array('values'=>'commondata',
		        				'mode'=>'show',
		        				'fields'=>array('commondata_table', 'order_by')
		        		),
		        ),
	        	'advanced' => array(
			        	array('values'=>1,
			        			'mode'=>'show',
			        			'fields'=>array('template', 'display_callback', 'QFfield_callback'),
			        			'confirm'=>__('Changing these settings may often cause system unstability. Are you sure you want to see advanced settings?')
			        	)
			        )
        );
        
        $row['advanced'] = 0;
        
        foreach ($autohide_mapping as $control_field=>$map) {
        	$form->autohide_fields($control_field, isset($row[$control_field])? $row[$control_field]:null, $map);
        }

		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		Base_ActionBarCommon::add('back', __('Cancel'), $this->create_back_href());
		
        return true;
    }
    
    public static function qf_rule_without_double_underscore($str) {
        return strpos($str, '__') === false;
    }
	
	public function check_field_definitions($data) {
		$ret = array();
		
		if ($this->admin_field_mode=='edit') 
			$type = $this->admin_field_type;
		else
			$type = $data['select_data_type'];

		if ($type == 'text') {
			$last = $this->admin_field_name?DB::GetOne('SELECT param FROM '.$this->getTab().'_field WHERE field=%s', array($this->admin_field_name)):1;
			if ($data['text_length']<$last) $ret['text_length'] = __('Must be a number greater or equal %d', array($last));
			if ($data['text_length']>255) $ret['text_length'] = __('Must be a number no greater than %d', array(255));
			if (!is_numeric($data['text_length'])) $ret['text_length'] = __('Must be a number');
			if ($data['text_length']=='') $ret['text_length'] = __('Field required');
		}
		if ($type == 'select') {
			if (!isset($data['data_source'])) $data['data_source'] = $this->admin_field['data_source'];
			if (!isset($data['rset'])) $data['rset'] = $this->admin_field['rset'];
			if (!is_array($data['rset'])) $data['rset'] = array_filter(explode('__SEP__', $data['rset'])); // data from multiselect field passed in raw format here
			if ($data['data_source']=='commondata' && $data['commondata_table']=='') $ret['commondata_table'] = __('Field required');
			if ($data['data_source']=='rset') {
				if ($data['label_field']!='') {
				    $fs = explode(',', $data['label_field']);
				    foreach($data['rset'] as $rset)
				        $ret = $ret + $this->detranslate_field_names($rset, $fs);
				}
			}
			if ($this->admin_field_mode=='edit' && $data['select_type']=='select' && $this->admin_field['select_type']=='multiselect') {
				$count = DB::GetOne('SELECT COUNT(*) FROM '.$this->getTab().'_data_1 WHERE f_'.Utils_RecordBrowserCommon::get_field_id($this->admin_field['field']).' '.DB::like().' %s', array('%_\_\__%'));
				if ($count!=0) {
					$ret['select_type'] = __('Cannot change type');
					print('<span class="important_notice">'.__('Following records have more than one value stored in this field, making type change impossible:'));
					$recs = DB::GetCol('SELECT id FROM '.$this->getTab().'_data_1 WHERE f_'.Utils_RecordBrowserCommon::get_field_id($this->admin_field['field']).' '.DB::like().' %s', array('%_\_\__%'));
					foreach ($recs as $r)
						print('<br/>'.Utils_RecordBrowserCommon::create_default_linked_label($this->getTab(), $r, false, false));
					print('</span>');
				}
			}
		}

        $show_php_embedding = false;
        foreach (array('QFfield_callback', 'display_callback') as $ff) {
            if (isset($data[$ff]) && $data[$ff]) {
                $callback_func = Utils_RecordBrowserCommon::callback_check_function($data[$ff], true);
                if ($callback_func) {
                    if (!is_callable($callback_func)) {
                        $ret[$ff] = __('Invalid callback');
                    }
                } elseif (!defined('ALLOW_PHP_EMBEDDING') || !ALLOW_PHP_EMBEDDING) {
                    $ret[$ff] = __('Using PHP code is blocked');
                    $show_php_embedding = true;
                }
            }
        }
        if ($show_php_embedding) {
            print(__('Using PHP code in application is currently disabled. Please edit file %s and add following line:', array(DATA_DIR . '/config.php'))) . '<br>';
            print("<pre>define('ALLOW_PHP_EMBEDDING', 1);</pre>");
        }
            
		return $ret;
	}
	
	public function detranslate_field_names($rset, & $fs) {
		$fields = [];
		foreach (Utils_RecordBrowser_Recordset::create($rset)->getHash() as $f)
			$fields[_V($f)] = $f; // ****** RecordBrowser - field name
		
		$ret = array();
		foreach ($fs as $k => $f) {
			$f = trim($f);
            $fs[$k] = $f;

			if (isset($fields[$f])) {
				if ($f==$fields[$f]) continue;
				
				$fs[$k] = $fields[$f];
				continue;
			}
			$ret['label_field'] = __('Field not found: %s', [$f]);
		}
		return $ret;
	}
	
    public function check_if_no_id($arg){
        return !preg_match('/^[iI][dD]$/',$arg);
    }
    public function check_if_column_exists($field){        
        if (strtolower($field)==strtolower($this->current_field)) return true;

        foreach($this->getRecordset()->getAdminFields() as $desc)
            if (strtolower($desc['name']) == strtolower($field))
                return false;
        
        return true;
    }
    
	public function manage_permissions() {
		$this->help('Permissions Editor','permissions');
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),'permissions_'.$this->getTab(), 'permissions_'.$this->getTab());
		$gb->set_table_columns(array(
				array('name'=>__('Access type'), 'width'=>'100px'),
				array('name'=>__('Clearance required'), 'width'=>'30'),
				array('name'=>__('Applies to records'), 'width'=>'60'),
				array('name'=>__('Fields'), 'width'=>'100px')
		));
		$ret = DB::Execute('SELECT * FROM '.$this->getTab().'_access AS acs ORDER BY action DESC');
		
		$tmp = DB::GetAll('SELECT * FROM '.$this->getTab().'_access_clearance AS acs');
		$clearance = [];
		foreach ($tmp as $t) $clearance[$t['rule_id']][] = $t['clearance'];
		
		$tmp = DB::GetAll('SELECT * FROM '.$this->getTab().'_access_fields AS acs');
		$fields = [];
		foreach ($tmp as $t) $fields[$t['rule_id']][] = $t['block_field'];
		
		$all_clearances = array_flip(Base_AclCommon::get_clearance(true));
		$all_fields = $this->getRecordset()->getHash();
		$actions = $this->get_permission_actions();
		$rules = array();
		while ($row = $ret->FetchRow()) {
			$clearance[$row['id']] = $clearance[$row['id']]?? [];
			$fields[$row['id']] = $fields[$row['id']]?? [];
			$action = $actions[$row['action']];
			$crits = Utils_RecordBrowser_Recordset_Access::parseAccessCrits($row['crits']);
			$crits_text = $crits->toWords($this->getRecordset());
			foreach ($fields[$row['id']] as $k=>$v)
				if (isset($all_fields[$v]))
					$fields[$row['id']][$k] = $all_fields[$v];
				else
					unset($fields[$row['id']][$k]);
			foreach ($clearance[$row['id']] as $k=>$v)
				if (isset($all_clearances[$v])) $clearance[$row['id']][$k] = $all_clearances[$v];
				else unset($clearance[$row['id']][$k]);
			$c_all_fields = count($all_fields);
			$c_fields = count($fields[$row['id']]);

			$props = $c_all_fields?($c_all_fields-$c_fields)/$c_all_fields:0;
			$color = dechex(255-68*$props).dechex(187+68*$props).'BB';
			$fields_value = ($c_all_fields-$c_fields).' / '.$c_all_fields;
			if ($props!=1) $fields_value = Utils_TooltipCommon::create($fields_value, '<b>'.__('Excluded fields').':</b><hr>'.implode('<br>',$fields[$row['id']]), false);
			
			$rules[$row['action']][$row['id']] = [
					$action,
					'<span class="Utils_RecordBrowser__permissions_crits">' . implode(' <span class="joint">' . __('and') . '</span><br>', $clearance[$row['id']]) . '</span>',
					[
							'value' => '<span class="Utils_RecordBrowser__permissions_crits">' . $crits_text . '</span>',
							'overflow_box' => false
					],
					[
							'style' => 'background-color:#' . $color,
							'value' => $fields_value
					]
			];
		}
		foreach ($actions as $a=>$l) {
			if (!isset($rules[$a])) continue;
			
			foreach ($rules[$a] as $id=>$vals) {
				$gb_row = $gb->get_new_row();
				$gb_row->add_data_array($vals);
				if ($this->check_section_access('permissions', self::ACCESS_FULL)) {
					$gb_row->add_action($this->create_callback_href(['Base_BoxCommon', 'push_module'], ['Utils_RecordBrowser#Admin', 'edit_permissions_rule', $id, $this->getTab()]), 'edit', __('Edit'));
					$gb_row->add_action($this->create_callback_href(['Base_BoxCommon', 'push_module'], ['Utils_RecordBrowser#Admin', 'edit_permissions_rule', [$id, true], $this->getTab()]), 'copy', __('Clone rule'), Base_ThemeCommon::get_template_file(Utils_Attachment::module_name(),'copy_small.png'));
					$gb_row->add_action($this->create_confirm_callback_href(__('Are you sure you want to delete this rule?'), [$this, 'delete_permissions_rule'], [$id]), 'delete', 'Delete');
				}
			}
		}
		if ($this->check_section_access('permissions', self::ACCESS_FULL)) {
			Base_ActionBarCommon::add('add',__('Add new rule'), $this->create_callback_href(['Base_BoxCommon', 'push_module'], ['Utils_RecordBrowser#Admin', 'edit_permissions_rule', [], $this->getTab()]));
		}
			
		Base_ThemeCommon::load_css('Utils_RecordBrowser', 'edit_permissions');
		$this->display_access_callback_descriptions();
		$this->display_module($gb);
		eval_js('Utils_RecordBrowser.permissions.crits_initialized = false;');
	}
	public function display_access_callback_descriptions() {
		$callbacks = Utils_RecordBrowserCommon::get_custom_access_callbacks($this->getTab());
	
		if (!$callbacks) return;
	
		$output = '<div class="crits_callback_info"><b>' . __('The recordset has access crits callbacks active. Final permisions depend on the result of the callbacks:') . '</b>';
		$output .= '<ul>';
	
		foreach ($callbacks as $callback) {
			$output .= '<li><b>' . $callback . '</b>: ';
				
			try {
				list($class_name, $method_name) = explode('::', $callback);
					
				$class = new ReflectionClass($class_name);
				$docblock  = new \phpDocumentor\Reflection\DocBlock($class->getMethod($method_name));
					
				$output .= '<span class="description">' . $docblock->getShortDescription() . '<br />' . $docblock->getLongDescription()->getContents() . '</span>';
			} catch (Exception $e) {
			}
				
			$output .= '</li>';
		}
	
		$output .= '</ul></div>';
	
		print($output);
	}
	public function delete_permissions_rule($id) {
		Utils_RecordBrowserCommon::delete_access($this->getTab(), $id);
		return false;
	}
	
	public function edit_permissions_rule($id = null, $clone = false) {
		if (! $this->check_section_access('permissions', self::ACCESS_FULL)) {
			print(__('You are not authorized to view for this page!'));
			
			return;
		}
		
        if ($this->is_back()) {
            return Base_BoxCommon::pop_main();
		}

		$all_clearances = [''=>'---'] + array_flip(Acl::get_clearance(true));

		$form = $this->init_module(Libs_QuickForm::module_name());
		$theme = $this->init_module(Base_Theme::module_name());
		
		$counts = [
			'clearance'=>5,
		];
		
		$actions = $this->get_permission_actions();
		$form->addElement('select', 'action', __('Action'), $actions);

		for ($i=0; $i < $counts['clearance']; $i++)
			$form->addElement('select', 'clearance_'.$i, __('Clearance'), $all_clearances);

		
		$form->addElement('multiselect', 'blocked_fields', null, $this->getRecordset()->getHash());

		$theme->assign('labels', [
			'and' => '<span class="joint">'.__('and').'</span>',
			'or' => '<span class="joint">'.__('or').'</span>',
			'caption' => $id?__('Edit permission rule'):__('Add permission rule'),
			'clearance' => __('Clearance requried'),
			'fields' => __('Field permissions'),
			'crits' => __('Criteria required'),
			'add_clearance' => __('Add clearance'),
			'add_or' => __('Add criteria (or)'),
			'add_and' => __('Add criteria (and)')
 		]);
		$current_clearance = 0;
        $crits = [];
        $defaults = [];
        if ($id!==null && $this->getTab()!='__RECORDSETS__' && !preg_match('/,/',$this->getTab())) {
			$row = DB::GetRow('SELECT * FROM '.$this->getTab().'_access AS acs WHERE id=%d', [$id]);
			
			$defaults['action'] = $row['action'];

			$crits = Utils_RecordBrowser_Crits::create(Utils_RecordBrowserCommon::unserialize_crits($row['crits']));
		
			$i = 0;
			$tmp = DB::GetAll('SELECT * FROM '.$this->getTab().'_access_clearance AS acs WHERE rule_id=%d', [$id]);
			foreach ($tmp as $t) {
				$defaults['clearance_'.$i] = $t['clearance'];
				$i++;
			}
			$current_clearance += $i-1;

			$defaults['blocked_fields'] = DB::GetCol('SELECT block_field FROM '.$this->getTab().'_access_fields AS acs WHERE rule_id=%d', [$id]);
		}
        $form->addElement('crits', 'qb_crits', __('Crits'), $this->getTab(), $crits);

        $form->setDefaults($defaults);
		
		if ($form->validate()) {
			$vals = $form->exportValues();
			$action = $vals['action'];

			$clearance = array();
			for ($i=0; $i<$counts['clearance']; $i++)
				if ($vals['clearance_'.$i]) $clearance[] = $vals['clearance_'.$i];

            $crits = $vals['qb_crits'];

			if ($id===null || $clone)
				Utils_RecordBrowserCommon::add_access($this->getTab(), $action, $clearance, $crits, $vals['blocked_fields']);
			else
				Utils_RecordBrowserCommon::update_access($this->getTab(), $id, $action, $clearance, $crits, $vals['blocked_fields']);
		
			return Base_BoxCommon::pop_main();
		}

		eval_js('Utils_RecordBrowser.permissions.set_field_access_titles ('.json_encode([
				'blocked_fields__from' => __('GRANT'),
				'blocked_fields__to' => __('DENY')
		]).')');
		eval_js('Utils_RecordBrowser.permissions.init_clearance('.$current_clearance.', '.$counts['clearance'].')');
		eval_js('Utils_RecordBrowser.permissions.crits_initialized = true;');
		
		$form->assign_theme('form', $theme);
		$theme->assign('counts', $counts);
		
		$theme->display('edit_permissions');
		Utils_ShortcutCommon::add(array('Ctrl','S'), 'function(){'.$form->get_submit_form_js().'}');
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		Base_ActionBarCommon::add('delete', __('Cancel'), $this->create_back_href());
	}
	
	private function get_permission_actions() {
		return array(
			'view'=>__('View'),
			'edit'=>__('Edit'),
			'add'=>__('Add'),
			'delete'=>__('Delete'),
			'print'=>__('Print'),
			'export'=>__('Export'),
			'selection'=>__('Selection')
		);
	}
	
	public function setTab($tab) {		
		Utils_RecordBrowser_Recordset::exists($tab);
		
		$this->set_module_variable('tab', $tab);
	
		$this->getRecordset(true);
	}
	
	public function getTab() {
		return $this->getRecordset()->getTab();
	}
	
	public function getRecordset($force = false) {
		if (!$this->recordset || $force) {
			$this->recordset = Utils_RecordBrowser_Recordset::create($this->get_module_variable('tab'), $force);
		}
		
		return $this->recordset;
	}
}
?>