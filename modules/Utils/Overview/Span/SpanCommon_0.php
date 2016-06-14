<?php
/**
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2015, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-span
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Overview_SpanCommon extends ModuleCommon {
	public static function init_span() {
		return new Utils_Overview_Span_Object();
	}
	
	public static function color_from_text($text,$min_brightness=100,$spec=10, $hue = 100)	{
		// Check inputs
		if(!is_int($min_brightness)) trigger_error("$min_brightness is not an integer" . $this->get_path() . '.', E_USER_ERROR);
		if(!is_int($spec)) trigger_error("$spec is not an integer: " . $this->get_path() . '.', E_USER_ERROR);
		if($spec < 2 or $spec > 10) trigger_error("$spec is out of range: " . $this->get_path() . '.', E_USER_ERROR);
		if($min_brightness < 0 or $min_brightness > 255) trigger_error("$min_brightness is out of range: " . $this->get_path() . '.', E_USER_ERROR);
	
		$hash = md5($text);  //Gen hash of text
		$colors = array();
		for($i=0;$i<3;$i++)
			$colors[$i] = max(array(round(((hexdec(substr($hash,$spec*$i,$spec)))/hexdec(str_pad('',$spec,'F')))*$hue) + 80,$min_brightness)); //convert hash into 3 decimal values between 0 and 255
	
		if($min_brightness > 0)  //only check brightness requirements if min_brightness is about 100
			while( array_sum($colors)/3 < $min_brightness )  //loop until brightness is above or equal to min_brightness
				for($i=0;$i<3;$i++)
					$colors[$i] += 10;	//increase each color by 10
	
		$output = '';
	
		for($i=0;$i<3;$i++)
			$output .= str_pad(dechex($colors[$i]),2,0,STR_PAD_LEFT);  //convert each color to hex and append to output
	
		return '#'.$output;
	}
}

?>