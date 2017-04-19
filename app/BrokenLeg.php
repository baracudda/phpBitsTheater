<?php
namespace BitsTheater ;
use BitsTheater\costumes\IDirected;
use com\blackmoonit\exceptions\IDebuggableException;
use BitsTheater\costumes\APIResponse;
use com\blackmoonit\exceptions\DbException;
use PDOException;
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
class BrokenLeg extends \Exception
{
	// Constants for a subset of standard HTTP error codes.
	// https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
	const HTTP_OK = 200 ;
	const HTTP_NO_CONTENT = 204 ;
	const HTTP_MULTISTATUS = 207 ; // In particular, reflects a "partial" success.
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
	const ERR_MISSING_ARGUMENT =     self::HTTP_BAD_REQUEST ;
	const ERR_MISSING_VALUE =        self::HTTP_BAD_REQUEST ;
	const ERR_FILE_NOT_FOUND =       self::HTTP_NOT_FOUND ;
	const ERR_FORBIDDEN =            self::HTTP_FORBIDDEN ;
	const ERR_DEFAULT =              self::HTTP_INTERNAL_SERVER_ERROR ;
	const ERR_DB_EXCEPTION =         self::HTTP_INTERNAL_SERVER_ERROR ;
	const ERR_ENTITY_NOT_FOUND =     self::HTTP_NOT_FOUND ;
	const ERR_NOT_DONE_YET =         self::HTTP_NOT_IMPLEMENTED ;
	const ERR_DB_CONNECTION_FAILED = self::HTTP_SERVICE_UNAVAILABLE ;
	const ERR_NOT_AUTHENTICATED =    self::HTTP_UNAUTHORIZED ;
	const ERR_SERVICE_UNAVAILABLE =  self::HTTP_SERVICE_UNAVAILABLE ;
	const ERR_TOO_MANY_REQUESTS =    self::HTTP_TOO_MANY_REQUESTS ;
	const ERR_DEPRECATED_FUNCTION =  self::HTTP_GONE ;
	
