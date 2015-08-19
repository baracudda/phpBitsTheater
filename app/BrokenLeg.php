<?php
namespace BitsTheater ;
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
	const ERR_MISSING_ARGUMENT = -400 ;
	const ERR_MISSING_VALUE = -400 ;
	const ERR_FORBIDDEN = -403 ;
	const ERR_DEFAULT = -500 ;
	const ERR_DB_EXCEPTION = -500 ;
	const ERR_NOT_DONE_YET = -501 ;
	const ERR_DB_CONNECTION_FAILED = -503 ;
	
	// General-purpose messages should be defined in the BitsGeneric resource.
	const MSG_MISSING_ARGUMENT = 'generic/errmsg_arg_is_empty' ;
	const MSG_MISSING_VALUE = 'generic/errmsg_var_is_empty' ;
	const MSG_FORBIDDEN = 'generic/msg_permission_denied' ;
	const MSG_DEFAULT = 'generic/errmsg_default' ;
	const MSG_DB_EXCEPTION = 'generic/errmsg_db_exception' ;
	const MSG_NOT_DONE_YET = 'generic/errmsg_not_done_yet' ;
	const MSG_DB_CONNECTION_FAILED = 'generic/errmsg_database_not_connected' ;

	/**
	 * Provides an instance of the exception.
	 * @param object $aContext some BitsTheater object that can provide context
	 *  for the website, so that text resources can be retrieved; this can be an
	 *  actor, model, or scene
	 * @param string $aCondition a string uniquely identifying the exceptional
	 *  scenario; this must correspond to one of the constants defined within
	 *  the descendant class
	 * @param string $aResourceData (optional) any additional data that would be
	 *  passed into a variable substitution in the definition of a text
	 *  resource; if non-empty, then the initial '/' separator is inserted
	 *  automatically before being used in getRes()
	 * @return \BitsTheater\BrokenLeg an instance of the exception class
	 */
	public static function toss( &$aContext, $aCondition, $aResourceData=null )
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
			$theResource = constant($theMessageID) ;
			if( !empty($aResourceData) )
				$theResource .= '/' . $aResourceData ;
			$theMessage = $aContext->getRes( $theResource ) ;
		}
		$theException = (new $theClass($theMessage,$theCode))
				->setCondition( $aCondition ) ;
		return $theException ;
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
	
} // end BrokenLeg class
	
} // end namespace BitsTheater