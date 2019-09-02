<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Base_Print_Printer
{
	protected $data = [];
	
    /** @var Base_Print_Document */
	protected $document;

    /** @var Base_Print_Template */
	protected $template;
    
	public static function instance($class, $data = []) {
		if (!$class || !class_exists($class)) {
			throw new ErrorException('Wrong printer classname');
		}
		if (!is_subclass_of($class, Base_Print_Printer::class)) {
			throw new ErrorException('Printer class has to extend Base_Print_Printer');
		}
		
		return new $class($data);
    }
    
    /**
     * @param array $data
     * @param string $class     * 
     * @return Base_Print_Printer
     */
	public static function create($data = [], $class = null) {
		$class = $class?: static::class;
		
    	return new $class($data);
    }
    
    public function __construct($data = []) {
    	$this->set_data($data);
    }
    
    public function print() {
    	print $this->get_document()->get_contents();
    }
    
    public function save($file) {
    	file_put_contents($file, $this->get_document()->contents());
    }
    
    /**
     * Document name is a string used to identify the document type printed
     * by your printer class.
     *
     * @return string NOT translated document name, mark to translate with _M()
     */
    abstract public function document_name();

    /**
     * This method is responsible for printing document.
     *
     * Example code:
     * <code>
     * $section = $this->new_section();
     * $section->assign('data', $data);
     * $this->print_section('section_name', $section);
     * </code>
     * @param mixed $data This is a value that is passed to get_href method
     * @see Base_Print_Printer::new_section()
     * @see Base_Print_Printer::print_section()
     * @see Base_Print_Printer::set_footer()
     * @return null It doesn't have to return value
     */
    abstract protected function print_document();

    protected function additional_params() {
    	return array();
    }
    
    /**
     * @return array
     */
    public function default_templates()
    {
        return array();
    }
    
    /**
     * @return array
     */
    public function enabled_templates()
    {
    	/**
    	 * @var Base_Print_Template $template
    	 */
    	$ret = [];
    	foreach ($this->default_templates() as $id => $template) {
    		if (!$template->get_name()) {
    			$template->set_name($id);
    		}
    		
    		if (Base_PrintCommon::is_template_disabled(get_class($this), $id)) continue;
    		
    		$ret[$id] = $template;
    	}
    	return $ret;
    }

    /**
     * Return array of sample data that may be passed to the printer.
     * This data may be used as example in the future or external modules.
     *
     * @return array Sample data
     */
    public function sample_data($opts)
    {
        return array();
    }

    /**
     * Get href to call a print method
     *
     * Example:
     * <code>
     *  $printer = new Some_Printer_class();
     *  Base_ActionBarCommon::add('print', __('Print'), $printer->get_href($data));
     * </code>
     *
     * @return mixed|string By default it should be a string with href="...",
     * but you can override returned value by registering your own callback
     * with Base_PrintCommon::set_print_href_callback method
     */
    public function get_href()
    {
        return $this->get_print_href();
    }
    
    /**
     * Get print href string. This method calls custom print href, that may
     * return array or string. If array is returned then it will be used
     * to create a leightbox select with buttons and it has to be the array
     * of arrays('href' => .. , 'label' => ..)
     *
     * @return string href to open printed document or to open leightbox
     *                with buttons if multiple templates are enabled
     */
    public final function get_print_href()
    {
    	$links = $this->get_print_template_links();
    	
    	if (!is_array($links)) return $links;
    	
    	return count($links) > 1? Base_PrintCommon::choose_box_href($links): reset($links)['href'];
    }
    
    /**
     * Get print available templates. This method calls custom print href, that should
     * return array with href,label and template keys.
     */
    public final function get_print_template_links()
    {
    	$ret = array();
    	$callback = Base_PrintCommon::get_print_href_callback();
    	if ($callback && is_callable($callback)) {
    		$passed_params = func_get_args();
    		$ret = call_user_func_array($callback, $passed_params);
    	}
    	if (is_array($ret)) {
    		foreach ($this->enabled_templates() as $id => $template) {
    			$ret[] = [
    					'href' => $this->default_href($id),
    					'label' => _V($template->get_name()?: $id),
    			];
    		}
    	}
    	return $ret;
    }
    
    public function default_href($template_id)
    {
    	$params = array_merge($this->additional_params(), [
    			'cid' => CID,
    			'data' => $this->get_data(),
    			'ut' => time(),
    			'printer' => get_class($this),
    			'tpl' => $template_id
    	]);
    	
    	$url = Base_PrintCommon::Instance()->get_module_dir() . 'Handle.php?' . http_build_query($params);
    	
    	return ' target="_blank" href="' . $url . '" ';
    }

    /**
     * Create a new section of document.
     *
     * @see Base_Print_Printer::print_document
     *
     * @return Smarty
     */
    protected function new_section()
    {
        return Base_ThemeCommon::init_smarty();
    }

    /**
     * Get document object used by printer.
     *
     * @return Base_Print_Document
     */
    public function get_document()
    {
    	if (!$this->document) {
    		$this->document = $this->get_template()->create_document($this->get_data());
    		
    		$this->print_document();
    	}
    	
        return $this->document;
    }

    /**
     * Set template used by printer.
     *
     * @param Base_Print_Template|string $template
     */
    public function set_template($template, $data = [])
    {
    	if (!is_object($template)) {
    		$templates = $this->default_templates();
    		
    		$id = $template?: key($templates);
    		
    		if (!isset($templates[$id])) {
    			throw new ErrorException('Wrong template');
    		}
    		
    		$template = $templates[$id];
    	}
    	
        $this->template = $template;        
        
        return $this;
    }

    /**
     * Get template used by printer.
     *
     * @return Base_Print_Template
     */
    public function get_template()
    {    	
    	if (!$this->template) {
	    	$templates = $this->default_templates();
	    	$this->set_template(reset($templates));
    	}
    	
        return $this->template;
    }

    /**
     * Print template section to the document.
     *
     * @see Base_Print_Printer::print_document
     *
     * @param string $template Section identifier
     * @param Smarty $section Section object with assigned data
     */
    protected function print_section($template, Smarty $section)
    {
        $text = $this->get_template()->print_section($template, $section);
        $this->print_text($text);
    }

    /**
     * Set footer section. Footer is handled separately from the other sections,
     * because it's built-in support in document object.
     *
     * Example:
     * <code>
     *   $section = $this->new_section();
     *   $section->assign('data', $data); // optional
     *   $this->set_footer($section);
     * </code>
     *
     * @param Smarty $section Section to use as footer
     */
    protected function set_footer(Smarty $section)
    {
        $text = $this->get_template()->print_section('footer', $section);
        $this->get_document()->set_footer($text);
    }

    /**
     * Write text directly to the document.
     *
     * @param $text
     */
    protected function print_text($text)
    {
        $this->get_document()->write_text($text);
    }

    /**
     * Just return file to be used as a filename during download or save as.
     * @return string Printed filename without extension.
     */
    public function get_printed_filename_suffix()
    {
        return '';
    }

	public function get_data() {
		return $this->data;
	}

	public function set_data($data) {
		$this->data = $data;
		
		return $this;
	}


}
