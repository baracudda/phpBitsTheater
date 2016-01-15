<?php
namespace BitsTheater ;
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\APIResponse;
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
 */
class BrokenLeg extends \Exception
{
	// The default codes here all roughly correspond to HTTP response codes.
	const ERR_MISSING_ARGUMENT = 400 ;
	const ERR_MISSING_VALUE = 400 ;
	const ERR_FILE_NOT_FOUND = 404;
	const ERR_FORBIDDEN = 403 ;
	const ERR_DEFAULT = 500 ;
	const ERR_DB_EXCEPTION = 500 ;
	const ERR_NOT_DONE_YET = 501 ;
	const ERR_DB_CONNECTION_FAILED = 503 ;
	
	// General-purpose messages should be defined in the BitsGeneric resource.
	const MSG_MISSING_ARGUMENT = 'generic/errmsg_arg_is_empty' ;
	const MSG_MISSING_VALUE = 'generic/errmsg_var_is_empty' ;
	const MSG_FILE_NOT_FOUND = 'generic/errmsg_file_not_found';
	const MSG_FORBIDDEN = 'generic/msg_permission_denied' ;
	const MSG_DEFAULT = 'generic/errmsg_default' ;
	const MSG_DB_EXCEPTION = 'generic/errmsg_db_exception' ;
	const MSG_NOT_DONE_YET = 'generic/errmsg_not_done_yet' ;
	const MSG_DB_CONNECTION_FAILED = 'generic/errmsg_database_not_connected' ;

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
	 * Provides an instance of the exception based on an already thrown exception.
	 * @param IDirected $aContext some BitsTheater object that can provide context
	 *  for the website, so that text resources can be retrieved; this can be an
	 *  actor, model, or scene, or anything implementing IDirected
	 * @param Exception $aException - a thrown exception.
	 */
	static public function tossException( IDirected &$aContext, $aException )
	{
		if ($aException instanceof DbException) {
			throw static::toss($aContext, 'DB_EXCEPTION');
		} else {
			throw static::toss($aContext, 'DEFAULT');
		}		
	}
	
	/** Stores the original condition code that was passed into toss(). */
	protected $myCondition ;
	
	/** Accessor */
	public function getCondition()
	{ return $this->myCondition ; }
	
	/** Mutator; accessible to toss() */
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
	 * @param Scene $v - (optional) Scene in which to set the results.
	 * @return array Returns the standard error response for API calls.
	 */
	public function setErrorResponse($v=null)
	{
		//create the response to be JSON encoded
		$theResults = array(
				'cause' => $this->myCondition,
				'message' => $this->message,
		);
		//set our HTTP response code
		http_response_code($this->code);
		//set our results property if an object was passed in
		if (!empty($v) && is_object($v)) {
			if (empty($v->results)) {
				$v->results = new APIResponse();
			}
			if ($v->results instanceof APIResponse) {
				$v->results->setError($theResults);
			} else {
				//do not do anything
			}
		}
		//return what we created in case no obj was passed in
		return $theResults;
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
	
} // end BrokenLeg class
	
} // end namespace BitsTheater