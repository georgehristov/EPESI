<?php

abstract class Base_Print_Document
{
    protected $template;
    protected $filename;
    protected $footer;
    protected $file_extension;

    abstract public function document_type_name();

    /**
     * @param string $class
     * @param array $config
     * @param array $data
     * @return Base_Print_Document
     */
    public static function create($class, $config = [], $data= [])
    {
    	$document = new $class($config);
    	
    	$document->init($data);

    	return $document;
    }
    
    public function init($data) {}

    public function set_template($template)
    {
    	$this->template = $template;

    	return $this;
    }

    public function get_template()
    {
    	return $this->template;
    }
    
    public function set_filename($filename)
    {
        $this->filename = $filename;
        
        return $this;
    }

    public function get_filename()
    {
        return $this->filename?: md5(microtime(true));
    }

    public function set_filename_extension($extension)
    {
        $this->file_extension = $extension;
        
        return $this;
    }

    public function get_filename_extension()
    {
        return $this->file_extension;
    }

    public function get_filename_with_extension()
    {
        return $this->get_filename() . '.' . $this->get_filename_extension();
    }

    abstract public function write_text($text);

    /**
     * @return string - the document contents
     */
    abstract public function contents();
    
    /**
     * @return string - export|print
     */
    abstract public static function type();
    
    public function http_headers() {}
    
    public function get_contents() {
    	$contents = $this->contents();
    	
    	$this->http_headers();
    	
    	return $contents;
    }

    public function set_footer($text)
    {
        $this->footer = $text;
        
        return $this;
    }

    public function get_footer()
    {
        return $this->footer;
    }
}