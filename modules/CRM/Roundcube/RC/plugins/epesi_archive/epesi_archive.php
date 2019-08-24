<?php
class epesi_archive extends rcube_plugin
{
  public $task = 'mail';
  public $archive_mbox = 'CRM Archive';
  public $archive_sent_mbox = 'CRM Archive Sent';

  function init()
  {
    global $account;

    if($account['f_imap_root']) {
        $this->archive_mbox = rtrim($account['f_imap_root'],'.').'.'.$this->archive_mbox;
        $this->archive_sent_mbox = rtrim($account['f_imap_root'],'.').'.'.$this->archive_sent_mbox;
    }

    $rcmail = rcmail::get_instance();
    $this->register_action('plugin.epesi_archive', array($this, 'request_action'));

    //register hook to archive just sent mail
    $this->add_hook('attachments_cleanup', array($this, 'auto_archive'));
    if(!isset($_SESSION['epesi_auto_archive']))
        $_SESSION['epesi_auto_archive'] = isset($account['f_archive_on_sending']) && $account['f_archive_on_sending']?1:0;

    $this->include_script('archive.js');
    $skin_path = $rcmail->config->get('skin_path');
    if (is_file($this->home . "/$skin_path/archive.css"))
        $this->include_stylesheet("$skin_path/archive.css");
    $this->add_texts('localization', true);

    $this->add_hook('messages_list', array($this, 'list_messages'));

    if($rcmail->action == 'compose') {
        $this->add_button(
        array(
            'command' => 'plugin.epesi_auto_archive',
            'imageact' => $skin_path.'/archive_'.($_SESSION['epesi_auto_archive']?'act':'pas').'.png',
            'title' => 'buttontitle_compose',
            'domain' => $this->ID,
            'id'=>'epesi_auto_archive_button'
        ),
        'toolbar');
    }

    if ($rcmail->action == '' || $rcmail->action == 'show') {
      $this->add_button(
        array(
            'command' => 'plugin.epesi_archive',
            'imagepas' => $skin_path.'/archive_pas.png',
            'imageact' => $skin_path.'/archive_act.png',
            'title' => 'buttontitle',
            'domain' => $this->ID,
        ),
        'toolbar');

      if(!isset($account['f_use_epesi_archive_directories']) || !$account['f_use_epesi_archive_directories']) return;

      // register hook to localize the archive folder
      $this->add_hook('render_mailboxlist', array($this, 'render_mailboxlist'));

      // set env variable for client
      $rcmail->output->set_env('archive_mailbox', $this->archive_mbox);
      $rcmail->output->set_env('archive_sent_mailbox', $this->archive_sent_mbox);

      // add archive folder to the list of default mailboxes
      if (($default_folders = $rcmail->config->get('default_imap_folders')) && !in_array($this->archive_mbox, $default_folders)) {
        $default_folders[] = $this->archive_mbox;
        $rcmail->config->set('default_imap_folders', $default_folders);
      }

      if (($default_folders = $rcmail->config->get('default_imap_folders')) && !in_array($this->archive_sent_mbox, $default_folders)) {
        $default_folders[] = $this->archive_sent_mbox;
        $rcmail->config->set('default_imap_folders', $default_folders);
      }

//      if(!$rcmail->config->get('create_default_folders'))
      $this->add_hook('storage_folders', array($this, 'add_mailbox'));
    }
  }

  function render_mailboxlist($p)
  {
    // set localized name for the configured archive folder
    if (isset($p['list'][$this->archive_mbox]))
        $p['list'][$this->archive_mbox]['name'] = $this->gettext('archivefolder');

    if (isset($p['list'][$this->archive_sent_mbox]))
        $p['list'][$this->archive_sent_mbox]['name'] = $this->gettext('archivesentfolder');

    return $p;
  }

  function look_contact($addr) {
    global $E_SESSION;
    return CRM_Mail_AddressCommon::get_records_from_address($addr,$E_SESSION['user']);
  }

