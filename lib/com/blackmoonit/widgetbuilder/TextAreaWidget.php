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

namespace com\blackmoonit\widgetbuilder ;
use com\blackmoonit\widgetbuilder\BlockWidget ;
{

class TextAreaWidget extends BlockWidget
{
	const WRAP_HARD = 'hard' ;
	const WRAP_SOFT = 'soft' ;

	protected $myElementType = 'textarea' ;
	protected $myHTMLClass = 'field' ;

	/**
	 * Override of inherited field $myAttrs provides some default values for
	 * defining a text area widget.
	 */
	protected $myAttrs = array(
		//'required' => 'required', //REQUIRED is a boolean attribute which is either present or not, setting it to true/false does not matter.
		'rows' => 3,
		'cols' => 40,
		'wrap' => self::WRAP_SOFT,
	) ;

	/**
	 * @return TextAreaWidget Returns $this for chaining.
	 */
	public function setID( $aID )
	{
		parent::setID($aID) ;
		$this->setAttr( 'name', $this->myID ) ;
		return $this ;
	}

	/**
	 * Shorthand to set the number of rows for the text area.
	 * @return TextAreaWidget Returns $this for chaining.
	 */
	public function setRows( $aRows )
	{ return $this->setAttr( 'rows', $aRows ) ; }

	/**
	 * Shorthand to set the number of columns for the text area.
	 * @return TextAreaWidget Returns $this for chaining.
	 */
	public function setCols( $aCols )
	{ return $this->setAttr( 'cols', $aCols ) ; }

	/**
	 * Shorthand, with validation, to set the text wrap policy for the box.
	 * see http://www.w3.org/TR/html-markup/textarea.html#textarea.attrs.wrap.hard
	 * @return TextAreaWidget Returns $this for chaining.
	 */
	public function setWrap( $aWrap )
	{
		$theWrap = strtolower($aWrap) ;
		switch( $theWrap )
		{
			case self::WRAP_HARD:
			case self::WRAP_SOFT:
				$this->setAttr( 'wrap', $theWrap ) ;
				break ;
			default: ; // No-op for invalid values.
		}
		return $this ;
	}
	
	/**
	 * Set this input widget as required.
	 * @param string $isReq - (optional) will not set if pass in FALSE.
	 * @return TextAreaWidget Returns $this for chaining.
	 */
	public function setRequired( $isReq=true )
	{
		if (!empty($isReq))
			$this->setAttr('required', 'required');
		return $this;
	}
	
	/**
	 * Convenience method for setting the placeholder attribute of an input (ghost text).
	 * @param string $aPlaceholder - the helpful ghost text placed in an empty input.
	 * @return TextAreaWidget Returns $this for chaining.
	 */
	public function setPlaceholder( $aPlaceholder )
	{ return $this->setAttr('placeholder', $aPlaceholder); }

} // end class TextAreaWidget

} // end namespace com\blackmoonit\widgetbuilder
