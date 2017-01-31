<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
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

namespace com\blackmoonit\widgetbuilder ;
use com\blackmoonit\widgetbuilder\SingletonWidget ;
{//begin namespace

class InputWidget extends SingletonWidget
{
	protected $myElementType = 'input' ;
	protected $myHTMLClass = 'field' ;
	
	const INPUT_TYPE_TEXT = 'text';
	const INPUT_TYPE_EMAIL = 'email';
	const INPUT_TYPE_PASSWORD = 'password';
	const INPUT_TYPE_HIDDEN = 'hidden';
	const INPUT_TYPE_SUBMIT_BUTTON = 'submit';
	
	/**
	 * Override of inherited field $myAttrs provides some default values for
	 * defining a text area widget.
	 */
	protected $myAttrs = array(
			'type' => self::INPUT_TYPE_TEXT,
			//'size' => 20, //20 is default max size if you do not specify any
			'maxlength' => 255,
			//'value' => '',
			//'required' => 'required', //REQUIRED is a boolean attribute which is either present or not, setting it to true/false does not matter.
	) ;

	/**
	 * Factory convenience method for use instead of (new X()) constructor.
	 * @param string $aName - the Name and default ID of the element.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function withName( $aName )
	{
		$thisClassName = get_called_class();
		$o = new $thisClassName( $aName );
		if (!empty($aName))
			$o->setAttr( 'name', $aName ) ;
		return $o;
	}

	/**
	 * Factory convenience method for use instead of (new X()) constructor.
	 * @param string $aName - the Name and default ID of the element.
	 * @param string $aInputType - the type of input to create.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function withNameAndType( $aName, $aInputType )
	{
		$thisClassName = get_called_class();
		$o = new $thisClassName( $aName );
		if (!empty($aName))
			$o->setAttr( 'name', $aName ) ;
		$o->setInputType( $aInputType );
		return $o;
	}
	
	/**
	 * Factory convenience method for creating a text input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function asText( $aName )
	{
		return self::withName( $aName );
	}

	/**
	 * Factory convenience method for creating an email input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function asEmail( $aName )
	{
		return self::withNameAndType( $aName, self::INPUT_TYPE_EMAIL )
				->setSize(30);
	}

	/**
	 * Factory convenience method for creating a password input.
	 * @param string $aName - the Name and default ID of the element.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function asPassword( $aName )
	{
		return self::withNameAndType( $aName, self::INPUT_TYPE_PASSWORD )
				->setSize(60);
	}
	
	/**
	 * Factory convenience method for creating a hidden input.
	 * @param string $aName - the Name and default ID of the element.
	 * @param string $aValue - the Value of the element.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function asHidden( $aName, $aValue )
	{
		return self::withNameAndType( $aName, self::INPUT_TYPE_HIDDEN )
				->setValue($aValue);
	}
	
	/**
	 * Factory convenience method for creating a honeypot input. Used to create
	 * a non-visible input element to trap spam bots into filling it out.
	 * @param string $aName - the Name and default ID of the element.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function asHoneyPot( $aName )
	{
		return self::withName( $aName )->addClass('hidden');
	}
	
	/**
	 * Factory convenience method for creating a standard submit button.
	 * @param string $aName - the Name and default ID of the element.
	 * @param string $aLabel - the label the button will display.
	 * @return InputWidget Returns the new object for chaining.
	 */
	static public function asSubmitButton( $aName, $aLabel )
	{
		return self::withNameAndType( $aName, self::INPUT_TYPE_SUBMIT_BUTTON )
				->setValue($aLabel)->addClass('btn-primary');
	}
	
	/**
	 * Set the input type to something other than 'text'.
	 * @param string $aInputType - one of the self::INPUT_TYPE_* consts.
	 * @return InputWidget Returns the new object for chaining.
	 */
	public function setInputType( $aInputType )
	{
		if (!empty($aInputType))
			$this->setAttr( 'type', $aInputType ) ;
		return $this;
	}

	/**
	 * Set the input value.
	 * @param string $aValue - the value to set.
	 * @return InputWidget Returns the new object for chaining.
	 */
	public function setValue( $aValue )
	{
		$this->setAttr( 'value', $aValue );
		return $this;
	}
	
	/**
	 * Set this input widget as required.
	 * @param string $isReq - (optional) will not set if pass in FALSE.
	 * @return InputWidget Returns the new object for chaining.
	 */
	public function setRequired( $isReq=true )
	{
		if (!empty($isReq))
			$this->setAttr('required', 'required');
		return $this;
	}
	
	/**
	 * Convenience method for setting the size attribute since that is very common.
	 * @param string $aInputSize - the size to set.
	 * @return InputWidget Returns the new object for chaining.
	 */
	public function setSize( $aInputSize )
	{ return $this->setAttr('size', $aInputSize); }
	
	/**
	 * Convenience method for setting the placeholder attribute of an input (ghost text).
	 * @param string $aPlaceholder - the helpful ghost text placed in an empty input.
	 * @return InputWidget Returns the new object for chaining.
	 */
	public function setPlaceholder( $aPlaceholder )
	{ return $this->setAttr('placeholder', $aPlaceholder); }

}//end class

}//end namespace