  function request_action()
  {
    $this->add_texts('localization');
    $rcmail = rcmail::get_instance();

    if (isset($_POST['_enabled_auto_archive'])) { //auto archive toggle
        $_SESSION['epesi_auto_archive'] = get_input_value('_enabled_auto_archive', RCUBE_INPUT_POST);
        return;
    }

    //archive button
    $uids = get_input_value('_uid', RCUBE_INPUT_POST);
    $mbox = get_input_value('_mbox', RCUBE_INPUT_POST);
    if(in_array($mbox, [$this->archive_mbox, $this->archive_sent_mbox, $rcmail->config->get('drafts_mbox')])) {
        $rcmail->output->show_message($this->gettext('invalidfolder'), 'error');
        return;
    }
    $sent_mbox = ($rcmail->config->get('sent_mbox')==$mbox);

    $uids = explode(',', $uids);
    if($this->archive($uids)) {
		global $account;
		if(isset($account['f_use_epesi_archive_directories']) && $account['f_use_epesi_archive_directories']) {
			$rcmail->output->command('move_messages', $sent_mbox? $this->archive_sent_mbox: $this->archive_mbox);
       	}
        $rcmail->output->show_message($this->gettext('archived'), 'confirmation');
    }
    global $E_SESSION_ID,$E_SESSION;

    EpesiSession::set($E_SESSION_ID, $E_SESSION);
  }

  private function archive($uids, $verbose=true) {
    global $E_SESSION;
    $rcmail = rcmail::get_instance();
    $path = getcwd();
    chdir(str_replace(['/modules/CRM/Roundcube/RC','\\modules\\CRM\\Roundcube\\RC'], '', $path));

    $uids = is_array($uids)? $uids: $uids->get();
    
    $_SESSION['force_archive'] = $_SESSION['force_archive']?? [];
    
    $epesi_mails = [];  
    foreach($uids as $uid) {
    	
        $message = new rcube_message($uid);
        if ($message===null || empty($message->headers)) {
            if($verbose) {
                $rcmail->output->show_message('messageopenerror', 'error');
            }
            return false;
        }
        
        $message_id = str_replace(['<','>'], '', $message->get_header('MESSAGE-ID'));
        if(Utils_RecordBrowserCommon::get_records_count('crm_mails', compact('message_id'))) {
        	$rcmail->output->show_message($this->gettext('archived_duplicate'), 'warning');
        	return false;
        }
        
        if (!$emails = $this->parse_emails($message, $verbose)) return false;

        $headers = $this->parse_headers($message, $verbose);
        
        $content = $this->parse_content($message, $verbose);
        
        $employee = CRM_ContactsCommon::get_contact_by_user_id($E_SESSION['user']);
        
        $epesi_mails[] = CRM_MailCommon::archive_message(array_merge([
        		'message_id' => $message_id,
        		'related' => $message->get_header('REFERENCES'),
        		'contacts' => $this->look_contact(array_merge($emails['from'], $emails['to'])),
        		'date' => $message->headers->timestamp,
        		'subject' => substr($message->subject, 0, 256),
        		'body' => $content['body'],
        		'headers' => implode("\n", $headers),
        		'from' => rcube_mime::decode_mime_string((string) $message->get_header('FROM')),
        		'to' => rcube_mime::decode_mime_string((string) $message->get_header('TO')),
        		'employee' => $employee['id']?? ''
        ], $content['files']));        
    }

    $E_SESSION['crm_mails_cp'] = $epesi_mails;

    chdir($path);
    return true;
  }

  private function parse_headers(rcube_message $message, $verbose = true) {
  	$headers = [];
  	foreach($message->headers as $kk => $v) {
  		if(is_string($v) && !in_array($kk, ['from', 'to', 'body_structure']))
  			$headers[] = $kk.': '.rcube_mime::decode_mime_string((string) $v);
  	}
  	return $headers;
  }
  
  private function parse_emails(rcube_message $message, $verbose = true) {
  	$rcmail = rcmail::get_instance();
  	
  	$uid = $message->uid;
  	
  	$ret = [];
  	foreach (['from', 'to'] as $key) {
  		$ret[$key] = array_filter(array_column(rcube_mime::decode_address_list($message->get_header($key)), 'mailto'));
  	}
  	
  	if(!$ret['from'] && !isset($_SESSION['force_archive'][$uid])) {
  		$_SESSION['force_archive'][$uid] = 1;
  		if ($verbose) {
  			$rcmail->output->show_message($this->gettext('contactnotfound'), 'error');
  		}
  		return false;
  	}
  	
  	return $ret;
  }
  
