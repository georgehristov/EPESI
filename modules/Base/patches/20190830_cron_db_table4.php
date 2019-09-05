<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_alter_column('cron', 'running', 'I');
PatchUtil::db_rename_column('cron', 'func', 'token', 'C(32)');
PatchUtil::db_add_column('cron', 'log', 'X');
PatchUtil::db_add_column('cron', 'reference', 'C(255)');
