<?php
/**
 * HTML class for common data
 *
 * @author       Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CommonData
 */
require_once('HTML/QuickForm/select.php');

class HTML_QuickForm_Dropzone extends HTML_QuickForm_static {
	var $files = null;
	var $maxFiles = null;
	var $acceptedFiles = null;
	
	function HTML_QuickForm_Dropzone($elementName=null, $elementLabel=null, $files=null, $options=null, $attributes=null) {
		$this->HTML_QuickForm_static($elementName, $elementLabel, array(), $attributes);
		$this->_persistantFreeze = true;
		$this->_type = 'dropzone';
		$this->_appendName = true;
		
		$files = [];
		foreach ( $files as $filestorageId ) {
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
		$this->files = $files;

		if (isset($options['max_files']) && $options['max_files'] !== false) {
			$this->maxFiles = $options['max_files'];
		}
		if (isset($options['accepted_files']) && $options['accepted_files'] !== false) {
			$this->acceptedFiles = $options['accepted_files'];
		}
	} //end constructor

	function toHtml() {
		$this->check_clear();
		$identifier = 'dropzone_' . $this->getName();
		$content = "<div id=\"{$identifier}\" class=\"dropzone\"></div>";
		$dir = 'modules/Utils/FileUpload/';
		load_css($dir . 'theme/dropzone.css');
		load_css(EPESI_LOCAL_DIR . '/vendor/enyo/dropzone/dist/min/basic.min.css');
		load_css(EPESI_LOCAL_DIR . '/vendor/enyo/dropzone/dist/min/dropzone.min.css');
		load_js(EPESI_LOCAL_DIR . '/vendor/enyo/dropzone/dist/min/dropzone.min.js');
		$query = http_build_query(array('cid' => CID, 'path' => $this->get_path()));
		$files = $this->get_uploaded_files();
		$files_js = '';
		if (isset($files['add'])) {
			foreach ($files['add'] as $file) {
				$js_file = json_encode(array('name' => $file['name'], 'size' => $file['size']));
				$thumbnail = strpos($file['type'], 'image/') === 0 ? 'dz.emit("thumbnail", mockFile, ' . json_encode(strval($file['file'])) . ');' : '';
				$files_js .= '(function(dz) {
                    var mockFile = ' . $js_file . ';
                    dz.emit("addedfile", mockFile);
                    ' . $thumbnail . '
                    dz.emit("complete", mockFile);
                })(dz);';
			}
		}
		if (isset($files['existing'])) {
			foreach ($files['existing'] as $file) {
				if (isset($files['delete'][$file['file_id']])) continue;
				$js_file = json_encode(array('name' => $file['name'], 'size' => $file['size']));
				$thumbnail = isset($file['file']) && strpos($file['type'], 'image/') === 0 ? 'dz.createThumbnailFromUrl(mockFile, ' . json_encode(strval($file['file'])) . ');' : '';
				$files_js .= '(function(dz) {
                    var mockFile = ' . $js_file . ';
                    dz.emit("addedfile", mockFile);
                    ' . $thumbnail . '
                    dz.emit("complete", mockFile);
                })(dz);';
			}
		}
		$options = [
				'url' => $dir . 'dropzoneupload.php?' . $query,
				'uploadMultiple' => true,
				'addRemoveLinks' => true,
				'maxFiles' => $this->maxFiles,
				'acceptedFiles' => $this->acceptedFiles,
				'dictDefaultMessage' => __('Drop files here or click to upload')
		];
		eval_js('jq(".dz-hidden-input").remove(); if (document.querySelector("#' . $identifier . '") && !document.querySelector("#' . $identifier . '").dropzone) {
            var dz = new Dropzone("#' . $identifier . '", '.json_encode($options).');
            dz.on("removedfile", function(file) {
                   jq.ajax({
                    type:\'POST\',
                    url: this.options.url,
                    data: {
                      delete:file.name,
                    }
                  });
             });' . $files_js . '
             }');
		
		$this->_text = $content;

	    return parent::toHtml();
	}
} //end class HTML_QuickForm_commondata
?>
