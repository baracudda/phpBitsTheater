<?php
/*
 * Copyright (C) 2020 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe;
use stdClass as BaseClass;
use BitsTheater\costumes\AuthOrg;
use BitsTheater\costumes\IDirected;
use BitsTheater\models\Auth as AuthModel;
use com\blackmoonit\Strings;
{

/**
 * Provides a standardized way to log JSON messages.
 * @since BitsTheater 5.1.0
 */
class LogMessage extends BaseClass
{
	/** @var int val=1, PHP's LOG_level constants differ by OS, use our own. See Strings::getLogLevelName() */
	const LOG_CRITICAL = 1;
	/** @var int val=4, PHP's LOG_level constants differ by OS, use our own. See Strings::getLogLevelName() */
	const LOG_ERROR = 4;
	/** @var int val=5, PHP's LOG_level constants differ by OS, use our own. See Strings::getLogLevelName() */
	const LOG_WARNING = 5;
	/** @var int val=6, PHP's LOG_level constants differ by OS, use our own. See Strings::getLogLevelName() */
	const LOG_INFO = 6;
	/** @var int val=7, PHP's LOG_level constants differ by OS, use our own. See Strings::getLogLevelName() */
	const LOG_DEBUG = 7;
	
	/** @var \BitsTheater\Director The director. */
	protected $mDirector = null;
	/** @var int One of system or our LOG_* consts. */
	protected $mLevel = self::LOG_INFO;
	/** @var string Timestamp of log. */
	protected $mTimestamp = null;
	/** @var string[] key=>value array of log information. */
	protected $mInfo = array();
	
	
	/**
	 * Construct this object with default values.
	 * @param IDirected $aContext - the context to use.
	 */
	public function __construct( IDirected $aContext=null )
	{
		global $director;
		$this->mDirector = ( $aContext != null ) ? $aContext->getDirector() : $director;
		$this->mTimestamp = gmdate("Y-m-d\TH:i:s\Z");
		$theDirector = $this->getDirector();
		if ( !empty($theDirector) ) {
			//website info
			$this->setInfo('site', $theDirector->getFullUrl());
			if ( !$theDirector->isGuest() ) {
				$myAcctInfo = $theDirector->getMyAccountInfo();
				$this->setInfo('auth_id', $myAcctInfo->auth_id);
				$this->setInfo('auth_num', $myAcctInfo->account_id);
				$this->setInfo('auth_name', $myAcctInfo->account_name);
				//org info
				$theOrg = $myAcctInfo->mSeatingSection;
				if ( !empty($theOrg) ) {
					$this->setInfo('org_id', $theOrg->org_id);
					$this->setInfo('org_name', $theOrg->org_name);
					$this->setInfo('org_title', $theOrg->org_title);
					if ( !empty($theOrg->parent_org_id) ) {
						$this->setInfo('parent_org_id', $theOrg->parent_org_id);
						try {
							if ( empty($theOrg->parent_org) ) {
								$dbAuth = $theDirector->getPropsMaster()->getAuthModel();
								$theOrg->parent_org = AuthOrg::fetchInstanceFromRow(
										$dbAuth->getOrganization($theOrg->parent_org_id), $dbAuth
								);
							}
							$this->setInfo('parent_org_name', $theOrg->parent_org->org_name);
							$this->setInfo('parent_org_title', $theOrg->parent_org->org_title);
						}
						catch( \Exception $ignore ) {}
					}
					else {
						//if no parent org, put itself into parent_* so easy Kibana filters.
						$this->setInfo('parent_org_id', $theOrg->org_id);
						$this->setInfo('parent_org_name', $theOrg->org_name);
						$this->setInfo('parent_org_title', $theOrg->org_title);
					}
				}
				else {
					$this->setInfo('org_id', AuthModel::ORG_ID_4_ROOT);
					$this->setInfo('org_name', 'root');
					$this->setInfo('org_title', 'Root');
				}
			}
		}
	}
	
	/**
	 * Create logger object with a context to use for default info.
	 * @param IDirected $aContext - the context to use.
	 * @return $this Returns the newly created object.
	 */
	static public function withContext( IDirected $aContext )
	{
		$theClass = get_called_class();
		return (new $theClass($aContext));
	}
	
	/**
	 * Create logger object with the global context to use for default info.
	 * withContext() is preferred, but not always possible.
	 * @return $this Returns the newly created object.
	 */
	static public function withGlobalContext()
	{
		$theClass = get_called_class();
		return (new $theClass());
	}
	
	/** @return \BitsTheater\Director Returns the Director. */
	public function getDirector()
	{ return $this->mDirector; }
	
	/** @return int Accessor for the condition token. */
	public function getLevel()
	{ return $this->mLevel; }
	
	/**
	 * Setter for log level.
	 * @param int $aLevel - level of log message, one of the LOG_* consts.
	 * @return $this Returns $this for chaining.
	 */
	public function setLevel( $aLevel )
	{ $this->mLevel = $aLevel; return $this; }
	
