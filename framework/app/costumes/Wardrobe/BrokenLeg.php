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

namespace BitsTheater\costumes\Wardrobe;
use \Exception as BaseException;
use BitsTheater\costumes\APIResponse as APIResponseInUse;
use BitsTheater\costumes\IDirected;
use com\blackmoonit\exceptions\IDebuggableException;
use com\blackmoonit\exceptions\DbException;
{

/**
 * Provides a standardized way to design a custom exception that can use the
 * BitsTheater text resources (which can be translated to multiple languages) as
 * the basis of the exception's message. Some standard error messages are
 * defined here, corresponding to general-purpose error messages in the
 * BitsGeneric resource.
 *
 * A consumer of this class would call the static toss() method, passing in a
 * resource context (actor, model, or scene), a semantic exception tag, and
 * (optionally) additional data that is part of the corresponding text message
 * resource.
 *
 * The class is self-sufficient for generating standard exceptions; to extend
 * it, your custom exception class need only provide additional constants with
 * names following the covention of "ERR_tag" and "MSG_tag", where the "ERR_"
 * constant is a numeric code, and the "MSG_" tag refers to a translated text
 * resource name. Neither the code nor the message need be unique; several error
 * scenarios could mapped to a common code or to a common message. Only the tag
 * used to choose the exception condition need be unique, and that uniqueness is
 * enforced by making it the name of the constant in the class definition.
 *
 * The class also provides mnemonic constants for a selection of HTTP error
 * codes, so that the numeric constants for errors can be more obviously tied to
 * those standard codes.
 */
class BrokenLeg extends BaseException
{
	// Constants for a subset of standard HTTP status codes.
	// Even "successful" codes are noted here, just to keep them all together.
	// https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
	const HTTP_OK = 200 ;
	const HTTP_CREATED = 201 ;
	const HTTP_ACCEPTED = 202 ; // A non-committal success response; stay tuned.
	const HTTP_NO_CONTENT = 204 ;
	const HTTP_MULTISTATUS = 207 ;           // Warns of only a partial success.
	const HTTP_ALREADY_REPORTED = 208 ;
	const HTTP_MULTIPLE_CHOICES = 300 ;
	const HTTP_MOVED_PERMANENTLY = 301 ;                 // Deprecated? see 308.
	const HTTP_SEE_OTHER = 303 ;
	const HTTP_NOT_MODIFIED = 304 ;
	const HTTP_TEMPORARY_REDIRECT = 307 ;
	const HTTP_PERMANENT_REDIRECT = 308 ;
	const HTTP_BAD_REQUEST = 400 ;             // Not well-formed; contrast 422.
	const HTTP_UNAUTHORIZED = 401 ;
	const HTTP_PAYMENT_REQUIRED = 402 ;
	const HTTP_FORBIDDEN = 403 ;
	const HTTP_NOT_FOUND = 404 ;
	const HTTP_METHOD_NOT_ALLOWED = 405 ;
	const HTTP_NOT_ACCEPTABLE = 406 ;
	const HTTP_PROXY_AUTH_REQUIRED = 407 ;
	const HTTP_REQUEST_TIMEOUT = 408 ;
	const HTTP_CONFLICT = 409 ;
	const HTTP_GONE = 410 ;
	const HTTP_LENGTH_REQUIRED = 411 ;
	const HTTP_PRECONDITION_FAILED = 412 ;
	const HTTP_PAYLOAD_TOO_LARGE = 413 ;
	const HTTP_URI_TOO_LONG = 414 ;
	const HTTP_UNSUPPORTED_MEDIA_TYPE = 415 ;
	const HTTP_RANGE_NOT_SATISFIABLE = 416 ;
	const HTTP_EXPECTATION_FAILED = 417 ;
	const HTTP_TEAPOT = 418 ;
	const HTTP_ENHANCE_YOUR_CALM = 420 ;  // Burst limit exceeded; contrast 429.
	const HTTP_MISDRECTED_REQUEST = 421 ;
	const HTTP_UNPROCESSABLE_ENTITY = 422 ; // Well-formed but unusable; contrast 400.
	const HTTP_LOCKED = 423 ;
	const HTTP_FAILED_DEPENDENCY = 424 ;
	const HTTP_UPGRADE_REQUIRED = 426 ;
	const HTTP_PRECONDITION_REQUIRED = 428 ;
	const HTTP_TOO_MANY_REQUESTS = 429 ;   // Long-term limit exceeded; contrast 420.
	const HTTP_HEADER_FIELDS_TOO_LARGE = 431 ;
	const HTTP_CENSORED = 451 ;
	const HTTP_INTERNAL_SERVER_ERROR = 500 ;
	const HTTP_NOT_IMPLEMENTED = 501 ;
	const HTTP_BAD_GATEWAY = 502 ;
	const HTTP_SERVICE_UNAVAILABLE = 503 ;
	const HTTP_GATEWAY_TIMEOUT = 504 ;
	const HTTP_HTTP_VERSION_NOT_SUPPORTED = 505 ;
	const HTTP_INSUFFICIENT_STORAGE = 507 ;
	const HTTP_NOT_EXTENDED = 510 ;

	// The default codes here all roughly correspond to HTTP response codes.
	const ERR_DEFAULT =              self::HTTP_INTERNAL_SERVER_ERROR ;
	const ERR_MISSING_ARGUMENT =     self::HTTP_BAD_REQUEST ;
	const ERR_MISSING_VALUE =        self::HTTP_BAD_REQUEST ;
	const ERR_FILE_NOT_FOUND =       self::HTTP_NOT_FOUND ;
	const ERR_FORBIDDEN =            self::HTTP_FORBIDDEN ;
	const ERR_DB_EXCEPTION =         self::HTTP_INTERNAL_SERVER_ERROR ;
	const ERR_ENTITY_NOT_FOUND =     self::HTTP_NOT_FOUND ;
	const ERR_NOT_DONE_YET =         self::HTTP_NOT_IMPLEMENTED ;
	const ERR_DB_CONNECTION_FAILED = self::HTTP_SERVICE_UNAVAILABLE ;
	const ERR_NOT_AUTHENTICATED =    self::HTTP_UNAUTHORIZED ;
	const ERR_SERVICE_UNAVAILABLE =  self::HTTP_SERVICE_UNAVAILABLE ;
	const ERR_TOO_MANY_REQUESTS =    self::HTTP_TOO_MANY_REQUESTS ;
	const ERR_DEPRECATED_FUNCTION =  self::HTTP_GONE ;
	const ERR_NOT_ACCEPTABLE =       self::HTTP_NOT_ACCEPTABLE ;
	
	// General-purpose messages should be defined in the BitsGeneric resource.
	const MSG_DEFAULT = 'generic/errmsg_default' ;
	const MSG_MISSING_ARGUMENT = 'generic/errmsg_arg_is_empty' ;
	const MSG_MISSING_VALUE = 'generic/errmsg_var_is_empty' ;
	const MSG_FILE_NOT_FOUND = 'generic/errmsg_file_not_found';
	const MSG_FORBIDDEN = 'generic/msg_permission_denied' ;
	const MSG_DB_EXCEPTION = 'generic/errmsg_db_exception' ;
	const MSG_ENTITY_NOT_FOUND = 'generic/errmsg_entity_not_found' ;
	const MSG_NOT_DONE_YET = 'generic/errmsg_not_done_yet' ;
	const MSG_DB_CONNECTION_FAILED = 'generic/errmsg_database_not_connected' ;
	const MSG_NOT_AUTHENTICATED = self::MSG_FORBIDDEN ;
	const MSG_SERVICE_UNAVAILABLE = 'generic/errmsg_service_unavailable';
	const MSG_TOO_MANY_REQUESTS = 'generic/errmsg_too_many_requests';
	const MSG_DEPRECATED_FUNCTION = 'generic/errmsg_deprecated' ;
	const MSG_NOT_ACCEPTABLE = 'generic/errmsg_not_acceptable' ;
	
	// Condition constants if you like to use them rather than remember strings to use.
	const ACT_DEFAULT =              'DEFAULT';
	const ACT_MISSING_ARGUMENT =     'MISSING_ARGUMENT';
	const ACT_MISSING_VALUE =        'MISSING_VALUE';
	const ACT_FILE_NOT_FOUND =       'FILE_NOT_FOUND';
	const ACT_FORBIDDEN =            'FORBIDDEN';
	const ACT_DB_EXCEPTION =         'DB_EXCEPTION';
	const ACT_ENTITY_NOT_FOUND =     'ENTITY_NOT_FOUND';
	const ACT_NOT_DONE_YET =         'NOT_DONE_YET';
	const ACT_DB_CONNECTION_FAILED = 'DB_CONNECTION_FAILED';
	const ACT_NOT_AUTHENTICATED =    'NOT_AUTHENTICATED';
	const ACT_SERVICE_UNAVAILABLE =  'SERVICE_UNAVAILABLE';
	const ACT_TOO_MANY_REQUESTS =    'TOO_MANY_REQUESTS';
	const ACT_DEPRECATED_FUNCTION =  'DEPRECATED_FUNCTION';
	const ACT_NOT_ACCEPTABLE =       'NOT_ACCEPTABLE' ;
	// Virtual Conditions that map to other conditions based on certain criteria
	const ACT_PERMISSION_DENIED =    'PERMISSION_DENIED'; //FORBIDDEN or NOT_AUTH
	
	/**
	 * Provides an instance of the exception.
	 * @param IDirected $aContext some BitsTheater object that can provide context
	 *  for the website, so that text resources can be retrieved; this can be an
	 *  actor, model, or scene, or anything implementing IDirected
	 * @param string $aCondition a string uniquely identifying the exceptional
	 *  scenario; this must correspond to one of the constants defined within
	 *  the descendant class
	 * @param string|array $aResourceData (optional) any additional data that would be
	 *  passed into a variable substitution in the definition of a text
	 *  resource; if non-empty, then the initial '/' separator is inserted
	 *  automatically before being used in getRes()
	 * @return $this Returns an instance of the exception class.
	 */
	public static function toss( IDirected $aContext, $aCondition, $aResourceData=null )
	{
		$theClass = get_called_class() ;
		//$aContext cannot be null since we gave it a type hint of IDirected
		if ($aCondition==static::ACT_PERMISSION_DENIED)
		{
			$aCondition = ($aContext->isGuest())
					? static::ACT_NOT_AUTHENTICATED
					: static::ACT_FORBIDDEN
					;
		}
		$theCode = static::ERR_DEFAULT ;
		$theCodeID = $theClass . '::ERR_' . $aCondition ;
		if( defined( $theCodeID ) )
			$theCode = constant( $theCodeID ) ;
		$theMessage = static::MSG_DEFAULT ;
		$theMessageID = $theClass . '::MSG_' . $aCondition ;
		if( defined( $theMessageID ) )
		{
			$theMessage = static::getMessageFromResource(
					$aContext, constant($theMessageID), $aResourceData
			);
		}
		$theException = (new $theClass($theMessage,$theCode))
				->setCondition( $aCondition ) ;
		return $theException ;
	}
	
	/**
	 * Provides an instance of the exception based on an already thrown
	 * exception. If the exception is already an instance of BrokenLeg, it is
	 * immediately thrown back.
	 * @param IDirected $aContext some BitsTheater object that can provide context
	 *  for the website, so that text resources can be retrieved; this can be an
	 *  actor, model, or scene, or anything implementing IDirected
	 * @param \Exception $aException - a thrown exception.
	 * @return $this Returns the newly created object for throwing.
	 */
	static public function tossException( IDirected $aContext, $aException )
	{
		if (ini_get('log_errors') && $aException instanceof IDebuggableException)
		{
			$aContext->getDirector()->logErrors('[1/2]', 'msg: ', $aException->getMessage(),
					' context: ', $aException->getContextMsg()
			);
			$aContext->getDirector()->logErrors('[2/2]', $aContext->getDirector()->formatCallStackAsLogStr(
					$aException->getTraceAsString()
			));
		}

		if( $aException instanceof BrokenLeg )
			return $aException ;
		else if ($aException instanceof DbException || $aException instanceof \PDOException)
		{
			$aDbCode = intval($aException->getCode());
			$theErr = ($aDbCode>2000 && $aDbCode<2030)
					? static::ACT_DB_CONNECTION_FAILED
					: static::ACT_DB_EXCEPTION
					;
			return static::toss($aContext, $theErr, $aException->getMessage());
		}
		else if ( !empty($aException->code) && !empty($aException->message) )
		{ //consider 0 code the same as NULL, same with empty string "" msg.
			return static::pratfall(static::ACT_DEFAULT,
					$aException->code, $aException->message
			);
		}
		else
		{
			$o = static::toss( $aContext, static::ACT_DEFAULT ) ;
			$theErrMsg = $aException->getMessage();
			if (!empty($theErrMsg))
				$o->message = $theErrMsg;
			return $o;
		}
	}
	
	/** Stores the original condition code that was passed into toss(). */
	protected $myCondition ;
	
	/**
	 * Stores any additional data that should be returned with the exception.
	 * @var \stdClass
	 * @since phpBitsTheater 3.8.2
	 */
	protected $myExtras = null ;

	/**
	 * Magic PHP method to limit what var_dump() shows.
	 * @return string['var' => 'value'] Returns the debug array info.
	 */
	public function __debugInfo() {
		return array(
				'code' => $this->code,
				'message' => $this->message,
				'myCondition' => $this->myCondition,
				'myExtras' => $this->myExtras,
		);
	}
	
	/** @return string Accessor for the condition token. */
	public function getCondition()
	{ return $this->myCondition ; }
	
	/**
	 * Mutator for the condition token; accessible to toss().
	 * @return $this Returns $this for chaining.
	 */
	protected function setCondition( $aCondition )
	{ $this->myCondition = $aCondition ; return $this ; }
	
	public function getDisplayText()
	{
		$theText = '[' . $this->code . ']: ' . $this->message ;
		if( !empty( $this->myCondition ) )
			$theText .= ' (' . $this->myCondition . ')' ;
		return $theText ;
	}
	
	/**
	 * Accessor for the integer condition code (usually an HTTP status code)
	 * recorded in this object.
	 * @return integer Returns the condition code.
	 */
	public function getConditionCode()
	{ return $this->code ; }
	
	/**
	 * Mutator for the integer condition code (usually an HTTP status code)
	 * recorded in this object. The method uses <code>intval()</code> to force
	 * the input to be an integer.
	 * @param integer $aCode - the condition code.
	 * @return $this Returns $this for chaining.
	 * @since BitsTheater v4.1.0
	 */
	public function setConditionCode( $aCode )
	{ $this->code = intval($aCode) ; return $this ; }
	
	/**
	 * Sets the literal error message text for the exception. This should not be
	 * generally used; text resources (perhaps fetched using
	 * <code>getMessageFromResource()</code>) should be used instead. This
	 * method is applicable when using <code>BrokenLeg</code> or a descendant to
	 * capture and relay error response messages from other downstream APIs.
	 * @param string $aMessage the literal error message to be set
	 * @return $this Returns $this for chaining.
	 * @since BitsTheater v4.1.0
	 */
	public function setMessage( $aMessage='' )
	{ $this->message = $aMessage ; return $this ; }
	
	/**
	 * Get the extra if it has been assigned.
	 * @param string $aKey - the key for the extra.
	 * @return mixed Returns the value of the key.
	 */
	public function getExtra( $aKey )
	{
		$theVal = null;
		if ( !empty($this->myExtras) && !empty($this->myExtras->$aKey) ) {
			$theVal = $this->myExtras->$aKey;
		}
		return $theVal;
	}
	
	/**
	 * Writes an "extra" property into the exception, which will be returned as
	 * part of a <code>data</code> property.
	 * @param string $aKey the data key
	 * @param mixed $aValue the data value
	 * @return $this Returns $this for chaining.
	 * @since phpBitsTheater 3.8.2
	 */
	public function putExtra( $aKey, $aValue )
	{
		if ( empty($this->myExtras) ) $this->myExtras = new \stdClass();
		$this->myExtras->$aKey = $aValue;
		return $this ;
	}
	
	/**
	 * Get the set of extras that have been assigned.
	 * @return \stdClass Returns the set of extras defined.
	 */
	public function getExtras()
	{
		if ( empty($this->myExtras) ) $this->myExtras = new \stdClass();
		return $this->mExtras;
	}
	
	/**
	 * Writes a set of "extra" properties into the exception, which will be
	 * returned as part of a <code>data</code> property.
	 * @param array|object $aDataSet a dictionary of extra data &mdash; either
	 *  as an associative array or an object
	 * @return $this Returns $this for chaining.
	 */
	public function putExtras( $aDataSet )
	{
		if( is_array($aDataSet) || is_object($aDataSet) )
		{ // If we can iterate over its properties, then do so.
			foreach( $aDataSet as $aKey => $aValue )
				$this->putExtra( $aKey, $aValue ) ;
		}
		return $this ;
	}
	
	/**
	 * Used to protect special string values, like URIs, from the URI resolution
	 * algorithm in IDirected::getRes().
	 * @var string
	 * @see BrokenLeg::getMessageFromResource()
	 * @since phpBitsTheater 3.8.2
	 */
	const SLASH_REPLACEMENT = '|SLASH|' ;

	/**
	 * Retrives the resourced message substituting extra data where appropriate.
	 * @param IDirected $aContext some BitsTheater object that can provide context
	 *  for the website, so that text resources can be retrieved; this can be an
	 *  actor, model, or scene, or anything implementing IDirected
	 * @param string $aMessageResource - the resource name.
	 * @param string|array $aResourceData (optional) any additional data that would be
	 *   passed into a variable substitution in the definition of a text
	 *   resource; if non-empty, then the initial '/' separator is inserted
	 *   automatically before being used in getRes().
	 * @return string Retuns the string resource.
	 */
	static public function getMessageFromResource( IDirected $aContext, $aMessageResource, $aResourceData=null )
	{
		$theResource = $aMessageResource;
		if (is_string($aResourceData))
		{
			$theResource .= '/'
					. str_replace('/', self::SLASH_REPLACEMENT, $aResourceData);
		}
		else if (is_array($aResourceData))
		{
			$theResource .= '/' . implode('/', array_map(
					function($value)
					{
						return str_replace('/', self::SLASH_REPLACEMENT, $value) ;
					}, $aResourceData)
			);
		}
		return str_replace( self::SLASH_REPLACEMENT, '/',
					$aContext->getRes( $theResource ) ) ;
	}
	
	/**
	 * Returns the standard error container well as sets the http_response_code.
	 * @param object $aContext (optional) context in which to set the results
	 * @return \stdClass Returns the standard error response for API calls.
	 */
	public function setErrorResponse( $aContext=null )
	{
		$theResults = $this->toResponseObject() ;

		http_response_code( $this->code ) ;

		if ( is_object($aContext) ) {
			/* @var APIResponseInUse $theResponse */
			if ( !($aContext instanceof APIResponseInUse) ) {
				if ( empty($aContext->results) ) {
					$aContext->results = new APIResponseInUse();
				}
				$theResponse = $aContext->results;
			}
			else {
				$theResponse = $aContext;
			}
			if ( !empty($theResponse) ) {
				$theResponse->setError($this);
				if ( !empty($this->myExtras) && empty($theResponse->data) ) {
					$theResponse->data = $this->myExtras;
				}
			}
			else if ( is_object($aContext->results) ) {
				$aContext->results->error = $theResults ;
			}
			else if ( is_array($aContext->results) ) {
				$aContext->results['error'] = $theResults ;
			}
		}
		return $theResults ;
	}
	
	/**
	 * Provides an instance of the exception without requiring pre-defined consts.
	 * As an alternative to the toss() method, this one does not load resources nor
	 * checks for any defined constants; it just uses its parameters as is.
	 * @param string $aCondition a string uniquely identifying the exceptional
	 *  scenario.
	 * @param integer $aCode - the error code associated with the $aCondition; this will
	 * typically be the HTTP Response code to return.
	 * @param string $aMessage - the text of the error message.
	 * @return $this Returns $this for chaining.
	 */
	public static function pratfall( $aCondition, $aCode, $aMessage )
	{
		$theClass = get_called_class() ;
		return (new $theClass($aMessage, $aCode))->setCondition( $aCondition ) ;
	}
	
	/**
	 * Provides an instance of the exception without requiring pre-defined consts.
	 * As an alternative to the pratfall() method, this one will load resources, but
	 * does not require any defined constants... at the expense of more parameters.
	 * @param IDirected $aContext some BitsTheater object that can provide context
	 *  for the website, so that text resources can be retrieved; this can be an
	 *  actor, model, or scene, or anything implementing IDirected
	 * @param string $aCondition a string uniquely identifying the exceptional
	 *  scenario.
	 * @param integer $aCode - the error code associated with the $aCondition; this will
	 * typically be the HTTP Response code to return.
	 * @param string $aMessageResource - the resource name.
	 * @param string|array $aResourceData (optional) any additional data that would be
	 *   passed into a variable substitution in the definition of a text
	 *   resource; if non-empty, then the initial '/' separator is inserted
	 *   automatically before being used in getRes()
	 * @return $this Returns $this for chaining.
	 */
	public static function pratfallRes( IDirected $aContext, $aCondition, $aCode,
			$aMessageResource, $aResourceData=null )
	{
		return static::pratfall( $aCondition, $aCode,
				static::getMessageFromResource($aContext,
						 $aMessageResource, $aResourceData
				)
		);
	}
	
	/**
	 * Forms the object representing this exception, for return in a response.
	 * @return \stdClass Returns an object with "cause" and "message" fields.
	 * @since BitsTheater 3.6
	 */
	public function toResponseObject()
	{
		$theError = new \stdClass() ;
		$theError->cause = $this->myCondition ;
		$theError->message = $this->message ;
		if( ! empty($this->myExtras) )
			$theError->data = $this->myExtras ;
		return $theError ;
	}

	/**
	 * (Override) Returns the representation of this object that is appropriate
	 * for error responses from the API.
	 * @return \stdClass an object with "cause" and "message" fields
	 * @since BitsTheater 3.6
	 */
	public function exportData()
	{ return $this->toResponseObject() ; }

	/**
	 * As toResponseObject(), but serializes that object to a JSON string.
	 * @param string $aEncodeOptions the JSON encoding options
	 * @return string Returns a JSON serialization of the standard response object.
	 * @since BitsTheater 3.6
	 */
	public function toJson( $aEncodeOptions=null )
	{ return json_encode( $this->exportData(), $aEncodeOptions ) ; }

	/**
	 * Add a reason for the exception the UI may display to the user.
	 * @param string $aReason - the reason the exception may have occurred.
	 * @return $this Returns $this for chaining.
	 */
	public function addReasonForUI( $aReason )
	{ return $this->putExtra('reason', $aReason); }
	
	/**
	 * Add an extra message ("\n" will be preserved) to display for the
	 * user to help fix whatever problem just occurred.
	 * @param string $aMsg - the possibly multi-line string message.
	 * @return $this Returns $this for chaining.
	 */
	public function addExtraMsgForUI( $aMsg )
	{
		$theMsgs = $this->getExtra('extra_msg');
		if ( !empty($theMsgs) ) $theMsgs .= "\n";
		$theMsgs .= $aMsg;
		return $this->putExtra('extra_msg', $theMsgs);
	}
	
	/** @return string Return the extra error information contained, if any. */
	public function getExtendedErrMsg()
	{
		$s = $this->getMessage();
		if ( !empty($this->myExtras->reason) ) {
			$s .= "\n" . $this->myExtras->reason;
		}
		if ( !empty($this->myExtras->extra_msg) ) {
			$s .= "\n" . $this->myExtras->extra_msg;
		}
		return $s;
	}
	
} // end BrokenLeg class

} // end namespace BitsTheater
