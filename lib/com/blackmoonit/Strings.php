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
use Exception;
use PDOStatement;
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
				$s .= self::phpArray2jsArray($val, $elemPostChar);
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
	 * Generate a random string of variable length using Base64 alphabet.
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
	 * Random string with just ".", "0 thru 9", and "A-Z,a-z".
	 * @param number $aLen - (optional) length of random string, default 16.
	 * @return string Returns a random string of length specified.
	 */
	static public function urlSafeRandomChars($aLen=16) {
		$salt = str_repeat('.',$aLen);
		for ($i = 0; $i<$aLen; $i++) {
			$salt{$i} = substr(".ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789", mt_rand(0,62), 1);
		}
		return $salt;
	}
	
	static public $crypto_strength = '08';
	
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
		//only first 72 chars are encoded via blowfish,
		//  and also try to protect against unseemly long string/time attacks
		if (strlen($aPwInput)<64) {
			$thePwInput = urlencode($aPwInput);
		} else {
			$thePwInput = urlencode(substr($aPwInput,0,64).min(array(999,strlen($aPwInput))));
		}
		/* Security advisory from PHP.net:
		 * Developers targeting PHP 5.3.7+ should use "$2y$" in preference to "$2a$".
		 * Full details: http://php.net/security/crypt_blowfish.php
		 */
		if (version_compare(PHP_VERSION, '5.3.7') >= 0) {
			//2y = Updated Blowfish, 11 = crypto strength (04-31), append actual 22 char salt to end of this
			$theCryptoInfo = '$2y$'.self::$crypto_strength.'$';
		} else {
			//2a = Blowfish, 08 = crypto strength, append actual 22 char salt to end of this
			$theCryptoInfo = '$2a$'.self::$crypto_strength.'$';
		}
		//if encrypted data is passed, check it against input ($info)
		if ($aEncryptedData) {
			$saltCrypto = substr($aEncryptedData,0,-16);
			$saltPw = substr($aEncryptedData,-16);
			if ($aEncryptedData==crypt($thePwInput.$saltPw,$saltCrypto).$saltPw) {
				return true;
			} else {
				return false;
			}
		} else {
			$saltPw = self::randomSalt(16);
			$saltCrypto = $theCryptoInfo.self::randomSalt(22);
			//return 76 char string (60 char hash & 16 char salt)
			return crypt($thePwInput.$saltPw,$saltCrypto).$saltPw;
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
	
	const DEBUG_VAR_DUMP_FLAG = '__DEBUG_VAR_DUMP_FLAG';
	/**
	 * Recursive var dump that takes into account the magic method __debugInfo(). This method
	 * is used in PHP <5.6 since that is when __debugInfo() was introduced.
	 * @param mixed $aVar - the var to dump.
	 * @param boolean $bMultilineOutput - (OPTIONAL) defaults to TRUE.
	 * @param string $aVarName - name of the var we're dumping (used during recursion).
	 * @param string $aVarReference - complex name used to determine infinite recursion and avoid it.
	 * @param string $aDeRefStr - string used to "de-reference" recursion values ("=" or "->").
	 * @param number $depth - how deep into the var we've delved (25 is a hard limit).
	 * @return string Returns the dumped var as a string ("- " per depth and "\n" used).
	 */
	static public function var_dump(&$aVar, $bMultilineOutput=true, $aVarName='', $aVarReference='', $aDeRefStr='=', $depth=0) {
		static $varList;
		static $varCount;
		$nl = ($bMultilineOutput ? "\n" : '');
		$fe = ($bMultilineOutput ? '' : ', ');
		$indent = ($bMultilineOutput ? str_repeat('- ',$depth) : '');
		$output = '';
		switch (true) {
			case ($depth===0):
				$varList = array();
				$varCount = 0;
				break;
			case ($depth<=25):
				$output .= $indent.$aVarName.' '.$aDeRefStr.' ';
				break;
			default:
				return $output;
		}
		$theVar =& $aVar;
		$theVarType = gettype($theVar);
		
		try {
			if (is_null($theVar)) {
				$output .= (($theVarType!=='NULL') ? '('.$theVarType.') ' : '').'NULL';
			} else if ($theVarType==='array' && isset($theVar[self::DEBUG_VAR_DUMP_FLAG])) {
				$output .= '[@see: |A-'.$theVar[self::DEBUG_VAR_DUMP_FLAG].'|]';
			} else if ($theVarType==='object' && isset($theVar->{self::DEBUG_VAR_DUMP_FLAG})) {
				$output .= '[@see: |O-'.$theVar->{self::DEBUG_VAR_DUMP_FLAG}.'|]';
			} else { //we have not seen this var before
				if (empty($aVarReference)) {
					$aVarReference = $aVarName;
				}
				// print it out
				switch ($theVarType) {
					case 'array':
						//update debug flag to preven recusion
						array_push($varList,$theVar);
						$theVar[self::DEBUG_VAR_DUMP_FLAG] = ++$varCount;
						//dump var
						$output .= 'Array('.(count($theVar)-1).')|A-'.$varCount.'|['.$nl;
						foreach ($theVar as $key => &$val) {
							if ($key!==self::DEBUG_VAR_DUMP_FLAG) {
								$s = self::var_dump($val, $bMultilineOutput, $key, $aVarReference.'["'.$key.'"]', '=', $depth+1);
								$output .= $s.$fe;
							}
						}
						$output .= $indent.']';
						break;
					case 'object':
						//update debug flag to preven recusion
						array_push($varList,$theVar);
						$theVar->{self::DEBUG_VAR_DUMP_FLAG} = ++$varCount;
						//dump var
						$output .= '('.get_class($theVar).')|O-'.$varCount.'|{'.$nl;
						if (is_callable(array($theVar,'__debugInfo'))) {
							foreach ($theVar->__debugInfo() as $key => $val) {
								if ($key!==self::DEBUG_VAR_DUMP_FLAG) {
									$s = self::var_dump($val, $bMultilineOutput, $key, $aVarReference.'->'.$key, '=>', $depth+1);
									$output .= $s.$fe;
								}
							}
						} else if ($theVar instanceof PDOStatement) {
							//do not allow debug var dump to fetch data from cursor object
						} else {
							foreach ($theVar as $key => $val) {
								if ($key!==self::DEBUG_VAR_DUMP_FLAG) {
									$s = self::var_dump($val, $bMultilineOutput, $key, $aVarReference.'->'.$key, '->', $depth+1);
									$output .= $s.$fe;
								}
							}
						}
						$output .= $indent.'}';
						break;
					case 'string':
						$output .= '"'.$theVar.'"';
						break;
					case 'boolean':
						$output .= ($theVar?'true':'false');
						break;
					default:
						$output .= '('.$theVarType.') '.$theVar;
						break;
				}//switch
			}
		} catch (Exception $e) {
			$depth = 0;
			$output .= ' ERR='.$e->getMessage();
		}
		if ($depth===0) {
			foreach ($varList as $var) {
				if (is_array($var))
					unset($var[self::DEBUG_VAR_DUMP_FLAG]);
				else if (is_object($var))
					unset($var->{self::DEBUG_VAR_DUMP_FLAG});
			}
			unset($varList);
			unset($varCount);
		}
		return $output.$nl;
	}

	/**
	 * Captures the var_dump() of what is passed in.
	 * @param mixed $aVar - variable to capture debug output
	 * @param string $aNewLineReplacement - (optional) default is space ' ', null = no replacement.
	 * @return string Returns the captured debug output as string.
	 */
	static public function debugStr($aVar, $aNewLineReplacement=' ') {
		$s = '';
		/* I like my own var_dump better now. :)
		if (version_compare(phpversion(), "5.6.0", ">=")) {
			ob_start();
			var_dump($aVar); //5.6+ takes into account the magic method __debugInfo()
			$s = ob_get_clean();
		} else */{
			$s = self::var_dump($aVar, !isset($aNewLineReplacement));
			if (isset($aNewLineReplacement)) {
				$s = str_replace('- ','',$s);
			}
		}
		if (isset($aNewLineReplacement)) {
			return str_replace("\n",$aNewLineReplacement,$s);
		} else {
			return $s;
		}
	}
	
	/**
	 * Sets/Gets the debug prefix string in use.
	 * @param string $aPrefix - (optional) if not null, it will set the value.
	 * @return string Returns the currently set debug prefix (defaults to "[dbg] ").
	 */
	static public function debugPrefix($aPrefix=null) {
		static $myDebugPrefix = '[dbg] ';
		if (isset($aPrefix))
			$myDebugPrefix = $aPrefix;
		return $myDebugPrefix;
	}

	/**
	 * Send the string parameter to the debug log.
	 * Current implementation is the LOG_ERR destination with
	 * a prefix of "[dbg] " prepended to the parameter.
	 * @param string $s - string to send to the debug log.
	 */
	static public function debugLog($s) {
		if (!is_string($s))
			$s = self::debugStr($s);
		syslog(LOG_ERR,self::debugPrefix().$s);
	}
	
	static protected function upperStrMatches($matches) {
		$num_matches = count($matches);
		if ($num_matches>1) {
			$theResult = strtoupper($matches[1]);
			for ($i=2; $i<$num_matches; $i++) {
				$theResult .= strtoupper($matches[$i]);
			}
		} else {
			$theResult = $matches[0];
		}
		return $theResult;
	}

	/**
	 * Converts the name from under_score to CamelCase.
	 * e.g. "this_class_name" -> "ThisClassName"
	 * @param string $aName - potential class name.
	 * @return string Returns the name as a standard Class name.
	 */
	static public function getClassName($aName) {
		return preg_replace_callback('+(?:^|_)(.?)+', array(__CLASS__, 'upperStrMatches'), $aName);
	}

	/**
	 * Converts the name from under_score to CamelCase.
	 * e.g. "this_method_name" -> "thisMethodName"
	 * @param string $aName - potential method name.
	 * @return string Returns the name as a standard method name.
	 */
	static public function getMethodName($aName) {
		return preg_replace_callback('+_(.?)+', array(__CLASS__, 'upperStrMatches'), $aName);
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
	 * @param number $aWidth - (optional) max width to wrap around.
	 * @param string $aBreak - (optional) use this text as the break.
	 * @param boolean $bCut - (optional)
	 * @return string Returns the string with added $aWrappers inserted into $aStr.
	 * @link http://php.net/manual/en/function.wordwrap.php#107570
	 */
	static public function wordWrap($aStr, $aWidth=75, $aBreak="\n", $bCut=false) {
		if ($bCut) {
			// Match anything 1 to $width chars long followed by whitespace or EOS,
			// otherwise match anything $width chars long
			$thePattern = '/(.{1,'.$aWidth.'})(?:\s|$)|(.{'.$aWidth.'})/uS';
			$theReplacement = '$1$2'.$aBreak;
		} else {
			// Anchor the beginning of the pattern with a lookahead
			// to avoid crazy backtracking when words are longer than $width
			$thePattern = '/(?=\s)(.{1,'.$aWidth.'})(?:\s|$)/uS';
			$theReplacement = '$1'.$aBreak;
		}
		return preg_replace($thePattern, $theReplacement, $aStr);
	}

	/**
	 * Similar to trim, but only works on the outer most layer.
	 * @param string $aStr - string to strip off a single enclosure layer.
	 * @param string $aEnclosureA - (optional) the starting enclosure, defaults to '"'.
	 * @param string $aEnclosureB - (optional) the ending enclosure, defaults to $aEnclosureA.
	 * @return string Returns the string stripped of the enclosure, if there was one.
	 */
	static public function stripEnclosure($aStr, $aEnclosureA='"', $aEnclosureB=null) {
		$theResult = $aStr;
		if (empty($aEnclosureB))
			$aEnclosureB = $aEnclosureA;
		if (!empty($aEnclosureA) && !empty($aEnclosureB) &&
				self::beginsWith($aStr, $aEnclosureA) && self::endsWith($aStr, $aEnclosureB)) {
			$theResult = substr($aStr, 1, -1);
		}
		return $theResult;
	}

	/**
	 * Convert a 'key=value' string into array(key, value).
	 * @param string $aStr - the string to parse.
	 * @param string $aDelimiter - (optional) key=value separator, defaults to '='.
	 * @return array Returns array(key, value) or array() if none found.
	 */
	static public function strToKeyValue($aStr, $aDelimiter='=') {
		$theResult = array();
		$thePos = strpos($aStr, $aDelimiter);
		if (is_int($thePos)) {
			$theResult[0] = substr($aStr, 0, $thePos);
			$theResult[1] = substr($aStr, $thePos+strlen($aDelimiter));
		}
		return $theResult;
	}

	/**
	 * @return string - Returns the http/https scheme in use followed by '://'.
	 */
	static public function getUrlSchemeInUse() {
		$bUsingHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on')
				/*
				 * a de facto standard for identifying the originating protocol of an HTTP request,
				 * since a reverse proxy (load balancer) may communicate with a web server using HTTP
				 * even if the request to the reverse proxy is HTTPS.
				 */
				|| (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&  $_SERVER['HTTP_X_FORWARDED_PROTO']=='https')
				/*
				 * Non-standard header field used by Microsoft applications and load-balancers
				 */
				|| (!empty($_SERVER['FRONT-END-HTTPS']) &&  $_SERVER['FRONT-END-HTTPS']=='on')
		;
		return (($bUsingHttps) ? 'https' : 'http') . '://';
	}
	
	/**
	 * Returns the requested number of PHP_EOL constants, or only one if a value
	 * less than or equal to 1 is passed. Use this to append newlines to any
	 * long multi-line string that will be displayed in the output stream.
	 * @param number $aCount the number of newline constants to be returned
	 * @return string the requested number of newline constants
	 */
	static public function eol( $aCount=1 )
	{ return Strings::repeat( PHP_EOL, $aCount ) ; }
	
	/**
	 * Returns the requested number of spaces, or one if no number is specified.
	 * Use this to auto-indent lines of text.
	 * @param number $aCount the number of spaces to be returned
	 * @return string a string with the requested number of spaces
	 */
	static public function spaces( $aCount=1 )
	{ return Strings::repeat( ' ', $aCount ) ; }
	
	/**
	 * Returns the requested string repeated some number of times.
	 * @param string $aToken a string token to be repeated
	 * @param number $aCount a number of times to repeat it
	 * @return string the token, repeated the specified number of times
	 */
	static public function repeat( $aToken, $aCount=1 )
	{
		if( strlen($aToken) == 0 ) return '' ;
		else if( $aCount == 1 ) return $aToken ;
		else if( $aCount > 0 )
		{
			$theString = '' ;
			for( $i = 0 ; $i < $aCount ; $i++ )
				$theString .= $aToken ;
			return $theString ;
		}
		else return '' ;
	}
	
	/**
	 * Translates some semantic size indicator to bytes.
	 * See http://stackoverflow.com/a/22500394/2736531
	 * @param string $aSize Some semantic specification of size. If strictly
	 *  numeric, then bytes are assumed, and the same number is returned.
	 *  If empty or null, then zero is returned. If not parseable, -1 is
	 *  returned.
	 */
	static public function semanticSizeToBytes( $aSize )
	{
		if( ! isset($aSize) ) return 0 ;        // null/unset translates to zero
		if( is_numeric($aSize) ) return $aSize ;     // numeric is already bytes
		
		$theSuffix = strtoupper(substr( $aSize, -1 )) ;
		if( ! strpos( 'PTGMK', $theSuffix ) ) return -1 ;      // invalid suffix
		
		$theMantissa = substr( $aSize, 0, -1 ) ;
		if( ! is_numeric($theMantissa) ) return -1 ;    // can't parse to number
		
		$theValue = $theMantissa ;
		
		switch( $theSuffix )
		{ // intentionally fall through orders of magnitude to multiply again
			case 'P' : $theValue *= 1024 ; // petabytes
			case 'T' : $theValue *= 1024 ; // terabytes
			case 'G' : $theValue *= 1024 ; // gigabytes
			case 'M' : $theValue *= 1024 ; // megabytes
			case 'K' : $theValue *= 1024 ; // kilobytes
				break ;
			default : ; // Can't happen...?
		}
		
		return $theValue ;
	}
	
	/**
	 * Deep array check for encoding to utf8.
	 * @param string|array $aInput - string or array input
	 * @return string Return the thing encoded as utf8 string.
	 */
	static public function deep_utf8_encode($aInput) {
		if (is_array($aInput)) {
			return array_map('com\blackmoonit\Strings::deep_utf8_encode', $aInput);
		} else if (is_string($aInput)) {
			return utf8_encode($aInput);
		} else
			return $aInput;
	}
	
	/**
	 * Deep array check for mb_convert_encoding (utf8).
	 * @param string|array $aInput - string or array input
	 * @return string Return the input "fixed" for utf8.
	 */
	static public function deep_mb_convert_encoding($aInput) {
		if (is_array($aInput)) {
			return array_map('com\blackmoonit\Strings::deep_mb_convert_encoding', $aInput);
		} else if (is_string($aInput)) {
			return @mb_convert_encoding($aInput,'UTF-8','auto');
		} else
			return $aInput;
	}
	
	/**
	 * Convert an HTTP header name to its key in the $_SERVER PHP global var.
	 * @param string $aHeaderName - the name of the HTTP header.
	 */
	static public function httpHeaderNameToServerKey($aHeaderName) {
		return 'HTTP_'.strtoupper(preg_replace('/\W+/m', '_', $aHeaderName));
	}
	
	/**
	 * Recombine the array made by parse_url() into a string.
	 * @param array $aParsedUrl
	 * @return string Returns the url string.
	 * @see parse_url()
	 */
	static public function recombineUrl($aParsedUrl) {
		$scheme = isset($aParsedUrl['scheme']) ? "{$aParsedUrl['scheme']}://" : '';
		$host = isset($aParsedUrl['host']) ? $aParsedUrl['host'] : '';
		$port = isset($aParsedUrl['port']) ? ":{$aParsedUrl['port']}" : '';
		$user = isset($aParsedUrl['user']) ? $aParsedUrl['user'] : '';
		$pass = isset($aParsedUrl['pass']) ? ":{$aParsedUrl['pass']}"  : '';
		$pass = ($user || $pass) ? "{$pass}@" : '';
		$path = isset($aParsedUrl['path']) ? $aParsedUrl['path'] : '';
		$query = isset($aParsedUrl['query']) ? "?{$aParsedUrl['query']}" : '';
		$fragment = isset($aParsedUrl['fragment']) ? "#{$aParsedUrl['fragment']}" : '';
		return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
	}

}//end class

/* increase default crypto strength (04-31) based on PHP version
 * NOTE: cryto strength is exponentially longer, 8 takes a while, 15 takes a LOT longer;
 *       base the strength on the power/speed of the server you are using.
 */
if (version_compare(PHP_VERSION, '5.3.7') >= 0) {
	Strings::$crypto_strength = '11';
}

}//end namespace
