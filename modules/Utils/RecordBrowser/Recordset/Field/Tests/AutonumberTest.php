<?php

class Utils_RecordBrowser_Recordset_Field_Tests_AutonumberTest extends PHPUnit_Framework_TestCase {
	static function getMockField() {
		$recordset = Utils_RecordBrowser_Recordset_Tests_MockRecordset::create('test');
		
		$desc = [
				'type' => 'autonumber',
				'field' => 'Test Autonumber',
				'active' => 1,
				'param' => [
						'prefix' => '',
						'pad_length' => 64,
						'pad_mask' => '?'
				]
		];
		
		$recordset->setField($desc);
		
		return $recordset->getField('test_autonumber');
	}

	function testQuerySection() {
		$field = self::getMockField();
		
		$querySection = $field->getQuerySection(Utils_RecordBrowser_Recordset_Query_Crits_Basic::create('!test_autonumber', '5'));
		
		$this->assertEquals('r.f_test_autonumber != %s AND r.f_test_autonumber IS NOT NULL', $querySection->getSQL());
		
		$this->assertEquals(['5'], $querySection->getValues());
	}
}
