<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_CritsValidator
{
    protected $tab;
    protected $fields;
    protected $fields_by_id;

    protected $issues = array();

    function __construct($tab)
    {
        $this->tab = $tab;
        $this->fields = Utils_RecordBrowserCommon::init($tab);
        $this->fields_by_id = Utils_RecordBrowserCommon::$hash;
    }

    public function validate(Utils_RecordBrowser_CritsInterface $crits, $record)
    {
        if (!$crits->is_active()) {
            return true;
        }
        $success = true;

        if ($crits instanceof Utils_RecordBrowser_CritsSingle) {
            $success = $this->validate_single($crits, $record);
        } elseif ($crits instanceof Utils_RecordBrowser_Crits) {
            $success = $this->validate_compound($crits, $record);
        } elseif ($crits instanceof Utils_RecordBrowser_CritsRawSQL) {
            $success = $this->validate_sql($crits, $record);
        }
        return $success;
    }

    /**
     * @return array
     */
    public function get_issues()
    {
        return $this->issues;
    }

    protected function validate_single(Utils_RecordBrowser_CritsSingle $crits, $record)
    {
        $id = isset($record['id']) ? $record['id'] : '';
        list($field, $subfield) = Utils_RecordBrowser_CritsSingle::parse_subfield($crits->get_field());
        $field = ltrim(Utils_RecordBrowserCommon::get_field_id($field), '_');
        $subfield = ltrim(Utils_RecordBrowserCommon::get_field_id($subfield), '_');
        $r_val = isset($record[$field]) ? $record[$field] : '';
        $crit_value = $crits->get_value();
        $field_definition = $this->get_field_definition($field);
        if ($subfield && $field_definition) {
            $sub_tab = isset($field_definition['ref_table']) ? $field_definition['ref_table'] : false;
            if ($sub_tab) {
                if (is_array($r_val)) {
                    $orig_values = $r_val;
                    $r_val = array();
                    foreach ($orig_values as $k => $v) {
                        $nested_val = Utils_RecordBrowserCommon::get_value($sub_tab, $v, $subfield);
                        if (substr($nested_val, 0, 2)=='__') $nested_val = Utils_RecordBrowserCommon::decode_multi($nested_val); // FIXME need better check
                        if (is_array($nested_val)) $r_val = array_merge($r_val, $nested_val);
                        else $r_val[] = $nested_val;
                    }
                } else {
                    if ($r_val) $r_val = Utils_RecordBrowserCommon::get_value($sub_tab, $r_val, $subfield);
                    else $r_val = '';
                    if (substr($r_val, 0, 2)=='__') $r_val = Utils_RecordBrowserCommon::decode_multi($r_val); // FIXME need better check
                }
            }
        }
        $k = strtolower($field);
        $record[$k] = $r_val;

        $result = false;
        $transform_date = false;
        if ($k == 'created_on') {
            $transform_date = 'timestamp';
        } elseif ($k == 'edited_on') {
            $details = Utils_RecordBrowserCommon::get_record_info($this->tab, $id);
            $record[$k] = $details['edited_on'] ? $details['edited_on'] : $details['created_on'];
            $transform_date = 'timestamp';
        } elseif ($field_definition) {
            $type = $field_definition['type'];
            if ($type == 'timestamp') {
                $transform_date = 'timestamp';
            } elseif ($type == 'date') {
                $transform_date = 'date';
            }
        }
        if ($transform_date == 'timestamp' && $crit_value) {
            $crit_value = Base_RegionalSettingsCommon::reg2time($crit_value, false);
            $crit_value = date('Y-m-d H:i:s', $crit_value);
        } else if ($transform_date == 'date' && $crit_value) {
            $crit_value = Base_RegionalSettingsCommon::reg2time($crit_value, false);
            $crit_value = date('Y-m-d', $crit_value);
        }

        $vv = explode('::',$crit_value,2);
        if (isset($vv[1]) && is_callable($vv)) {
            $result = call_user_func_array($vv, array($this->tab, &$record, $k, $crits));
        } else {
            if (is_array($record[$k])) {
                if ($crit_value) $result = in_array($crit_value, $record[$k]);
                else $result = empty($record[$k]);
                if ($crits->get_operator() == '!=') $result = !$result;
            }
            else switch ($crits->get_operator()) {
                case '>': $result = ($record[$k] > $crit_value); break;
                case '>=': $result = ($record[$k] >= $crit_value); break;
                case '<': $result = ($record[$k] < $crit_value); break;
                case '<=': $result = ($record[$k] <= $crit_value); break;
                case '!=': $result = ($record[$k] != $crit_value); break;
                case '=': $result = ($record[$k] == $crit_value); break;
                case 'LIKE': $result = self::check_like_match($record[$k], $crit_value); break;
                case 'NOT LIKE': $result = !self::check_like_match($record[$k], $crit_value); break;
            }
        }
        if ($crits->get_negation()) $result = !$result;
        if (!$result) $this->issues[] = $k;
        return $result;
    }

    public static function check_like_match($value, $pattern, $ignore_case = true)
    {
        $pattern = str_replace(array('_', '%'), array('.', '.*'), $pattern);
        $pattern = "/^$pattern\$/" . ($ignore_case ? "i" : "");
        return preg_match($pattern, $value) > 0;
    }

    protected function validate_compound(Utils_RecordBrowser_Crits $crits, $record)
    {
        if ($crits->is_empty()) {
            return true;
        }
        $or = $crits->get_join_operator() == 'OR';
        $success = $or ? false : true;
        foreach ($crits->get_component_crits() as $c) {
            $satisfied = $this->validate($c, $record);
            if ($or) {
                if ($satisfied) {
                    $success = true;
                    break;
                }
            } else {
                if (!$satisfied) {
                    $success = false;
                    break;
                }
            }
        }
        if ($crits->get_negation()) {
            $success = !$success;
        }
        return $success;
    }

    protected function validate_sql(Utils_RecordBrowser_CritsRawSQL $crits, $record)
    {
        $sql = $crits->get_negation() ? $crits->get_negation_sql() : $crits->get_sql();
        if ($sql) {
            $sql = "AND $sql";
        }
        $ret = DB::GetOne("SELECT 1 FROM {$this->tab}_data_1 WHERE id=%d $sql", array($record['id']));
        return $ret ? true : false;
    }

    protected function get_field_definition($field_id_or_label)
    {
        $field_def = null;
        if (isset($this->fields[$field_id_or_label])) {
            $field_def = $this->fields[$field_id_or_label];
        } elseif (isset($this->fields_by_id[$field_id_or_label])) {
            $field_label = $this->fields_by_id[$field_id_or_label];
            $field_def = $this->fields[$field_label];
        }
        return $field_def;
    }

}