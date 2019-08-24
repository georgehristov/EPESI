<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Utils_RecordBrowser_Recordset_Query_Crits alias
 *
 * Also takes care of backward compatibility
 *
 */
abstract class Utils_RecordBrowser_CritsInterface extends Utils_RecordBrowser_Recordset_Query_Crits
{
    
}

/**
 * Utils_RecordBrowser_Recordset_Query_Crits_Compound alias
 * 
 * Also takes care of backward compatibility
 *
 */
class Utils_RecordBrowser_Crits extends Utils_RecordBrowser_Recordset_Query_Crits_Compound
{
	
	
	
	// ---> backward compatibility
	public function is_active()
	{
		return $this->isActive();
	}
	
	public static function from_array($crits)
	{
		return self::create($crits);
	}
	// <--- backward compatibility
}

/**
 * @deprecated use Utils_RecordBrowser_Recordset_Query_Crits_Single
 *
 */
class Utils_RecordBrowser_CritsSingle extends Utils_RecordBrowser_Recordset_Query_Crits_Single
{
    
}

/**
 * @deprecated use Utils_RecordBrowser_Recordset_Query_Crits_RawSQL
 *
 */
class Utils_RecordBrowser_CritsRawSQL extends Utils_RecordBrowser_Recordset_Query_Crits_RawSQL
{

}

/**
 * @deprecated use Utils_RecordBrowser_Crits::create
 *
 */
class Utils_RecordBrowser_CritsBuilder {}
