<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Recordset_Query_Crits_Builder
{

    public function build_single($crits)
    {
        $ret = array();

        foreach ($crits as $k => $v) {

            if ($v instanceof Utils_RecordBrowser_Recordset_Query_Crits) {
                $ret[] = $v;
                continue;
            }

            // initiate key modifiers for each crit
            $negative = $noquotes = false;

            // default operator
            $operator = '=';

            // parse and remove modifiers
            while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
                if ($k[0] == '!') $negative = true;
                if ($k[0] == '"') $noquotes = true;
                if ($k[0] == '<') $operator = '<';
                if ($k[0] == '>') $operator = '>';
                if ($k[0] == '~') $operator = 'LIKE';
                // parse >= and <=
                if ($k[1] == '=' && $operator != 'LIKE') {
                    $operator .= '=';
                    $k = substr($k, 2);
                } else $k = substr($k, 1);

                if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word. Crits:'.print_r($crits,true), E_USER_ERROR);
            }

            $new_crit = new Utils_RecordBrowser_CritsSingle($k, $operator, $v);
            if ($noquotes) $new_crit->set_raw_sql_value();
            if ($negative) $new_crit->set_negation();

            $ret[] = $new_crit;
        }
        return $ret;
    }

    public function build_from_array($crits)
    {
        $CRITS = array(new Utils_RecordBrowser_Crits());
        if (is_bool($crits)) {
            return $crits ? $CRITS[0] : new Utils_RecordBrowser_CritsRawSQL('false', 'true');
        }
        if (!$crits) { // empty array case
            return $CRITS[0];
        }
        $CRITS_cnt = 1;
        /** @var Utils_RecordBrowser_Crits $current_crit */
        $current_crit = $CRITS[0];

        $or_started = $group_or = false;
        $group_or_cnt = null;
        foreach($crits as $k=>$v){
            if ($k == '') continue;

            // initiate key modifiers for each crit
            $negative = $noquotes = $or_start = $or = $group_or_start = false;

            // default operator
            $operator = '=';

            // parse and remove modifiers
            while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
                if ($k[0]=='!') $negative = true;
                if ($k[0]=='"') $noquotes = true;
                if ($k[0]=='(') $or_start = true;
                if ($k[0]=='|') $or = true;
                if ($k[0]=='<') $operator = '<';
                if ($k[0]=='>') $operator = '>';
                if ($k[0]=='~') $operator = 'LIKE';
                if ($k[0]=='^') $group_or_start = true;
                // parse >= and <=
                if ($k[1]=='=' && $operator != 'LIKE') {
                    $operator .= '=';
                    $k = substr($k, 2);
                } else $k = substr($k, 1);

                if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word. Crits:'.print_r($crits,true), E_USER_ERROR);
            }

            $new_crit = new Utils_RecordBrowser_CritsSingle($k, $operator, $v);
            if ($noquotes) $new_crit->set_raw_sql_value();
            if ($negative) $new_crit->set_negation();

            if ($group_or_start) {
                $or_started = false; // group or takes precedence
                if ($group_or) {
                    $CRITS_cnt = $group_or_cnt + 1; // return to group or crit
                    $current_crit = $CRITS[$group_or_cnt]; // get grouping crit
                } else {
                    $CC = new Utils_RecordBrowser_Crits();
                    $group_or_cnt = $CRITS_cnt;
                    $CRITS[$CRITS_cnt++] = $CC;
                    $current_crit->_and($CC);
                    $current_crit = $CC;
                    $group_or = true;
                }
                $CC = new Utils_RecordBrowser_Crits();
                $CRITS[$CRITS_cnt++] = $CC;
                $current_crit->_or($CC);
                $current_crit = $CC;
            }
            if ($or_start) {
                if ($or_started) {
                    $CRITS_cnt -= 1; // pop current one
                    $current_crit = $CRITS[$CRITS_cnt - 1]; // get grouping crit
                }
                $CC = new Utils_RecordBrowser_Crits($new_crit);
                $CRITS[$CRITS_cnt++] = $CC;
                $current_crit->_and($CC);
                $current_crit = $CC;
                $or_started = true;
                continue;
            }
            if ($or) {
                $current_crit->_or($new_crit);
            } else {
                if ($or_started) {
                    $CRITS_cnt -= 1; // pop current one
                    $current_crit = $CRITS[$CRITS_cnt - 1]; // get grouping crit
                    $or_started = false;
                }
                $current_crit->_and($new_crit);
            }
        }
        return $CRITS[0];
    }
}
