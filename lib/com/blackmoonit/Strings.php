<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace com\blackmoonit;
use com\blackmoonit\exceptions\IllegalArgumentException;
{//begin namespace

class Strings {

	private function __construct() {} //do not instantiate

	/**
	 * Return everything after $aNeedle is found in $aHaystack.
	 * @param string $aHaystack - string to search through.
	 * @param string $aNeedle - string to find.
	 * @param boolean $bCaseInsensitive - (optional) default FALSE.
	 * @return string|NULL - Returns rest of haystack after needle, else NULL.
	 */
	static public function strstr_after($aHaystack, $aNeedle, $bCaseInsensitive=false) {
    	$strpos = ($bCaseInsensitive) ? 'stripos' : 'strpos';
	    $thePos = $strpos($aHaystack,$aNeedle);
    	if (is_int($thePos)) {
        	return substr($aHaystack,$thePos+strlen($aNeedle));
    	} else
    		return null;
	}
	
	/**
	 * Check to see if string begins with substring.
	 * @param string $aHaystack - string to check
	 * @param string $aNeedle - needle to check for in $aHaystack
	 * @return boolean - Returns TRUE if $aHaystack begins with $aNeedle.
	 */
	static public function beginsWith($aHaystack, $aNeedle) {
		return self::startsWith($aHaystack, $aNeedle);
	}
	
	/**
	 * Optionally case-sensitive string comparison on just the beginning of the haystack.
	 * @param string $aHaystack - string to search on.
	 * @param string $aNeedle - string to find.
	 * @param boolean $bCaseInsensitive - when true, ignores case; defaults to FALSE.
	 * @return boolean Returns true if haystack starts with needle.
	 */
	static public function startsWith($aHaystack, $aNeedle, $bCaseInsensitive=false) {
    	$theFunc = ($bCaseInsensitive) ? 'strncasecmp' : 'strncmp';
    	$aHaystack .= '';  //ensure params are strings
    	$aNeedle .= '';    //ensure params are strings
	    return ($theFunc($aHaystack, $aNeedle, strlen($aNeedle)) === 0);
	}
	
	/**
	 * Optionally case-sensitive string comparison on just the end of the haystack.
	 * @param string $aHaystack - string to search on.
	 * @param string $aNeedle - string to find.
	 * @param boolean $bCaseInsensitive - when true, ignores case; defaults to FALSE.
	 * @return boolean Returns true if haystack ends with needle.
	 */
	static public function endsWith($aHaystack, $aNeedle, $bCaseInsensitive=false) {
		return self::startsWith(substr($aHaystack, strlen($aHaystack)-strlen($aNeedle)), $aNeedle, $bCaseInsensitive);
	}

	/**
	 * Alias for sprintf.
	 * @param string $aFormat - format to use, e.g. "Row %d".
	 * @param mixed $args - set of args used by the format (as many as needed)
	 * @return string - Returns formatted string.
	 * @link http://php.net/manual/en/function.sprintf.php
	 */
	static public function format($aFormat, $args) {
		if (!isset($args))
			throw new IllegalArgumentException('Strings::format requires arguments to replace in the format string.');
		return call_user_func_array('sprintf',func_get_args());
	}

	/**
	 * Captures the var_export() of what is passed in.
	 * @param mixed $aVar - variable to capture export output
	 * @param string $aNewLineReplacement - (optional) default is space ' ', null = no replacement.
	 * @return string Returns the captured export output as string.
	 */
	static public function exportStr($aVar, $aNewLineReplacement=' ') {
		ob_start();
		var_export($aVar);
		if (isset($aNewLineReplacement)) {
			return str_replace("\n",$aNewLineReplacement,ob_get_clean());
		} else {
			return ob_get_clean();
		}
	}
	
	/**
	 * Used in pre-html5 JS form validation.
	 * @param array $aArray - the array to encode.
	 * @return string - Returns string to be used in validate script.
	 */
	static public function phpArray2jsArray($aArray, $elemPostChar="\n") {
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
			$s .= ','.$elemPostChar;
		}
		$s .= "}";
		return $s;
	}

	/**
	 * Generate a random string of variable length.
	 * @param int $aLen - (optional) length of the random string, default 16.
	 * @return string - Returns the randomly generated string.
	 */
	static public function randomSalt($aLen=16) {
		$salt = str_repeat('.',$aLen);
		for ($i = 0; $i<$aLen; $i++) {
			$salt{$i} = substr("./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789", mt_rand(0,63), 1);
		}
		return $salt;
	}
	
	/**
	 * Blowfish pw encryption mechanism: 76 chars long (60 char encryption + 16 char random salt)
	 * <pre>
	 * $pwhash = hasher($pwInput); //encrypts $pw and appends the generated random salt
	 * //safe now to store $pwhash in a database
	 * $isAuthorized = hasher($pwInput, $pwhash);
	 * </pre>
	 * @param string $aPwInput - user supplied pw string
	 * @param string $aEncryptedData - db stored encrypted pw string to compare against (optional)
	 * @return mixed - If only the password is passed in, the encrypted result is returned.
	 * If both the password and encrypted data are passed in, TRUE is returned if they "match",
	 * else FALSE is returned.
	 */
	static public function hasher($aPwInput, $aEncryptedData = false) {
		$theCryptoInfo = '$2a$08$'; //2a = Blowfish, 08 = crypto strength, append actual 22 char salt to end of this
		//if encrypted data is passed, check it against input ($info)
		if ($aEncryptedData) {
			$saltCrypto = substr($aEncryptedData,0,-16);
			$saltPw = substr($aEncryptedData,-16);
			if ($aEncryptedData==crypt($aPwInput.$saltPw,$saltCrypto).$saltPw) {
				return true;
			} else {
				return false;
			}
		} else {
			$saltPw = self::randomSalt(16);
			$saltCrypto = $theCryptoInfo.self::randomSalt(22);
			//return 76 char string (60 char hash & 16 char salt)
			return crypt($aPwInput.$saltPw,$saltCrypto).$saltPw;
		}
	}

	/**
	 * Converts all spaces in the input string into underscores ("_")
	 * @param string $aValue - string to convert.
	 * @return string - Returns the string with all spaces as "_".
	 */
	static public function strip_spaces($aValue) {
		return str_replace(' ','_',trim($aValue));
	}
	
	/**
	 * Generates a new UUID (aka GUID). Removes enclosing "{ }", if present.
	 * @return string - Returns the generated UUID (36 chars).
	 * @see Strings::createUUID()
	 * @see Strings::createTextId()
	 */
	static public function createGUID() {
		if (function_exists('com_create_guid')) {
			return trim(com_create_guid(), '{}');
		} else {
			mt_srand((double)microtime()*10000); //optional for php 4.2.0 and up.
			$charid = strtoupper(md5(uniqid(rand(),true)));
			$guid = substr($charid, 0, 8).'-'.substr($charid, 8, 4).'-'
					.substr($charid,12, 4).'-'.substr($charid,16, 4).'-'
					.substr($charid,20,12);
			return $guid;
		}
	}

	/**
	 * Generates a new UUID (aka GUID). Removes enclosing "{ }", if present.
	 * @return string - Returns the generated UUID (36 chars).
	 * @see Strings::createTextId()
	 */
	static public function createUUID() {
		return Strings::createGUID();
	}

	/**
	 * Captures the var_dump() of what is passed in.
	 * @param mixed $aVar - variable to capture debug output
	 * @param string $aNewLineReplacement - (optional) default is space ' ', null = no replacement.
	 * @return string Returns the captured debug output as string.
	 */
	static public function debugStr($aVar, $aNewLineReplacement=' ') {
		ob_start();
		var_dump($aVar);
		if (isset($aNewLineReplacement)) {
			return str_replace("\n",$aNewLineReplacement,ob_get_clean());
		} else {
			return ob_get_clean();
		}
	}

	/**
	 * Send the string parameter to the debug log.
	 * Current implementation is the LOG_ERR destination with
	 * a prefix of "[dbg] " prepended to the parameter.
	 * @param string $s - string to send to the debug log.
	 */
	static public function debugLog($s) {
		//syslog(LOG_DEBUG,$s);
		syslog(LOG_ERR,'[dbg] '.$s);
	}

	/**
	 * Converts the name from under_score to CamelCase.
	 * e.g. "this_class_name" -> "ThisClassName"
	 * @param string $aName - potential class name.
	 * @return string Returns the name as a standard Class name.
	 */
	static public function getClassName($aName) {
		return preg_replace('/(?:^|_)(.?)/e',"strtoupper('$1')",$aName);
	}

	/**
	 * Converts the name from under_score to CamelCase.
	 * e.g. "this_method_name" -> "thisMethodName"
	 * @param string $aName - potential method name.
	 * @return string Returns the name as a standard method name.
	 */
	static public function getMethodName($aName) {
		return preg_replace('/_(.?)/e',"strtoupper('$1')",$aName);
	}
	
	/**
	 * Converts the hex representation of data to binary (same as the PHP 5.4 function)
	 * http://www.php.net/manual/en/function.hex2bin.php
	 * @param string $aData - Hexadecimal representation of data
	 * @return string - Returns the binary representation of the given data
	 */
	static public function hex2bin($aData='') {
		$bin = '';
		$max = strlen($aData);
		for ($i=0; $i<$max; $i+=2) {
			$bin .= chr(hexdec($aData{$i}.$aData{($i+1)}));
		}
		return $bin;
	}
	
	/**
	 * Converts the binary representation of data to hex (same as the PHP 5.4 function
	 * http://www.php.net/manual/en/function.bin2hex.php
	 * @param string $aData - Data you want to expand into hex notation
	 * @return string - Returns the hex representation of the given data
	 */
	static public function bin2hex($aData='') {
		$hex = '';
		$max = strlen($aData);
		for ($i=0; $i<$max; $i++) {
			$hex .= sprintf("%02x",ord($aData{$i}));
		}
		return $hex;
	}
	
	/**
	 * Convert PHP's UUID to a 32 char SQL UUID (dubbed TextId by me).
	 * @param string $aUUID - a UUID
	 * @return string - Returns the TextId (UUID minus punctuation)
	 */
	static public function cnvUUID2TextId($aUUID) {
		$theResult = str_replace('-','',trim($aUUID,'{} '));
		$sLen = strlen($theResult);
		return ($sLen==32) ? $theResult : '';
	}

	/**
	 * Converts TextId to UUID by putting the punctuation back in.
	 * @param string $aTextId - 32 char SQL UUID.
	 * @return string - Returns the 36 char PHP UUID.
	 */
	static public function cnvTextId2UUID($aTextId) {
		return substr($aTextId,0,8).'-'.substr($aTextId,8,4).'-'.substr($aTextId,12,4).
				'-'.substr($aTextId,16,4).'-'.substr($aTextId,20);
	}
	
	/**
	 * Generates a new UUID as TextId format.
	 * @return string - Returns the new TextId (SQL format, 32 chars).
	 * @see Strings::createUUID()
	 */
	static public function createTextId() {
		return Strings::cnvUUID2TextId(Strings::createUUID());
	}
	
	/**
	 * Convert a Unix Timestamp to a MySQL datetime field format
	 * without time zone information.
	 * @param int $aTimestamp - the timestamp.
	 * @return string - the SQL datetime string.
	 */
	static public function cnvTimestampUnix2MySQL($aTimestamp) {
		return gmdate('Y-m-d H:i:s',$aTimestamp);
	}
	
	/**
	 * Break up really long words/lines along word boundaries, if possible.
	 * @param string $aStr - string to wrap.
	 * @param number $aWidth - max width to wrap around.
	 * @param string $aWrapper - wrap lines using "\n" by default, or custom param.
	 * @return string Returns the string with added $aWrappers inserted into $aStr.
	 */
	static public function wordWrap($aStr, $aWidth=75, $aWrapper="\n") {
		return preg_replace('#(\S{'.$aWidth.',})#e', "chunk_split('$1', ".$aWidth.", '".$aWrapper."')", $aStr);
	}
	
	
}//end class

}//end namespace
