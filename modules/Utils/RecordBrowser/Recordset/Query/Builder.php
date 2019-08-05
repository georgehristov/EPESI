<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Builder
{
    protected $recordset;

    protected $applied_joins = array();
    protected $final_tab;
    protected $admin_mode = false;

    public function create($tabOrRecordset, $adminMode = false)
    {
    	$recordset = is_string($tabOrRecordset)? Utils_RecordBrowser_Recordset::create($tabOrRecordset): $tabOrRecordset;
    	
    	return new static ($recordset, $adminMode);
    }
    
    public function __construct(Utils_RecordBrowser_Recordset $recordset, $adminMode = false)
    {
        $this->setRecordset($recordset);
        $this->admin_mode = $adminMode;
    }

    public function build_query(Utils_RecordBrowser_Crits $crits, $order = array(), $admin_filter = '')
    {
        $crits = $crits->replace_special_values();

        $tab_with_as = $this->getTab() . '_data_1 AS ' . $this->getTabAlias();
        $this->final_tab = $tab_with_as;

        list($having, $vals) = $this->to_sql($crits);

        if (!$having) $having = 'true';

        $this->final_tab = str_replace('('. $tab_with_as .')', $tab_with_as, $this->final_tab);
        $where = $admin_filter . "($having)";
        $sql = ' ' . $this->final_tab . ' WHERE ' . $where;

        $order_sql = $this->build_order_part($order);

        return array('sql' => $sql, 'vals' => $vals, 'order' => $order_sql, 'tab' => $this->final_tab, 'where' => $where);
    }

    public function to_sql(Utils_RecordBrowser_Recordset_Query_Crits $crits)
    {
        if ($crits->is_active() == false) {
            return array('', array());
        }
        if ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits_Single) {
            return $this->build_single_crit_query($crits);
        } elseif ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits_Compound) {
            $vals = array();
            $sql = array();
            foreach ($crits->get_component_crits() as $c) {
                list($s, $v) = $this->to_sql($c);
                if ($s) {
                    $vals = array_merge($vals, $v);
                    $sql[] = "($s)";
                }
            }
            $glue = ' ' . $crits->get_join_operator() . ' ';
            $sql_str = implode($glue, $sql);
            if ($crits->get_negation() && $sql_str) {
                $sql_str = "NOT ($sql_str)";
            }
            return array($sql_str, $vals);
        } elseif ($crits instanceof Utils_RecordBrowser_Recordset_Query_Crits_RawSQL) {
            $sql = $crits->get_negation() ? $crits->get_negation_sql() : $crits->get_sql();
            return array($sql, $crits->get_vals());
        }
        return array('', array());
    }

    public static function transform_meta_operators_to_sql($operator)
    {
        if ($operator == 'LIKE') {
            $operator = DB::like();
        } else if ($operator == 'NOT LIKE') {
            $operator = 'NOT ' . DB::like();
        }
        return $operator;
    }

    protected function build_order_part($order)
    {
    	$orderby = [];
    	$user_id = Acl::get_user();
    	
        foreach ($order as $k => $v) {
            if (!is_string($k)) {
                break;
            }
            if ($k[0] == ':') {
                $order[] = ['column' => $k, 'order' => $k, 'direction' => $v];
            } else {
            	if ($field = $this->getRecordset()->getField($k, true)) {
            		$order[] = ['column' => $field->getName(), 'order' => $field->getName(), 'direction' => $v];
                }
            }
            unset($order[$k]);
        }

        foreach ($order as $v) {
            if ($v['order'][0] == ':') {
                switch ($v['order']) {
                    case ':id':
                        $orderby[] = ' id ' . $v['direction'];
                        break;
                    case ':Fav' :
                        $orderby[] = ' (SELECT COUNT(*) FROM '.$this->getTab().'_favorite WHERE '.$this->getTab().'_id='.$this->getTabAlias().'.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Visited_on'  :
                        $orderby[] = ' (SELECT MAX(visited_on) FROM '.$this->getTab().'_recent WHERE '.$this->getTab().'_id='.$this->getTabAlias().'.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Edited_on'   :
                        $orderby[] = ' (CASE WHEN (SELECT MAX(edited_on) FROM '.$this->getTab().'_edit_history WHERE '.$this->getTab().'_id='.$this->getTabAlias().'.id) IS NOT NULL THEN (SELECT MAX(edited_on) FROM '.$this->getTab().'_edit_history WHERE '.$this->getTab().'_id='.$this->getTabAlias().'.id) ELSE ' . $this->getTabAlias() . '.created_on END) '.$v['direction'];
                        break;
                    default     :
                        $orderby[] = ' '.substr($v['order'], 1) . ' ' . $v['direction'];
                }
            } else {
            	if (!$field = $this->getRecordset()->getField($v['order'], true)) continue;
            	 
                $orderby[] = $field->getSqlOrder($v['direction']);
            }
        }

        return $orderby? ' ORDER BY' . implode(', ',$orderby): '';
    }

    public function build_single_crit_query(Utils_RecordBrowser_CritsSingle $crit)
    {
        if ($special_ret = $this->handle_special_field_crit($crit)) {
            return $special_ret;
        }

        list($fieldId, ) = Utils_RecordBrowser_CritsSingle::parse_subfield($crit->get_field());

        if (!$field = $this->getRecordset()->getField($fieldId, true)) {
            return ['', []];
        }

        list($sql, $value) = $this->hf_multiple($crit, $field);

        return [$sql, is_array($value)? $value: [$value]];
    }

    protected function handle_special_field_crit(Utils_RecordBrowser_CritsSingle $crit)
    {
        $field = $crit->get_field();
        $operator = self::transform_meta_operators_to_sql($crit->get_operator());
        $value = $crit->get_value();
        $negation = $crit->get_negation();

        $special = $field[0] == ':' || $field == 'id';
        if ($special) {
            $sql = '';
            $vals = array();
            switch ($field) {
                case ':id' :
                case 'id' :
                    if (!is_array($value)) {
                        $sql = $this->getTabAlias().".id $operator %d";
                        $value = preg_replace('/[^0-9-]*/', '', $value);
                        $vals[] = $value;
                    } else {
                        if ($operator != '=' && $operator != '==') {
                            throw new Exception("Cannot use array values for id field operator '$operator'");
                        }
                        $clean_vals = array();
                        foreach ($value as $v) {
                            if (is_numeric($v)) {
                                $clean_vals[] = $v;
                            }
                        }
                        if (empty($clean_vals)) {
                            $sql = 'false';
                        } else {
                            $sql = $this->getTabAlias().".id IN (" . implode(',', $clean_vals) . ")";
                        }
                    }
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Fav' :
                    $fav = ($value == true);
                    if ($negation) $fav = !$fav;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->getTab() . '_favorite AS '.$this->getTabAlias().'_fav ON '.$this->getTabAlias().'_fav.' . $this->getTab() . '_id='.$this->getTabAlias().'.id AND '.$this->getTabAlias().'_fav.user_id='. Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $fav ? 'IS NOT NULL' : 'IS NULL';
                    $sql= $this->getTabAlias()."_fav.fav_id $rule";
                    break;
                case ':Sub' :
                    $sub = ($value == true);
                    if ($negation) $sub = !$sub;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN utils_watchdog_subscription AS '.$this->getTabAlias().'_sub ON '.$this->getTabAlias().'_sub.internal_id='.$this->getTabAlias().'.id AND '.$this->getTabAlias().'_sub.category_id=' . Utils_WatchdogCommon::get_category_id($this->getTab()) . ' AND '.$this->getTabAlias().'_sub.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $sub ? 'IS NOT NULL' : 'IS NULL';
                    $sql = $this->getTabAlias()."_sub.internal_id $rule";
                    break;
                case ':Recent'  :
                    $rec = ($value == true);
                    if ($negation) $rec = !$rec;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->getTab() . '_recent AS '.$this->getTabAlias().'_rec ON '.$this->getTabAlias().'_rec.' . $this->getTab() . '_id='.$this->getTabAlias().'.id AND '.$this->getTabAlias().'_rec.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $rec ? 'IS NOT NULL' : 'IS NULL';
                    $sql = $this->getTabAlias()."_rec.user_id $rule";
                    break;
                case ':Created_on'  :
                    $vals[] = Base_RegionalSettingsCommon::reg2time($value, false);
                    $sql = $this->getTabAlias().'.created_on ' . $operator . '%T';
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Created_by'  :
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                    $sql = array();
                    foreach ($value as $v) {
                        $vals[] = $v;
                        $sql[] = $this->getTabAlias().'.created_by ' . $operator . ' %d';
                    }
                    $sql = implode(' OR ', $sql);
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Edited_on'   :
                    if ($value === null) {
                        if ($operator == '=') {
                            $inj = 'IS NULL';
                        } elseif ($operator == '!=') {
                            $inj = 'IS NOT NULL';
                        } else {
                            throw new Exception('Cannot compare timestamp field null with operator: ' . $operator);
                        }
                    } else {
                        $inj = $operator . '%T';
                        $timestamp = Base_RegionalSettingsCommon::reg2time($value, false);
                        $vals[] = $timestamp;
                        $vals[] = $timestamp;
                    }

                    $sql = '(((SELECT MAX(edited_on) FROM ' . $this->getTab() . '_edit_history WHERE ' . $this->getTab() . '_id='.$this->getTabAlias().'.id) ' . $inj . ') OR ' .
                               '((SELECT MAX(edited_on) FROM ' . $this->getTab() . '_edit_history WHERE ' . $this->getTab() . '_id='.$this->getTabAlias().'.id) IS NULL AND '.$this->getTabAlias().'.created_on ' . $inj . '))';
                    if ($negation) {
                        $sql = "NOT (COALESCE($sql, FALSE))";
                    }
                    break;
            }
            return array($sql, $vals);
        }
        return false;
    }

    protected function hf_multiple(Utils_RecordBrowser_CritsSingle $crit, Utils_RecordBrowser_Recordset_Field $field)
    {
        $sql = array();
        $vals = array();

        $operator = $crit->get_operator();
        $raw_sql_val = $crit->get_raw_sql_value();
        $value = is_string($crit->get_value()) && preg_match('/^[A-Za-z]$/',$crit->get_value())
            ? "'%".$crit->get_value()."%'"
            : $crit->get_value();
        $negation = $crit->get_negation();
        if ($operator == 'NOT LIKE') {
            $operator = 'LIKE';
            $negation = !$negation;
        }
        if ($operator == '!=') {
            $operator = '=';
            $negation = !$negation;
        }
        $operator = self::transform_meta_operators_to_sql($operator);
        if (is_array($value)) { // for empty array it will give empty result
            $sql[] = 'false';
        } else {
            $value = array($value);
        }
        foreach ($value as $w) {
        	list($sql2, $vals2) = $raw_sql_val? $field->handleCritsRawSql($crit->get_field(), $operator, $w, $raw_sql_val): $field->handleCrits($crit->get_field(), $operator, $w);
            if ($sql2) {
                $sql[] = $sql2;
                $vals = array_merge($vals, $vals2);
            }
        }
        $sql_str = implode(' OR ', $sql);
        if ($sql_str && $negation) {
            $sql_str = "NOT ($sql_str)";
        }
        return array($sql_str, $vals);

    }

	/**
	 * @return Utils_RecordBrowser_Recordset
	 */
	public function getRecordset() {
		return $this->recordset;
	}
	
	public function getTab() {
		return $this->getRecordset()->getTab();
	}
	
	public function getTabAlias() {
		return $this->getRecordset()->getTabAlias();
	}

	/**
	 * @param Utils_RecordBrowser_Recordset $recordset
	 */
	protected function setRecordset($recordset) {
		$this->recordset = $recordset;
		
		return $this;
	}

}