	// General-purpose messages should be defined in the BitsGeneric resource.
	const MSG_MISSING_ARGUMENT = 'generic/errmsg_arg_is_empty' ;
	const MSG_MISSING_VALUE = 'generic/errmsg_var_is_empty' ;
	const MSG_FILE_NOT_FOUND = 'generic/errmsg_file_not_found';
	const MSG_FORBIDDEN = 'generic/msg_permission_denied' ;
	const MSG_DEFAULT = 'generic/errmsg_default' ;
	const MSG_DB_EXCEPTION = 'generic/errmsg_db_exception' ;
	const MSG_ENTITY_NOT_FOUND = 'generic/errmsg_entity_not_found' ;
	const MSG_NOT_DONE_YET = 'generic/errmsg_not_done_yet' ;
	const MSG_DB_CONNECTION_FAILED = 'generic/errmsg_database_not_connected' ;
	const MSG_NOT_AUTHENTICATED = self::MSG_FORBIDDEN ;
	const MSG_SERVICE_UNAVAILABLE = 'generic/errmsg_service_unavailable';
	const MSG_TOO_MANY_REQUESTS = 'generic/errmsg_too_many_requests';
	const MSG_DEPRECATED_FUNCTION = 'generic/errmsg_deprecated' ;

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
	 * @return \BitsTheater\BrokenLeg an instance of the exception class
	 */
	public static function toss( IDirected &$aContext, $aCondition, $aResourceData=null )
	{
		$theClass = get_called_class() ;
		$theCode = self::ERR_DEFAULT ;
		$theCodeID = $theClass . '::ERR_' . $aCondition ;
		if( defined( $theCodeID ) )
			$theCode = constant( $theCodeID ) ;
		$theMessage = self::MSG_DEFAULT ;
		$theMessageID = $theClass . '::MSG_' . $aCondition ;
		if( defined( $theMessageID ) && isset($aContext) )
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
	 * @param Exception $aException - a thrown exception.
	 */
	static public function tossException( IDirected &$aContext, $aException )
	{
		if (ini_get('log_errors') && $aException instanceof IDebuggableException)
		{
			$aContext->getDirector()->errorLog('[1/2] msg: '.
					$aException->getMessage().' context:'.$aException->getContextMsg()
			);
			$aContext->getDirector()->errorLog('[2/2] c_stk: '.
					str_replace( realpath(BITS_ROOT),
							'[%site]', $aException->getTraceAsString()
					)
			);
		}

		if( $aException instanceof BrokenLeg )
			return $aException ;
		else if ($aException instanceof DbException || $aException instanceof PDOException)
		{
			$aDbCode = intval($aException->getCode());
			$theErr = ($aDbCode>2000 && $aDbCode<2030)
					? 'DB_CONNECTION_FAILED'
					: 'DB_EXCEPTION'
					;
			throw static::toss($aContext, $theErr, $aException->getErrorMsg());
		}
		else if(isset($aException->code) && isset($aException->message))
		{
			throw static::pratfall("DEFAULT", $aException->code, $aException->message);
		}
		else
		{
			$o = static::toss( $aContext, 'DEFAULT' ) ;
			$theErrMsg = $aException->getMessage();
			if (!empty($theErrMsg))
				$o->message = $theErrMsg;
			return $o;
		}
	}
	
	/** Stores the original condition code that was passed into toss(). */
	protected $myCondition ;
	
	/** Accessor */
	public function getCondition()
	{ return $this->myCondition ; }
	
	/**
	 * Mutator; accessible to toss()
	 * @return BrokenLeg Returns $this for chaining.
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
	 * @return integer - a condition code
	 */
	public function getConditionCode()
	{ return $this->code ; }

	/**
	 * Retrives the resourced message substituting extra data where appropriate.
	 * @param IDirected $aContext some BitsTheater object that can provide context
	 *  for the website, so that text resources can be retrieved; this can be an
	 *  actor, model, or scene, or anything implementing IDirected
	 * @param string $aMessageResource - the resource name.
	 * @param string|array $aResourceData (optional) any additional data that would be
	 * passed into a variable substitution in the definition of a text
	 * resource; if non-empty, then the initial '/' separator is inserted
	 * automatically before being used in getRes()
	 */
	static public function getMessageFromResource( IDirected &$aContext, $aMessageResource, $aResourceData=null )
	{
		$theResource = $aMessageResource;
		if (is_string($aResourceData))
			$theResource .= '/' . $aResourceData ;
		else if (is_array($aResourceData))
			$theResource .= '/' . implode('/', $aResourceData);
		return $aContext->getRes( $theResource ) ;
	}
	
	/**
	 * Returns the standard error container well as sets the http_response_code.
	 * @param object $aContext (optional) context in which to set the results
	 * @return array Returns the standard error response for API calls.
	 */
	public function setErrorResponse( &$aContext=null )
	{
		$theResults = $this->toResponseObject() ;

		http_response_code( $this->code ) ;

		if( !empty($aContext) && is_object($aContext) )
		{
			if( $aContext instanceof APIResponse )
				$aContext->setError( $this ) ;
			else if( $aContext instanceof Scene )
			{
				if( empty( $aContext->results ) )
					$aContext->results = new APIResponse() ;

				if( $aContext->results instanceof APIResponse )
					$aContext->results->setError( $this ) ;
				else if( is_object( $aContext->results ) )
					$aContext->results->error = $theResults ;
				else if( is_array( $aContext->results ) )
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
	 * @param $aCode - the error code associated with the $aCondition; this will
	 * typically be the HTTP Response code to return.
	 * @param $aMessage - the text of the error message.
	 * @return \BitsTheater\BrokenLeg an instance of the exception class
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
	 * @param $aCode - the error code associated with the $aCondition; this will
	 * typically be the HTTP Response code to return.
	 * @param string $aMessageResource - the resource name.
	 * @param string|array $aResourceData (optional) any additional data that would be
	 * passed into a variable substitution in the definition of a text
	 * resource; if non-empty, then the initial '/' separator is inserted
	 * automatically before being used in getRes()
	 * @return \BitsTheater\BrokenLeg an instance of the exception class
	 */
	public static function pratfallRes( IDirected &$aContext, $aCondition, $aCode,
			$aMessageResource, $aResourceData=null )
	{
		return static::pratfall( $aCondition, $aCode,
				self::getMessageFromResource($aContext,
						 $aMessageResource, $aResourceData
				)
		);
	}
	
	/**
	 * Forms the object representing this exception, for return in a response.
	 * @return \stdClass an object with "cause" and "message" fields
	 * @since BitsTheater 3.6
	 */
	public function toResponseObject()
	{
		$theError = new \stdClass() ;
		$theError->cause = $this->myCondition ;
		$theError->message = $this->message ;
		return $theError ;
	}

	/**
	 * (Override) Returns the representation of this object that is appropriate
	 * for error responses from the API.
	 * @return stdClass an object with "cause" and "message" fields
	 * @since BitsTheater 3.6
	 */
	public function exportData()
	{ return $this->toResponseObject() ; }

	/**
	 * As toResponseObject(), but serializes that object to a JSON string.
	 * @param string $aEncodeOptions the JSON encoding options
	 * @return string a JSON serialization of the standard response object
	 * @since BitsTheater 3.6
	 */
	public function toJson( $aEncodeOptions=null )
	{ return json_encode( $this->exportData(), $aEncodeOptions ) ; }

} // end BrokenLeg class
	
} // end namespace BitsTheater