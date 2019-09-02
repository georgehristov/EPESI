<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Print_Document_PDF extends Base_Print_Document
{
    private $pdf;
    private $content_length;
    private $printed_by=true;
    private $subject='';
    private $title='';
    private $logo=null;
    private $contents = null;

    private $margin_top;
    private $margin_left;
    private $margin_right;
    private $margin_bottom;
    private $margin_footer;
    
    private $font_family;
    private $font_style;
    private $font_size;

    private $orientation = 'P';
    
    public function __construct($config = array()) {
        if(!is_array($config)) return;
        
		$this->printed_by = ! isset($config['printed_by']) || $config['printed_by'];
		$this->title = $config['title'] ?? '';
		$this->subject = $config['subject'] ?? '';
		$this->logo = $config['logo'] ?? null;
		$this->margin_top = $config['margin_top'] ?? null;
		$this->margin_left = $config['margin_left'] ?? null;
		$this->margin_right = $config['margin_right'] ?? $this->margin_left;
		$this->margin_bottom = $config['margin_bottom'] ?? null;
		$this->margin_footer = $config['margin_footer'] ?? null;
		$this->orientation = $config['orientation'] ?? null;
		$this->font_family = $config['font_family'] ?? Libs_TCPDFCommon::$default_font;
		$this->font_style = $config['font_style'] ?? '';
		$this->font_size = $config['font_size'] ?? 0;
	}

    public function document_type_name()
    {
        return 'PDF';
    }
    
    public static function type()
    {
        return 'print';
    }
    
    public function init($data)
    {
        $this->pdf = Libs_TCPDFCommon::new_pdf($this->orientation);
        
        $this->set_margins();
        
        Libs_TCPDFCommon::prepare_header($this->pdf,$this->title,$this->subject,$this->printed_by,$this->logo);
        Libs_TCPDFCommon::add_page($this->pdf);
        Libs_TCPDFCommon::SetFont($this->pdf, $this->font_family, $this->font_style, $this->font_size);
        
        $this->set_filename_extension('pdf');
    }

    public function write_text($html)
    {
        Libs_TCPDFCommon::writeHTML($this->pdf, $html, false);
    }

    public function contents()
    {
        if (!$this->contents) {           
	        $this->append_footers();
	        $this->contents = Libs_TCPDFCommon::output($this->pdf);
	        $this->content_length = strlen($this->contents);
        }
        return $this->contents;
    }

    protected function set_margins()
    {
       	$this->margin_top = $this->margin_top?? PDF_MARGIN_TOP;

       	$this->margin_left = $this->margin_left?? PDF_MARGIN_LEFT;
       	
       	$this->margin_right = $this->margin_right?? $this->margin_left;

        $this->pdf->SetMargins($this->margin_left, $this->margin_top, $this->margin_right, true);
        
        if (isset($this->margin_bottom)) {
            $this->pdf->SetAutoPageBreak(true, $this->margin_bottom);
        }
        if (isset($this->margin_footer)) {
            $this->pdf->setFooterMargin($this->margin_footer);
        }
    }

    protected function append_footers()
    {
        if (!$this->get_footer()) {
            return;
        }
        $margins = $this->pdf->getOriginalMargins();
        $pages_total = $this->pdf->getNumPages();
        $page_width = $this->pdf->getPageWidth();
        $footer_width = $page_width - $margins['left'] - $margins['right'];
        $footer_y = $this->pdf->getPageHeight() - $this->pdf->getFooterMargin() + 5;
        for ($page = 1; $page <= $pages_total; $page++) {
            $this->pdf->SetPage($page);
            $this->pdf->SetAutoPageBreak(false);
            $this->pdf->WriteHTMLCell($footer_width, 1,
                                      $margins['left'], $footer_y,
                                      $this->get_footer(), false, 0, false);
        }
    }

    protected function find_footer_height()
    {
        $footer_margin_orig = $this->pdf->getFooterMargin();
        $bottom_margin = $this->pdf->getBreakMargin();
        $footer_margin = $footer_margin_orig;
        $this->pdf->SetAutoPageBreak(true, $footer_margin);
        $text = $this->get_footer();
        $margins = $this->pdf->getOriginalMargins();
        $page_height = $this->pdf->getPageHeight();
        $page_width = $this->pdf->getPageWidth();
        $footer_width = $page_width - $margins['left'] - $margins['right'];
        $success = false;
        while (!$success) {
            $tmppdf = clone($this->pdf);
            $pages = $tmppdf->getNumPages();
            if ($footer_margin * 2 > $page_height) {
                throw new ErrorException('Your footer is too long');
            }
            $footer_y = $page_height - $footer_margin;
            $tmppdf->SetPage($tmppdf->getNumPages());
            $tmppdf->WriteHTMLCell($footer_width, 1,
                                   $margins['left'], $footer_y,
                                   $text, false, 0, false);
            if ($pages == $tmppdf->getNumPages()) {
                $success = true;
            } else {
                $footer_margin += 2;
            }
            unset($tmppdf);
        }
        // add some static value to set more space for the page number line
        $footer_margin += 5;
        if ($bottom_margin > $footer_margin) {
            $footer_margin = $bottom_margin;
        }
        $this->pdf->setFooterMargin($footer_margin);
        $this->pdf->SetAutoPageBreak(true, $footer_margin);
    }

    public function set_footer($text)
    {
        parent::set_footer($text);
        $this->find_footer_height();
    }

    public function http_headers()
    {
        parent::http_headers();
        if ($this->content_length === null) {
            throw new ErrorException('Call contents first to calculate output length');
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . $this->content_length);
        header('Cache-Control: no-cache');
        header('Content-disposition: inline; filename="' . $this->get_filename_with_extension() . '"');
    }
}
