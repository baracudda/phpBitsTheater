<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe ;
use \com\blackmoonit\Strings ;
use BitsTheater\costumes\EnumResEntry ;
{

/**
 * Similar to <code>ConfigResEntry</code>, this class encapsulates the
 * specification of an account preference.
 * @since BitsTheater [NEXT]
 */
class AccountPrefSpec extends EnumResEntry
{
	/**
	 * Denotes a preference whose value is Boolean (true/false).
	 * @var string
	 */
	const TYPE_BOOLEAN = 'boolean' ;
	/**
	 * Denotes a preference whose value is an integer.
	 * @var string
	 */
	const TYPE_INTEGER = 'integer' ;
	/**
	 * Denotes a prefernce whose value is a simple string.
	 * @var string
	 */
	const TYPE_STRING = 'string' ;
	/**
	 * Denotes a preference whose value is a long (possibly formatted) string.
	 * @var string
	 */
	const TYPE_TEXT = 'text' ;
	/**
	 * Denotes a preference whose value is a string which should not be
	 * displayed when entered (like a password).
	 * @var string
	 */
	const TYPE_SECRET = 'secret' ;
	/**
	 * Denotes a preference whose value must be chosen from a list of options
	 * (as in a dropdown box).
	 * @var string
	 */
	const TYPE_OPTION_LIST = 'options' ;
	/**
	 * Denotes a virtual preference which has no actual value; instead, the
	 * user agent should render this item as an action button.
	 * @var string
	 */
	const TYPE_ACTION = 'action' ;
	
	/**
	 * Coerces a value to be strictly Boolean.
	 * Shamelessly stolen from
	 * <a href="http://php.net/manual/en/function.boolval.php#116547">some PHP
	 * documentation comments</a>.
	 * @param mixed $aValue some value
	 * @return boolean a Boolean coercion of the value
	 */
	public static function toBooleanValue( $aValue )
	{
		$theValue = ( is_string($aValue) ?
				filter_var( $aValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) :
				boolval($aValue)
			);
		return( $theValue === null ? false : $theValue ) ;
	}
	
	/**
	 * Coerces a value to be strictly a string.
	 * @param mixed $aValue some value
	 * @return string a string coercion of the value
	 */
	public static function toStringValue( $aValue )
	{
		if( is_string($aValue) ) return $aValue ; // trivially
		
		if( is_bool($aValue) ) return( $aValue ? 'true' : 'false' ) ;
		
		if( is_array($aValue) || is_object($aValue) )
			return json_encode($aValue) ;
			
		return strval($aValue) ;
	}
	
	/**
	 * Provides a way to ensure that a given value is coerced into the type that
	 * is appropriate for a specific preference definition
	 * @param AccountPrefSpec $aSpec the definition of the preference
	 * @param mixed $aValue a value which was obtained for this preference
	 * @return boolean|integer|string the value, in the appropriate type
	 */
	public static function coerceValue( AccountPrefSpec $aSpec, $aValue )
	{ return static::coerceValueToType( $aValue, $aSpec->input_type ) ; }
	
	/**
	 * Provides a way to ensure that a given value is coerced into the
	 * specified type.
	 *
	 * Consumed by <code>coerceValue()</code>, which passes the spec object's
	 * type to this method.
	 *
	 * @param mixed $aValue a value which was obtained for this preference
	 * @param string $aType a type specifier from this class
	 * @return boolean|number|string the value, in the appropriate type
	 */
	public static function coerceValueToType( $aValue, $aType )
	{
		switch( $aType )
		{
			case static::TYPE_BOOLEAN:
				return self::toBooleanValue($aValue) ;
			case static::TYPE_INTEGER:
				return intval($aValue) ;
			default:
				return self::toStringValue($aValue) ;
		}
	}
	
	/**
	 * The preference's namespace.
	 * @var string
	 */
	public $namespace = null ;
	/**
	 * Indicates whether the preference should be displayed as editable in a
	 * user agent. (default:true)
	 * @var boolean
	 */
	public $is_editable = true ;
	/**
	 * Indicates the expected data type of the preference's value.
	 * @var string
	 */
	public $input_type = self::TYPE_STRING ;
	/**
	 * If type is <code>TYPE_OPTION_LIST</code>, then this is a dictionary of
	 * options.
	 * @var array
	 */
	public $input_options = null ;
	/**
	 * Specifies the default value for the preference, if none is provisioned
	 * for the account.
	 * @var boolean|integer|string
	 */
	public $default_value = null ;
	
	public function __construct( $aSpace, $aKey, $aLabel=null, $aDesc=null )
	{
		parent::__construct( $aKey, $aLabel, $aDesc ) ;
		$this->namespace = $aSpace ;
	}
	
	/**
	 * For preferences whose value is chosen from a list, this defines that
	 * list.
	 * @param mixed $aSpec the specification of options
	 * @return \BitsTheater\costumes\Wardrobe\AccountPrefSpec $this
	 */
	public function setInputOptions( $aSpec=null )
	{
		if( empty($aSpec) ) { $this->input_options = null ; return $this ; }
		
		$this->input_options = array() ;
		foreach( $aSpec as $key => $value )
		{
			if( $value instanceof EnumResEntry )
				$this->input_options[$key] = $value ;
			else if( is_array($value) )
			{ // Try to parse pieces of the array into our choice list.
				if( array_key_exists( 'label', $value ) && array_key_exists( 'desc', $value ) )
				{ // the value is itself a map with fields 'label' and 'desc'
					$this->input_options[$key] =
							new EnumResEntry( $key, $value['label'],
									( isset($value['desc']) ?
											$value['desc'] : null )
									);
				}
			}
			else
				$this->input_options[$key] = new EnumResEntry( $key, $value ) ;
		}
		
		return $this ;
	}
	
	/**
	 * Sets up the spec given some similar-looking spec data.
	 * @param mixed $aSpecData some sort of specification data
	 * @return \BitsTheater\costumes\Wardrobe\AccountPrefSpec $this
	 */
	public function setFromSpec( $aSpecData )
	{
		if( is_string($aSpecData) )
		{ // Interpret it as an input type; leave most other attributes alone.
			$this->setType($aSpecData) ;
			return $this ;
		}
		
		$theSpecData = null ;
		if( is_object($aSpecData) ) $theSpecData = $aSpecData ;
		else if( is_array($aSpecData) ) $theSpecData = ((object)($aSpecData)) ;
		else
		{
			Strings::errorLog( __METHOD__ . ' cannot resolve the given inputs.' ) ;
			return $this ; // in shame
		}
		
		// Now try to resolve what's contained in the spec data.
		if( property_exists( $theSpecData, 'type' ) )
			$this->setType( $theSpecData->type ) ;
		
		foreach( array( 'default_value', 'default' ) as $theDefaultKey )
		{
			if( property_exists( $theSpecData, $theDefaultKey ) )
			{
				$this->default_value = $theSpecData->$theDefaultKey ;
				break ; // the foreach
			}
		}
		
		if( property_exists( $theSpecData, 'is_editable' ) )
			$this->is_editable = static::toBooleanValue( $theSpecData->is_editable ) ;
		
		if( $this->input_type == static::TYPE_OPTION_LIST )
		{
			foreach( array( 'input_options', 'options', 'enums', 'values' ) as $theOptionsKey )
			{
				if( property_exists( $theSpecData, $theOptionsKey ) )
				{
					$this->setInputOptions( $theSpecData->$theOptionsKey ) ;
					break ; // the foreach
				}
			}
		}
		
		return $this ;
	}
	
	/**
	 * Sanely sets the spec's input type and also adjusts the default value if
	 * successful.
	 *
	 * Refuses to perform the update if the string doesn't match one of the
	 * defined <code>TYPE_</code> constants.
	 *
	 * @param string $aType the new type
	 * @return \BitsTheater\costumes\Wardrobe\AccountPrefSpec $this
	 */
	public function setType( $aType )
	{
		switch( $aType )
		{
			case static::TYPE_BOOLEAN:
				$this->input_options = null ;
				$this->default_value = static::toBooleanValue($this->default_value) ;
				break ;
			case static::TYPE_INTEGER:
				$this->input_options = null ;
				$this->default_value = intval($this->default_value) ;
				break ;
			case static::TYPE_STRING:
			case static::TYPE_TEXT:
			case static::TYPE_SECRET:
			case static::TYPE_ACTION:
				$this->input_options = null ;
				$this->default_value = static::toStringValue($this->default_value) ;
				break ;
			case static::TYPE_OPTION_LIST:
				$this->input_options = array() ;
				$this->default_value = static::toStringValue($this->default_value) ;
				break ;
			default:
				Strings::errorLog( __METHOD__ . ' rejected an invalid type ['
						. $aType . '].' ) ;
				return $this ; // without changing our type
		}
		$this->input_type = $aType ;
		return $this ;
	}
	
	/**
	 * Gets the preference's current value, while ensuring that it is returned
	 * as the data type that we would expect, given the preference's type.
	 * @return boolean|number|string the preference's value, coerced as
	 *  appropriate
	 */
	public function getValue()
	{ return self::coerceValue( $this, $this->value ) ; }
}

}