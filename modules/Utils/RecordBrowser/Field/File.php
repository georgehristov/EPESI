<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Field_MultiSelect extends Utils_RecordBrowser_Field_Select {
	public function defaultValue() {
		return [];
	}	
	
	public static function decodeValue($value, $htmlspecialchars = true) {
		return Utils_RecordBrowserCommon::decode_multi($value);
	}
	
	public function prepareSqlValue(& $files) {
		$files = $this->decodeValue($files);
		if ($this['param']['max_files'] && count($files) > $this['param']['max_files']) {
			throw new Exception('Too many files in field ' . $this['id']);
		}
		$files = $this->encodeValue(Utils_FileStorageCommon::add_files($files));
		return true;
	}
	
	public function processAddedValue($value, $record) {
		// update backref
		$value = $this->decodeValue($value);
		Utils_FileStorageCommon::add_files($value, "rb:$this[tab]/$record[id]/$this[pkey]");
		
		return $value;
	}
	
	public function defaultQFfield($form, $mode, $default, $rb_obj, $display_callback_table = null) {
		if ($this->createQFfieldStatic($form, $mode, $default, $rb_obj)) return;
		
		$record_id = isset($rb_obj->record['id']) ? $rb_obj->record['id'] : 'new';
		$module_id = md5($rb_obj->tab . '/' . $record_id . '/' . $this->getId());
		/** @var Utils_FileUpload_Dropzone $dropzoneField */
		$dropzoneField = Utils_RecordBrowser::$rb_obj->init_module('Utils_FileUpload#Dropzone', null, $module_id);
		$default = $this->decodeValue($default);
		if ($default) {
			$files = [];
			foreach ($default as $filestorageId) {
				$meta = Utils_FileStorageCommon::meta($filestorageId);
				$arr = [
						'filename' => $meta['filename'],
						'type' => $meta['type'],
						'size' => $meta['size'],
				];
				$backref = substr($meta['backref'], 0, 3) == 'rb:' ? explode('/', substr($meta['backref'], 3)) : [];
				if (count($backref) === 3) {
					list ($br_tab, $br_record, $br_field) = $backref;
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
		if (isset($this['param']['max_files']) && $this['param']['max_files'] !== false) {
			$dropzoneField->set_max_files($this['param']['max_files']);
		}
		if (isset($this['param']['accepted_files']) && $this['param']['accepted_files'] !== false) {
			$dropzoneField->set_accepted_files($this['param']['accepted_files']);
		}
		$dropzoneField->add_to_form($form, $this->getId(), $this->getLabel());
	}
}
