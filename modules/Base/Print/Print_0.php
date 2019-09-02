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

class Base_Print extends Module
{
    public function admin()
    {
        if($this->is_back()) {
            $this->parent->reset();
            return;
        }
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

        $form = $this->init_module(Libs_QuickForm::module_name());
        foreach (Base_PrintCommon::get_registered_printers_translated()
                 as $class_name => $printer_name) {
            $form->addElement('header', $printer_name, $printer_name);
            foreach (Base_Print_Printer::instance($class_name)->default_templates() as $tpl_id => $tpl) {
                $field_id = "$class_name::$tpl_id";
                $field_id = preg_replace('/[^A-Za-z0-9_:]/', '_', $field_id);
                $form->addElement('checkbox', $field_id, _V($tpl_id));
                $state = !Base_PrintCommon::is_template_disabled($class_name, $tpl_id);
                $form->setDefaults(array($field_id => $state));
            }
        }
        if ($form->validate()) {
            $values = $form->exportValues();
            foreach (Base_PrintCommon::get_registered_printers_translated()
                     as $class_name => $printer_name) {
                foreach (Base_Print_Printer::instance($class_name)->default_templates() as $tpl_id => $tpl) {
                    $field_id = "$class_name::$tpl_id";
                    $field_id = preg_replace('/[^A-Za-z0-9_:]/', '_', $field_id);
                    $active = isset($values[$field_id]) ? $values[$field_id] : false;
                    Base_PrintCommon::set_template_disabled($class_name, $tpl_id, $active);
                }
            }
            $this->parent->reset();
        } else {
            Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
            $this->display_module($form);
        }
    }
}

?>