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
// ^^^ will be done via lib/vendor/compose magic
use PHPMailer\PHPMailer\PHPMailer;
{//begin namespace

/**
 * Provides methods to statically create PHPMailer objects for outgoing email
 * messages.
 * See https://github.com/PHPMailer/PHPMailer
 */
class MailUtils
{
	private function __construct() {} // static invocation only
	
	/** @var string The fully qualified name of the object class we use. */
	const CLASS_OF_MAILER = PHPMailer::class;
	
	public static array $mEmailConfigUsed;
	
	/**
	 * PCRE regex pattern to recognize an email address. This is actually a
	 * narrowing of http://tools.ietf.org/html/rfc5322#section-3.4 which limits
	 * the final segment to one of the recognized ICANN top-level domains and/or
	 * a two-character country code.
	 */
	const RFC5322_EMAIL_REGEX = '/[\w\.\-\!\?\#\$\%\^\&\*\(\)\{\}]+@[\w\.\-]+\.(?:com|org|net|edu|gov|mil|biz|info|mobi|name|aero|asia|jobs|museum|[A-Za-z]{2})/' ;
	
	protected static $REQUIRED_CONFIGS = array( 'host', 'port', 'user', 'pwd' );
	protected static $OPTIONAL_CONFIGS = array( 'security' => '', 'default_from' => '' );
	
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
	static protected function validateMailerConfig( $aConfigArray )
	{
		$theValidated = array() ;
		foreach( self::$REQUIRED_CONFIGS as $theSetting )
		{
			if( ! empty( $aConfigArray[$theSetting] ) )
				$theValidated[$theSetting] = $aConfigArray[$theSetting] ;
			else
				throw MailUtilsException::exceptMissingConfig( $theSetting ) ;
		}
		foreach( self::$OPTIONAL_CONFIGS as $theSetting => $theDefault )
		{
			if( ! empty( $aConfigArray[$theSetting] ) )
				$theValidated[$theSetting] = $aConfigArray[$theSetting] ;
			else
				$theValidated[$theSetting] = $theDefault ;
		}
		return $theValidated ;
	}
	
	/**
	 * Converts a config settings object into an array for internal use.
	 * @param object $aConfigObject an object containing fields "host", "port",
	 *   "user", and "pwd".
	 * @return array a config settings array
	 */
	static protected function objectToArray( $aConfigObject )
	{
		$theConfigArray = array() ;
		foreach( self::$REQUIRED_CONFIGS as $theSetting )
		{
			if( ! empty( $aConfigObject->$theSetting ) )
				$theConfigArray[$theSetting] = $aConfigObject->$theSetting ;
			else
				throw MailUtilsException::exceptMissingConfig( $theSetting ) ;
		}
		foreach( self::$OPTIONAL_CONFIGS as $theSetting => $theDefault )
		{
			if( ! empty( $aConfigObject->$theSetting ) )
				$theConfigArray[$theSetting] = $aConfigObject->$theSetting ;
			else
				$theConfigArray[$theSetting] = $theDefault ;
		}
		return $theConfigArray ;
	}
	
	/**
	 * Extracts config settings from a BitsTheater config array in which the
	 * elements are addressed at the specified config setting path.
	 * @param \ArrayAccess $aBitsConfig a BitsTheater config
	 * @param string $aPath a path to the email settings
	 * @return array a config settings array
	 */
	static protected function bitsConfigToArray( $aBitsConfig, $aPath )
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
	private static function canAssignFromBitsConfig($aBitsConfig, $aSettingPath): bool
	{
		try { $theValue = $aBitsConfig[$aSettingPath] ; }
		catch( \Exception $x ) { return false ; }
		return( !empty($theValue) ) ;
	}
	
	/**
	 * Returns a PHP mailer object to the public method that asked for it.
	 * @param array $aValidatedArray a validated array of config settings, which
	 *  has been processed by either validateMailerConfig() or objectToArray().
	 * @return PHPMailer Returns the newly constructed and initialized object.
	 */
	static protected function buildMailer( array $aValidatedArray ): PHPMailer
	{
		$theMailer = new PHPMailer ;
		$theMailer->isSMTP() ;
		$theMailer->SMTPDebug = 0 ;
		$theMailer->SMTPAuth = true ;
		$theMailer->Host = $aValidatedArray['host'] ;
		$theMailer->Port = $aValidatedArray['port'] ;
		$theMailer->Username = $aValidatedArray['user'] ;
		$theMailer->Password = $aValidatedArray['pwd'] ;
		$theMailer->SMTPSecure = $aValidatedArray['security'] ;
		if ( !empty($aValidatedArray['default_from']) ) {
			$theMailer->setFrom($aValidatedArray['default_from']);
		}
		self::$mEmailConfigUsed = $aValidatedArray;
		return $theMailer;
	}
	
	/**
	 * Builds and returns a PHPMailer object using the host, port, user, and
	 * password settings passed in from the specified array.
	 * @param array $aConfigArray an associative array containing the settings
	 *   "host", "port", "user", and "pwd".
	 * @return PHPMailer|null Returns NULL if PHPMailer cannot be configured.
	 */
	static public function buildMailerFromArray( $aConfigArray )
	{
		$theConfigArray = self::validateMailerConfig($aConfigArray) ;
		return self::buildMailer($theConfigArray) ;
	}
	
	/**
	 * Builds and returns a PHPMailer object using the host, port, user, and
	 * password settings passed in from the specified object.
	 * @param object $aConfigObject - an object containing the fields "host",
	 *   "port", "user", and "pwd".
	 * @return PHPMailer|null Returns NULL if PHPMailer cannot be configured.
	 */
	static public function buildMailerFromObject( $aConfigObject )
	{
		$theConfigArray = self::objectToArray($aConfigObject) ;
		return self::buildMailer($theConfigArray) ;
	}
	
	/**
	 * Because of the way that IDs are programmatically generated as paths
	 * in BitsTheater config settings, this function is specially designed to
	 * extract those settings from a particular config group.
	 * @param \ArrayAccess $aBitsConfig the configuration
	 *   settings; this is the whole array for the whole app, not just a section
	 * @param string $aPath the path to be prepended to the name of every
	 *   email host configuration setting
	 * @return PHPMailer|null Returns NULL if PHPMailer cannot be configured.
	 */
	static public function buildMailerFromBitsConfig( $aBitsConfig, $aPath )
	{
		$theEmailServerURL = getenv('EMAIL_SERVER_URL');
		try {
			$theConfigArray = self::bitsConfigToArray($aBitsConfig, $aPath);
		}
		catch (\Exception $x) {
			if ( empty($theEmailServerURL) ) {
				return null;
			}
		}
		if ( !empty($theEmailServerURL) ) {
			$theEnvEmail = parse_url($theEmailServerURL);
			if ( !empty($theEnvEmail) ) {
				$theConfigArray['host'] = !empty($theEnvEmail['host']) ? $theEnvEmail['host'] : 'localhost';
				switch ( $theEnvEmail['scheme'] ) {
					case 'submission':
					case 'submit':
					{
						$theConfigArray['port'] = !empty($theEnvEmail['port']) ? intval($theEnvEmail['port']) : 587;
						$theConfigArray['security'] = 'tls';
						break;
					}
					case 'smtp':
					default:
					{
						$theConfigArray['port'] = !empty($theEnvEmail['port']) ? intval($theEnvEmail['port']) : 25;
						$theConfigArray['security'] = 'ssl';
						break;
					}
				}//end switch
				$theConfigArray['user'] = !empty($theEnvEmail['user']) ? $theEnvEmail['user'] : '';
				if ( empty($theConfigArray['user']) && getenv('EMAIL_SERVER_ACCT_NAME') ) {
					$theConfigArray['user'] = getenv('EMAIL_SERVER_ACCT_NAME');
				}
				$theConfigArray['pwd'] = !empty($theEnvEmail['pass']) ? $theEnvEmail['pass'] : '';
				if ( empty($theConfigArray['pwd']) && getenv('EMAIL_SERVER_ACCT_PSWD') ) {
					$theConfigArray['pwd'] = getenv('EMAIL_SERVER_ACCT_PSWD');
				}
				if ( !empty($theEnvEmail['query']) ) {
					parse_str($theEnvEmail['query'], $qvars);
					if ( !empty($qvars['_default_from_email']) ) {
						$theConfigArray['default_from'] = $qvars['_default_from_email'];
					}
				}
				if ( empty($theConfigArray['default_from']) ) {
					$theConfigArray['default_from'] = $theConfigArray['user'];
				}
			}
		}
		return self::buildMailer($theConfigArray) ;
	}
	
	/**
	 * Return the config value used for the mailer object.
	 * @param string $aKey - the config key name.
	 * @return mixed
	 */
	public static function getEmailConfigUsed( string $aKey ): mixed
	{
		$theResult = null;
		if ( !empty(self::$mEmailConfigUsed) && !empty(self::$mEmailConfigUsed[$aKey]) ) {
			$theResult = self::$mEmailConfigUsed[$aKey];
			if ( $aKey == 'port' ) {
				$theResult = intval($theResult);
			}
		}
		return $theResult;
	}
	
} // end MailUtils class

class MailUtilsException extends \Exception
{
	const ERR_MISSING_SETTING = -1 ;
	
	static public function exceptMissingConfig( $aSetting )
	{
		$theMessage = "Mailer was not given the required setting [{$aSetting}].";
		return new MailUtilsException( $theMessage, self::ERR_MISSING_SETTING );
	}
} // end MailUtilsException class

} // end namespace
