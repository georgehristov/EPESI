<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Field_File extends Utils_RecordBrowser_Recordset_Field_MultiSelect {
	
	public static function typeKey() {
		return 'file';
	}
	
	public static function typeLabel() {
		return _M('File');
	}
	
	public function processAdd($values) {
		$files = $this->decodeValue($values[$this->getId()]);
		
		if ($this['param']['max_files'] && count($files) > $this['param']['max_files']) {
			throw new Exception('Too many files in field ' . $this['id']);
		}
		
		$values[$this->getId()] = $this->encodeValue(Utils_FileStorageCommon::add_files($files));
		
		return $values;
	}
	
	public function processAdded($values) {
		// update backref
		$value = $this->decodeValue($values[$this->getId()]);
		
		Utils_FileStorageCommon::add_files($value, "rb:$this[tab]/$values[id]/$this[pkey]");
		
		return $values;
	}
	
	public static function defaultDisplayCallback($record, $nolink = false, $desc = null, $tab = null) {
		$labels = [];
		$inline_nodes = [];
		$fileStorageIds = self::decode_multi($record[$desc['id']]);
		$fileHandler = new Utils_RecordBrowser_FileActionHandler();
		foreach($fileStorageIds as $fileStorageId) {
			if(!empty($fileStorageId)) {
				$actions = $fileHandler->getActionUrlsRB($fileStorageId, $tab, $record['id'], $desc['id']);
				$labels[]= Utils_FileStorageCommon::get_file_label($fileStorageId, $nolink, true, $actions);
				$inline_nodes[]= Utils_FileStorageCommon::get_file_inline_node($fileStorageId, $actions);
			}
		}
		$inline_nodes = array_filter($inline_nodes);
		
		return implode('<br>', $labels) . ($inline_nodes? '<hr>': '') . implode('<hr>', $inline_nodes);
	}
	
	public static function defaultQFfieldCallback($form, $field, $label, $mode, $default, $desc, $rb_obj) {
		if (self::createQFfieldStatic($form, $field, $label, $mode, $default, $desc, $rb_obj))
			return;
		
		$record_id = $rb_obj->record['id']?? 'new';
		$module_id = md5($desc->getTab() . '/' . $record_id . '/' . $desc->getId());
		/** @var Utils_FileUpload_Dropzone $dropzoneField */
		$dropzoneField = Utils_RecordBrowser::$rb_obj->init_module('Utils_FileUpload#Dropzone', null, $module_id);

		if ($default = $desc->decodeValue($default)) {
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
		$dropzoneField->add_to_form($form, $desc->getId(), $desc->getQFfieldLabel());
	}   
}
