<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_User extends Utils_RecordBrowser_Recordset_Field {
	public static function typeKey() {
		return 'user';
	}
	
	public static function typeLabel() {
		return _M('%s User', [EPESI]);
	}
	
	public function getSqlType() {
		return '%d';
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		if (!$v = $record[$desc['id']]) return '---';

		if (!is_numeric($v)) return $v;
		
		$login = Base_UserCommon::get_user_login($v);
		
		if (!$nolink && Acl::i_am_admin()) {
			$contact = CRM_ContactsCommon::get_contact_by_user_id($v);
			
			$login = Utils_RecordBrowserCommon::create_linked_text($login, 'contact', $contact);
		}
		
		if (! Base_UserCommon::is_active($v)) {
			$login = $login . ' [' . __('user inactive') . ']';
		}
		
		return $login;
	}
	
	public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
		$label = __('%s User', array(EPESI));
		if (!Acl::i_am_admin()) return;
		
		if ($mode=='view') {
			if (!$default) return;
			
			if (Acl::i_am_sa()) {
				Base_ActionBarCommon::add('settings', __('Log as user'), Module::create_href(['log_as_user'=>$default]));
				if (isset($_REQUEST['log_as_user']) && $_REQUEST['log_as_user'] == $default) {
					Acl::set_user($default, true); //tag who is logged
					Epesi::redirect();
					return;
				}
			}
			$form->addElement('static', $field, $label);
			$form->setDefaults([$field => self::defaultDisplayCallback(['login'=>$default], true, ['id'=>'login'])]);
			return;
		}
		
		$ret = DB::Execute('SELECT id, login FROM user_login ORDER BY login');
		$users = [''=>'---', 'new'=>'['.__('Create new user').']'];
		while ($row=$ret->FetchRow()) {
			$contact_id = Utils_RecordBrowserCommon::get_id('contact','login',$row['id']);
			if ($contact_id===false || $contact_id===null || ($row['id']===$default && $mode!='add'))
				if (Acl::i_am_admin() || $row['id']==Acl::get_user())
					$users[$row['id']] = $row['login'];
		}
		
		$form->addElement('select', $field, $label, $users, ['id'=>'crm_contacts_select_user']);
		$form->setDefaults([$field=>$default]);
		
		if ($default === '') {
			eval_js('new_user_textfield = function(){'.
					'($("crm_contacts_select_user").value=="new"?"":"none");'.
					'$("username").up("tr").style.display = $("set_password").up("tr").style.display = $("confirm_password").up("tr").style.display = $("_access__data").up("tr").style.display = ($("crm_contacts_select_user").value==""?"none":"");'.
					'if ($("contact_admin")) $("contact_admin").up("tr").style.display = ($("crm_contacts_select_user").value==""?"none":"");'.
					'}');
			eval_js('new_user_textfield();');
			eval_js('Event.observe("crm_contacts_select_user","change",function(){new_user_textfield();});');
		}
		else {
			$form->freeze($field);
		}
		
		if ($default) {
			eval_js('$("_login__data").up("tr").style.display = "none";');
		}			
	}
}
