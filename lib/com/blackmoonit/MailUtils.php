<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
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

namespace com\blackmoonit ;
//require_once( 'PHPMailerAutoload.php' ) ; // for outgoing mail features
use com\blackmoonit\BrokenLeg ;
use com\blackmoonit\Strings ;
{//begin namespace

/**
 * Provides methods to statically create PHPMailer objects for outgoing email
 * messages.
 * See https://github.com/PHPMailer/PHPMailer
 */
class MailUtils
{
	private function __construct() {} // static invocation only

	/** 
	 * PCRE regex pattern to recognize an email address. This is actually a
	 * narrowing of http://tools.ietf.org/html/rfc5322#section-3.4 which limits
	 * the final segment to one of the recognized ICANN top-level domains and/or
	 * a two-character country code.
	 */
	const RFC5322_EMAIL_REGEX = '/[\w\.\-\!\?\#\$\%\^\&\*\(\)\{\}]+@[\w\.\-]+\.(?:com|org|net|edu|gov|mil|biz|info|mobi|name|aero|asia|jobs|museum|[A-Za-z]{2})/' ;
	
	protected static $REQUIRED_CONFIGS = array( 'host', 'port', 'user', 'pwd' );
	protected static $OPTIONAL_CONFIGS = array( 'security' => '' ) ;
	
	/**
	 * Discovers all email addresses in a string.
	 * @param string $aHaystack a string to be searched
	 * @return array an array of email addresses
	 */
	public static function discoverAllEmailAddressesIn( $aHaystack )
	{
		preg_match_all( self::RFC5322_EMAIL_REGEX, $aHaystack, $theMatches ) ;
		return $theMatches[0] ;
	}
	
	/**
	 * Validates an array of settings that would initialize a mailer.
	 * @param array $aConfigArray an array of settings
	 * @return array an array containing only the mailer config settings
	 */
	static protected function validateMailerConfig( &$aConfigArray )
	{
		$theValidated = array() ;
		foreach( self::$REQUIRED_CONFIGS as $theSetting )
		{
			if( configInArray( $aConfigArray, $theSetting ) )
				$theValidated[$theSetting] = $aConfigArray[$theSetting] ;
			else
				throw MailUtilsException::exceptMissingConfig( $theSetting ) ;
		}
		foreach( self::$OPTIONAL_CONFIGS as $theSetting => $theDefault )
		{
			if( self::configInArray( $aConfigArray, $theSetting ) )
				$theValidated[$theSetting] = $aConfigArray[$theSetting] ;
			else
				$theValidated[$theSetting] = $theDefault ;
		}
		return $theValidated ;
	}

	/** Consumed by validateMailerConfig() */
	private static function configInArray( &$aConfigArray, $aSetting )
	{
		return( array_key_exists( $aSetting, $aConfigArray )
				&& isset( $aConfigArray[$aSetting] )
				&& ! empty( $aConfigArray[$aSetting] ) ) ;
	}
	
	/**
	 * Converts a config settings object into an array for internal use.
	 * @param object $aConfigObject an object containing fields "host", "port",
	 *  "user", and "pwd". 
	 * @return array a config settings array
	 */
	static protected function objectToArray( &$aConfigObject )
	{
		$theConfigArray = array() ;
		foreach( self::$REQUIRED_CONFIGS as $theSetting )
		{
			if( self::configInObject( $aConfigObject, $theSetting ) )
				$theConfigArray[$theSetting] = $aConfigObject->$theSetting ;
			else
				throw MailUtilsException::exceptMissingConfig( $theSetting ) ;
		}
		foreach( self::$OPTIONAL_CONFIGS as $theSetting => $theDefault )
		{
			if( self::configInObject( $aConfigObject, $theSetting ) )
				$theConfigArray[$theSetting] = $aConfigObject->$theSetting ;
			else
				$theConfigArray[$theSetting] = $theDefault ;
		}
		return $theConfigArray ;
	}
	
	/** Consumed by objectToArray() */
	private static function configInObject( &$aConfigObject, $aSetting )
	{
		return( property_exists( $aConfigObject, $aSetting )
				&& isset( $aConfigObject->$aSetting )
				&& ! empty( $aConfigObject->aSetting ) ) ;
	}
	
	/**
	 * Extracts config settings from a BitsTheater config array in which the
	 * elements are addressed at the specified config setting path.
	 * @param array $aBitsConfig a BitsTheater config array
	 * @param string $aPath a path to the email settings
	 * @return array a config settings array
	 */
	static protected function bitsConfigToArray( &$aBitsConfig, $aPath )
	{
		$theConfigArray = array() ;
		foreach( self::$REQUIRED_CONFIGS as $theSetting )
		{
			$theConfigPath = $aPath . '/' . $theSetting ;
			if( self::canAssignFromBitsConfig( $aBitsConfig, $theConfigPath ) )
				$theConfigArray[$theSetting] = $aBitsConfig[$theConfigPath] ;
			else
				throw MailUtilsException::exceptMissingConfig($theSetting) ;
		}
		foreach( self::$OPTIONAL_CONFIGS as $theSetting => $theDefault )
		{
			$theConfigPath = $aPath . '/' . $theSetting ;
			if( self::canAssignFromBitsConfig( $aBitsConfig, $theConfigPath ) )
				$theConfigArray[$theSetting] = $aBitsConfig[$theConfigPath] ;
			else
				$theConfigArray[$theSetting] = $theDefault ;
		}
		return $theConfigArray ;
	}
	
	/** Consumed by bitsConfigToArray() */
	private static function canAssignFromBitsConfig(&$aBitsConfig,$aSettingPath)
	{
		$theValue = null ;
		try { $theValue = $aBitsConfig[$aSettingPath] ; }
		catch( Exception $x ) { return false ; }
		
		return( isset($theValue) && !empty($theValue) ) ;
	}
	
	/**
	 * Returns a PHP mailer object to the public method that asked for it.
	 * @param array $aValidatedArray a validated array of config settings, which
	 *  has been processed by either validateMailerConfig() or objectToArray().
	 * @return object a PHPMailer object
	 */
	static protected function buildMailer( &$aValidatedArray )
	{
		$theMailer = new \PHPMailer ;
		$theMailer->isSMTP() ;
		$theMailer->SMTPDebug = 0 ;
		$theMailer->SMTPAuth = true ;
		$theMailer->Host = $aValidatedArray['host'] ;
		$theMailer->Port = $aValidatedArray['port'] ;
		$theMailer->Username = $aValidatedArray['user'] ;
		$theMailer->Password = $aValidatedArray['pwd'] ;
		$theMailer->SMTPSecure = $aValidatedArray['security'] ;
		return $theMailer ;
	}
	
	/**
	 * Builds and returns a PHPMailer object using the host, port, user, and
	 * password settings passed in from the specified array. 
	 * @param array $aConfigArray an associative array containing the settings
	 *  "host", "port", "user", and "pwd".
	 * @return object a PHPMailer object, or null if it can't be configured
	 */
	static public function buildMailerFromArray( &$aConfigArray )
	{
		$theConfigArray = self::validateMailerConfig($aConfigArray) ;
		return self::buildMailer($aConfigArray) ;
	}
	
	/**
	 * Builds and returns a PHPMailer object using the host, port, user, and
	 * password settings passed in from the specified object.
	 * @param unknown $aConfigObject an object containing the fields "host",
	 *  "port", "user", and "pwd".
	 * @return object a PHPMailer object, or null if it can't be configured
	 */
	static public function buildMailerFromObject( &$aConfigObject )
	{
		$theConfigArray = self::objectToArray($aConfigObject) ;
		return self::buildMailer($theConfigArray) ;
	}
	
	/**
	 * Because of the wacky way that IDs are programmatically generated as paths
	 * in BitsTheater config settings, this function is specially designed to
	 * extract those settings from a particular config group.
	 * @param unknown $aBitsConfig an array of BitsTheater configuration
	 *  settings; this is the whole array for the whole app, not just a section
	 * @param unknown $aPath the path to be prepended to the name of every
	 *  email host configuration setting
	 * @return object a PHPMailer object, or null if it can't be configured
	 */
	static public function buildMailerFromBitsConfig( &$aBitsConfig, $aPath )
	{
		$theConfigArray = self::bitsConfigToArray( $aBitsConfig, $aPath ) ;
		return self::buildMailer($theConfigArray) ;
	}
	
} // end MailUtils class

class MailUtilsException extends \Exception
{
	const ERR_MISSING_SETTING = -1 ;
	
	static public function exceptMissingConfig( $aSetting )
	{
		$theMessage = 'Mailer was not given the required setting ['
				. $aSetting . '].'
				;
		return new MailUtilsException( $theMessage, self::ERR_MISSING_SETTING );
	}
} // end MailUtilsException class

} // end namespace
