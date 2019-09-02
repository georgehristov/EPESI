<?php
/**
 *
 * @author     Adam Bukowski <abukowski@telaxus.com>
 * @copyright  Telaxus LLC
 * @license    MIT
 * @version    1.5.0
 * @package    epesi-base
 * @subpackage Print
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_PrintCommon extends ModuleCommon
{

    public static function admin_caption()
    {
        return array('label'   => __('Print Templates'),
                     'section' => __('Features Configuration'));
    }

    /**
     * Create printer object, but check if classname is a proper printer class.
     *
     * @param string $printer_classname Printer classname
     *
     * @return Base_Print_Printer Printer object
     * @throws ErrorException When wrong classname is supplied
     * @deprecated use Base_Print_Printer::instance instead
     */
    public static function printer_instance($printer_classname)
    {
        return Base_Print_Printer::instance($printer_classname);
    }

    /**
     * Create document object, but check if classname is a proper class.
     *
     * @param string $document_classname Document classname
     * @param mixed  $_                  Optional parameters to the document constructor
     *
     * @return Base_Print_Document Document object
     * @throws ErrorException When wrong classname is supplied
     */
    public static function document_instance($document_classname, $_ = null)
    {
        if (!$document_classname || !class_exists($document_classname)) {
            throw new ErrorException('Wrong document classname');
        }
        if (!is_subclass_of($document_classname, 'Base_Print_Document')) {
            throw new ErrorException('Document class has to extend Base_Print_Document');
        }
        $args = func_get_args();
        array_shift($args); // remove document_classname
        $reflection_obj = new ReflectionClass($document_classname);

        return $reflection_obj->newInstanceArgs($args);
    }

    /**
     * Create a leightbox with buttons
     *
     * @param array $links array of array('href' => .. , 'label' => ..)
     *
     * @return string href to open leightbox
     */
    public static function choose_box_href(array $links)
    {
        $unique_id = md5(serialize($links));
        $popup_id = 'print_choice_popup_' . $unique_id;
        $header = __('Select document template to print');
        $deactivate = " onclick=\"leightbox_deactivate('$popup_id')\"";
        $icons = [];        
        foreach ($links as $link) {
            $icons[] = [
                'href' => $link['href'] . $deactivate,
                'label' => $link['label']
            ];
        }
        $th = Base_ThemeCommon::init_smarty();
        $th->assign('icons', $icons);
        ob_start();
        Base_ThemeCommon::display_smarty($th, self::Instance()->get_type(), 'launchpad');
        $content = ob_get_clean();
        Libs_LeightboxCommon::display($popup_id, $content, $header);
        return Libs_LeightboxCommon::get_open_href($popup_id);
    }

    /**
     * Set custom print href callback.
     *
     * @param callable $callback
     */
    public static function set_print_href_callback($callback)
    {
        Variable::set('print_href_callback', $callback);
    }

    /**
     * Get custom print href callback.
     *
     * @return string callback
     */
    public static function get_print_href_callback()
    {
        return Variable::get('print_href_callback', false);
    }

    /**
     * Register a new printer class.
     *
     * You have to register printer to allow managing templates
     *
     * @param Base_Print_Printer $obj
     */
    public static function register_printer(Base_Print_Printer $obj)
    {
        $registered_printers = self::get_registered_printers();
        $registered_printers[get_class($obj)] = $obj->document_name();
        self::set_registered_printers($registered_printers);
    }

    /**
     * Unregister printer.
     *
     * @param Base_Print_Printer|string $string_or_obj Object or classname
     *                                                 of printer
     */
    public static function unregister_printer($string_or_obj)
    {
        if (!is_string($string_or_obj)) {
            $string_or_obj = get_class($string_or_obj);
        }
        $registered_printers = self::get_registered_printers();
        unset($registered_printers[$string_or_obj]);
        self::set_registered_printers($registered_printers);
    }

    /**
     * Get registered printers' classnames => document names.
     *
     * @return string[] Classnames is the key, document name is the value
     */
    public static function get_registered_printers()
    {
        $registered_printers = Variable::get('printers_registered', false);
        if (!is_array($registered_printers)) {
            $registered_printers = array();
        }
        return $registered_printers;
    }

    /**
     * Get registered printers' classnames and translated document names.
     *
     * @return string[] Classnames is the key, translated document name is the value
     */
    public static function get_registered_printers_translated()
    {
        $registered_printers = self::get_registered_printers();
        foreach ($registered_printers as &$v) {
            $v = _V($v);
        }
        return $registered_printers;
    }

    protected static function set_registered_printers($registered_printers)
    {
        ksort($registered_printers);
        Variable::set('printers_registered', $registered_printers);
    }

    /**
     * Register new document type. Default ones are PDF and HTML.
     *
     * @param Base_Print_Document $obj
     */
    public static function register_document_type(Base_Print_Document $obj)
    {
        $document_types = self::get_registered_document_types();
        $document_types[get_class($obj)] = $obj->document_type_name();
        self::set_registered_document_types($document_types);
    }

    /**
     * Unregister document type.
     *
     * @param Base_Print_Document|string $string_or_obj
     */
    public static function unregister_document_type($string_or_obj)
    {
        if (is_object($string_or_obj)) {
            $string_or_obj = get_class($string_or_obj);
        }
        $document_types = self::get_registered_document_types();
        unset($document_types[$string_or_obj]);
        self::set_registered_document_types($document_types);
    }

    /**
     * Get registered document types.
     * @return string[] classname is the key, document type name is the value
     */
    public static function get_registered_document_types()
    {
        $document_types = Variable::get('print_document_types', false);
        if (!is_array($document_types)) {
            $document_types = array();
        }
        return $document_types;
    }

    protected static function set_registered_document_types($document_types)
    {
        ksort($document_types);
        Variable::set('print_document_types', $document_types);
    }

    /**
     * Disable specific template
     *
     * @param string $printer_class
     * @param string $template_id
     * @param bool   $active
     */
    public static function set_template_disabled($printer_class, $template_id, $active = false)
    {
        $disabled_templates = self::get_disabled_templates();
        $id = "$printer_class::$template_id";
        if ($active) {
            unset($disabled_templates[$id]);
        } else {
            $disabled_templates[$id] = true;
        }
        self::set_disabled_templates($disabled_templates);
    }

    /**
     * check if template is disabled
     *
     * @param string $printer_class
     * @param string $template_id
     *
     * @return bool
     */
    public static function is_template_disabled($printer_class, $template_id)
    {
        $disabled_templates = self::get_disabled_templates();
        $id = "$printer_class::$template_id";
        $ret = & $disabled_templates[$id];
        return $ret == true;
    }

    protected static function get_disabled_templates()
    {
        $disabled_templates = Variable::get('print_disabled_templates', false);
        if (!is_array($disabled_templates)) {
            $disabled_templates = array();
        }
        return $disabled_templates;
    }

    protected static function set_disabled_templates($disabled_templates)
    {
        Variable::set('print_disabled_templates', $disabled_templates);
    }
}

?>