	/**
	 * Get the log info if it has been assigned.
	 * @param string $aKey - the key for the data.
	 * @return mixed Returns the value of the key.
	 */
	public function getInfo( $aKey )
	{
		if ( $aKey == null ) return $this->mInfo;
		$theVal = null;
		if ( !empty($this->mInfo) && !empty($this->mInfo[$aKey]) ) {
			$theVal = $this->mInfo[$aKey];
		}
		return $theVal;
	}
	
	/**
	 * Flesh out what information should be logged in a structured manner.
	 * @param string $aKey - the data key
	 * @param mixed $aValue - the data value
	 * @return $this Returns $this for chaining.
	 */
	public function setInfo( $aKey, $aValue )
	{
		if ( $aValue == null ) return $this; //trivial
		if ( $aKey == null && is_array($aValue) ) {
			$this->mInfo = array_merge($this->mInfo, $aValue);
		}
		else if ( $aKey == null && is_object($aValue) ) {
			$this->mInfo = array_merge($this->mInfo, (array)$aValue);
		}
		else if ( $aKey == null && is_string($aValue) ) {
			$this->mInfo['message'] = $aValue;
		}
		else if ( $aKey != null ) {
			$this->mInfo[$aKey] = $aValue;
		}
		return $this;
	}
	
	/**
	 * Alias for setInfo(null, $aInfo), cleaner readability.
	 * @param string|string[]|object $aInfo - the info set to use.
	 * @return $this Returns $this for chaining.
	 */
	public function withInfo( $aInfo )
	{ return $this->setInfo(null, $aInfo); }
	
	/**
	 * Simple smashing all info as one string.
	 * @return string Returns all the info as one message.
	 */
	public function getMessage()
	{
		$theStr = ( $this->getLevel() == static::LOG_ERROR ) ? Strings::errorPrefix() : Strings::debugPrefix();
		if ( !empty($this->mInfo) ) {
			foreach( $this->mInfo as $theKey => $theVal ) {
				if ( is_string($theVal) || is_numeric($theVal) ) {
					$theStr .= $theKey . '=[' . $theVal . '] ';
					
				}
				else if ( $theVal == null ) {
					$theStr .= $theKey . '=NULL ';
				}
				else {
					$theStr .= $theKey . '={@see JSON} ';
				}
			}
		}
		return trim($theStr);
	}
	
	/**
	 * Forms the object representing this log statement.
	 * @param int $aLogLevel - (OPTIONAL) export this log level, not what we currently have defined.
	 * @return object Returns an object.
	 */
	public function toLogObject( $aLogLevel=null )
	{
		$theLog = new \stdClass() ;
        $theLog->level_num = ( !empty($aLogLevel) ) ? $aLogLevel : $this->getLevel();
        $theLog->level = Strings::getLogLevelName($theLog->level_num);
		$theLog->message = $this->getMessage();
		$theLog->timestamp = $this->mTimestamp;
		if ( !empty($this->mInfo) ) {
			foreach( $this->mInfo as $theKey => $theVal ) {
				$theLog->{$theKey} = $theVal;
			}
		}
		return $theLog;
	}

	/**
	 * Returns the representation of this object that is appropriate to encode.
	 * @param int $aLogLevel - (OPTIONAL) export this log level, not what we currently have defined.
	 * @return object Returns the object to encode.
	 */
	public function exportData( $aLogLevel=null )
	{ return $this->toLogObject($aLogLevel); }

	/**
	 * As toLogObject(), but serializes that object to a JSON string.
	 * @param int $aLogLevel - (OPTIONAL) export this log level, not what we currently have defined.
	 * @param string $aEncodeOptions - the JSON encoding options
	 * @return string Returns a JSON serialization of the standard response object.
	 */
	public function toJson( $aLogLevel=null, $aEncodeOptions=null )
	{ return json_encode($this->exportData($aLogLevel), $aEncodeOptions); }

	/**
	 * Log information as a JSON encoded object at level without saving the level.
	 * @return $this Returns $this for chaining.
	 */
	public function logAs( $aLevel )
	{ Strings::log($aLevel, $this); return $this; }
	
	/**
	 * Log information as a JSON encoded object.
	 * @return $this Returns $this for chaining.
	 */
	public function log()
	{ return $this->logAs($this->getLevel()); }
	
	/**
	 * Convenience method for combining withInfo()->log().
	 * @param string|string[]|object $aInfo - the info set to use.
	 * @return $this Returns $this for chaining.
	 */
	public function logWith( $aInfo )
	{ return $this->withInfo($aInfo)->log(); }
	
	/**
	 * Set all subsequent log levels to the given value.
	 * @param int $aLevel - level of log message, one of the LOG_* consts.
	 * @return $this Returns $this for chaining.
	 */
	public function logTo( $aLevel )
	{ return $this->setLevel($aLevel)->log(); }
	
	/**
	 * Log information as a JSON encoded object to DEBUG level without saving the level.
	 * @return $this Returns $this for chaining.
	 */
	public function logToDebug()
	{ return $this->logAs(static::LOG_DEBUG); }
	
	/**
	 * Log information as a JSON encoded object to ERROR level without saving the level.
	 * @return $this Returns $this for chaining.
	 */
	public function logToError()
	{ return $this->logAs(static::LOG_ERROR); }
	
} //end class

} //end namespace
