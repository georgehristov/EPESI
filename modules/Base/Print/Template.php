<?php defined("_VALID_ACCESS") || die('Direct access forbidden');


class Base_Print_Template
{
    /**
     * @var Base_Print_Template_Section[]
     */
	protected $sections = array();
	protected $name;

    /**
     * @param array $data
     * @return Base_Print_Document
     */
    public function create_document($data = [])
    {
    	return Base_Print_Document::create($this->document_class(), $this->document_config(), $data)->set_template($this);
    }
    
    public function __construct($config = []) {}
    
    /**
     * @return string - document class
     */
    public function document_class() {
    	return Base_Print_Document_PDF::class;
    }
    
    /**
     * @return array - document config array
     */
    public function document_config() {
    	return [];
    }

    public function get_document_type() {
    	return call_user_func([$this->document_class(), 'type']);
    }
    
    public function print_section($section_name, $section)
    {
        if (!$this->isset_section_template($section_name)) {
            return '';
        }

        return $this->get_section_template($section_name)->fetch($section);
    }

    public function get_section_template($name)
    {
        return $this->sections[$name];
    }

    public function set_section_template($name, Base_Print_Template_Section $template)
    {
        $this->sections[$name] = $template;
    }

    public function isset_section_template($name)
    {
        return isset($this->sections[$name]);
    }
    
    public function set_name($name)
    {
    	$this->name = $name;
    	
        return $this;
    }
    
    public function get_name()
    {
    	return $this->name;
    }

}
