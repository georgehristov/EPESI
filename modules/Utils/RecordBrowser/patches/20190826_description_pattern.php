<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('recordbrowser_table_properties', 'description_pattern', 'C(255) DEFAULT \'\'');

$recordsets = DB::GetAll('SELECT * FROM recordbrowser_table_properties');
$checkpoint = Patch::checkpoint('recordset');
$processed = $checkpoint->get('processed', []);
foreach ($recordsets as $recordset) {
	$tab = $recordset['tab'];
    if (isset($processed[$tab])) {
        continue;
    }
    $processed[$tab] = true;
    if (! $description_fields = $recordset['description_fields']) continue;

    Patch::require_time(5);
    Utils_RecordBrowserCommon::set_description_fields($tab, $description_fields);
    
    $checkpoint->set('processed', $processed);
}

PatchUtil::db_drop_column('recordbrowser_table_properties', 'description_fields');