  private function parse_content(rcube_message $message, $verbose = true) {
  	$rcmail = rcmail::get_instance();
  	
  	$uid = $message->uid;
  	
  	$files = [
  			'attachments' => [],
  			'media' => []
  	];
  	  	
  	if ($message->has_html_part()) {
  		$cid_map = [];
  		foreach ($message->mime_parts as $mime_id => $mime) {
  			$mimetype = strtolower($mime->ctype_primary . '/' . $mime->ctype_secondary);
  			
  			if ($mimetype != 'text/html') continue;
  			
  			$body = $rcmail->storage->get_message_part($uid, $mime_id, $mime);
  			
  			$cid_map = $mime->replaces?? [];
  			
  			break;
  		}
  	} else {
  		$body = '<pre>' . $message->first_text_part() . '</pre>';
  	}  	
  	  	
  	foreach ($message->mime_parts as $mime) {
  		if (!$mime->disposition) continue;
  		
  		$filename = $mime->filename?: $mime->content_id;
  		
  		$key = isset($cid_map['cid:' . $mime->content_id])? 'media': 'attachments';
  		
  		$files[$key][$mime->mime_id] = Utils_FileStorageCommon::write_content($filename, $message->get_part_body($mime->mime_id));
  	}
  	
  	if ($cid_map) {
  		foreach($cid_map as $k => &$v) {
  			$matches = null;
  			if(preg_match('/_part=(.*?)&/', $v, $matches)) {
  				$mid = $matches[1];
  				if(isset($files['media'][$mid])) {
  					$url = Utils_FileStorageCommon::get_default_action_urls($files['media'][$mid]);
  					
  					$v = $url['inline']?? '';
  				}
  			} else {
  				unset($cid_map[$k]);
  			}
  		}
  		
  		$body = rcmail_wash_html($body, ['safe' => true, 'inline_html' => true], $cid_map);
  	}
  	
  	return compact('body', 'files');
  }
  
  function add_mailbox($p) {
    if($p['root']=='' && $p['name']=='*') {
        $rcmail = rcmail::get_instance();

        if(!$rcmail->storage->folder_exists($this->archive_mbox)) {
            $old = str_replace('CRM','Epesi',$this->archive_mbox);
            if($rcmail->storage->folder_exists($old)) {
                $rcmail->storage->rename_folder($old,$this->archive_mbox);
            } else
                $rcmail->storage->create_folder($this->archive_mbox,true);
        } elseif(!$rcmail->storage->folder_exists($this->archive_mbox,true))
            $rcmail->storage->subscribe($this->archive_mbox);

        if(!$rcmail->storage->folder_exists($this->archive_sent_mbox)) {
            $old = str_replace('CRM','Epesi',$this->archive_sent_mbox);
            if($rcmail->storage->folder_exists($old)) {
                $rcmail->storage->rename_folder($old,$this->archive_sent_mbox);
            } else
                $rcmail->storage->create_folder($this->archive_sent_mbox,true);
        } elseif(!$rcmail->storage->folder_exists($this->archive_sent_mbox,true))
            $rcmail->storage->subscribe($this->archive_sent_mbox);
    }
  }

  //on message sending
  function auto_archive() {
    if(!$_SESSION['epesi_auto_archive']) return;
    unset($_SESSION['epesi_auto_archive']);

    global $store_folder,$saved,$message_id,$store_target;
    $IMAP = $imap = rcmail::get_instance()->storage;
    if(!$store_folder || !$saved) return;
    $rcmail = rcmail::get_instance();

    $msgid = strtr($message_id, array('>' => '', '<' => ''));
    $old_mbox = $IMAP->get_folder();

    $IMAP->set_folder($store_target);
    $uids = $IMAP->search_once('', 'HEADER Message-ID '.$msgid, true);
    if($uids->is_empty()) return;

    $archived = $this->archive($uids,false);

    global $account;
    if($archived && isset($account['f_use_epesi_archive_directories']) && $account['f_use_epesi_archive_directories']) {
        $rcmail->output->command('set_env', 'uid', $uids->get_element(0));
        $rcmail->output->command('set_env', 'mailbox',$store_target);
        $rcmail->output->command('move_messages', $this->archive_sent_mbox);
    }

    $IMAP->set_folder($old_mbox);

    if($archived) {
        $rcmail->output->show_message($this->gettext('archived'), 'confirmation');
    }
  }

  function list_messages($p) {
    $IMAP = $imap = rcmail::get_instance()->storage;
    $mbox = $IMAP->get_folder();
    if(preg_match('/CRM Archive Sent$/i',$mbox)) {
        foreach($p['cols'] as &$c) {
            if($c=='from') $c = 'to';
        }
    }
    return $p;
  }
}
