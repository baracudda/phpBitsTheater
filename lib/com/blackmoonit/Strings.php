<?php
namespace com\blackmoonit;
{//begin namespace

class Strings {

	private function __construct() {} //do not instantiate

	static public function strstr_after($aHaystack, $aNeedle, $bCaseInsensitive=false) {
    	$strpos = ($bCaseInsensitive) ? 'stripos' : 'strpos';
	    $thePos = $strpos($aHaystack,$aNeedle);
    	if (is_int($thePos)) {
        	return substr($aHaystack,$thePos+strlen($aNeedle));
    	} else
    		return null;
	}
	
	static public function beginsWith($str, $sub) {
	    return (strncmp($str, $sub, strlen($sub)) == 0);
	}
	
	static public function format() {
		return call_user_func_array('sprintf',func_get_args());
	}

	static public function exportStr($var) {
		ob_start();
		var_export($var);
		return str_replace("\n",' ',ob_get_clean());
	}
	
	static public function phpArray2jsArray($aArray) {
		$s = '{';
		foreach ($aArray as $key=>$val) {
			$s .= $key.': ';
			if (is_array($val))
				$s .= self::phpArray2jsArray($val);
			else if (is_string($val))
				if (self::beginsWith($val,'function'))
					$s .= $val;
				else
					$s .= '"'.$val.'"';
			else
				$s .= self::exportStr($val);
			$s .= ",\n";
		}
		$s .= "}";
		return $s;
	}
	
	/* Blowfish pw encryption mechanism: 82 chars long. 60 char encryption + 22 char random salt
	 * $pwhash = hasher($pw); //encrypts $pw and appends the generated random salt
	 * //safe now to store $pwhash in a database
	 * if ($pwhash==hasher($pwInput, $pwhash)) { 
	 *     //authorized
	 * } else {
	 *     //not authorized
	 * }
	 */
	static public function hasher($info, $aEncryptedData = false) {
		$theCryptStrenth = "08";
		//if encrypted data is passed, check it against input ($info)
		if ($aEncryptedData) {
			if (substr($aEncryptedData, 0, 60) == crypt($info, "$2a$".$theCryptStrenth."$".substr($aEncryptedData, 60))) {
				return true;
			} else {
				return false;
			}
		} else {
			//make a salt and hash it with input, and add salt to end
			$salt = "1234567890123456789012";
			for ($i = 0; $i < 22; $i++) {
				$salt{$i} = substr("./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789", mt_rand(0, 63), 1);
			}
			//return 82 char string (60 char hash & 22 char salt)
			return crypt($info, "$2a$".$theCryptStrenth."$".$salt).$salt;
		}
	}

	static public function strip_spaces($aValue) {
		return str_replace(' ','_',trim($aValue));
	}
	
	static public function createGUID() {
		if (function_exists('com_create_guid')) {
			return trim(com_create_guid(), '{}');
		} else {
			mt_srand((double)microtime()*10000); //optional for php 4.2.0 and up.
			$charid = strtoupper(md5(uniqid(rand(),true)));
			$guid = '{'.substr($charid, 0, 8).'-'.substr($charid, 8, 4).'-'
					.substr($charid,12, 4).'-'.substr($charid,16, 4).'-'
					.substr($charid,20,12).'}';
			return $guid;
		}
	}

	static public function createUUID() {
		return Strings::createGUID();
	}

	static public function debugStr($var) {
		ob_start();
		var_dump($var);
		return str_replace("\n",' ',ob_get_clean());
	}

	static public function debugLog($s) {
		syslog(LOG_DEBUG,$s);
		//syslog(LOG_ERR,$s);
	}

	static public function getClassName($aName) {
		// underscored to upper-camelcase
		// e.g. "this_class_name" -> "ThisClassName"
		return preg_replace('/(?:^|_)(.?)/e',"strtoupper('$1')",$aName);
	}

	static public function getMethodName($aName) {
		// underscored to lower-camelcase
		// e.g. "this_method_name" -> "thisMethodName"
		return preg_replace('/_(.?)/e',"strtoupper('$1')",$aName);
	}
	
	/**
	 * Converts the hex representation of data to binary (same as the PHP 5.4 function)
	 * http://www.php.net/manual/en/function.hex2bin.php
	 * @param   string  $data       Hexadecimal representation of data
	 * @return  string              Returns the binary representation of the given data
	 */
	static public function hex2bin($data='') {
		$bin = '';
		$max = strlen($data);
		for ($i=0; $i<$max; $i+=2) {
			$bin .= chr(hexdec($data{$i}.$data{($i+1)}));
		}
		return $bin;
	}
	
	/**
	 * Converts the binary representation of data to hex (same as the PHP 5.4 function
	 * http://www.php.net/manual/en/function.bin2hex.php
	 * @param String $data          Data you want to expand into hex notation
	 * @return  string              Returns the hex representation of the given data
	 */
	static public function bin2hex($data='') {
		$hex = '';
		$max = strlen($data);
		for ($i=0; $i<$max; $i++) {
			$hex .= sprintf("%02x",ord($data{$i}));
		}
		return $hex;
	}
	
	static public function cnvUUID2TextId($aUUID) {
		$theResult = str_replace('-','',trim($aUUID,'{} '));
		$sLen = strlen($theResult);
		return ($sLen==32) ? $theResult : '';
	}
	
	static public function cnvTextId2UUID($aTextId) {
		return substr($aTextId,0,8).'-'.substr($aTextId,8,4).'-'.substr($aTextId,12,4).
				'-'.substr($aTextId,16,4).'-'.substr($aTextId,20);
	}
	
	static public function createTextId() {
		return Strings::cnvUUID2TextId(Strings::createUUID());
	}
	
	static public function cnvTimestampUnix2SQL($aTimestamp) {
		return gmdate('Y-m-d H:i:s',$aTimestamp);
	}
	
	
}//end class

}//end namespace
