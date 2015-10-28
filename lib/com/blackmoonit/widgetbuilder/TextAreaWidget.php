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
		'required' => 'false',
		'rows' => 3,
		'cols' => 40,
		'wrap' => self::WRAP_SOFT,
	) ;

	public function setID( $aID )
	{
		parent::setID($aID) ;
		$this->setAttr( 'name', $this->myID ) ;
		return $this ;
	}

	/** Shorthand to set the number of rows for the text area. */
	public function setRows( $aRows )
	{ return $this->setAttr( 'rows', $aRows ) ; }

	/** Shorthand to set the number of columns for the text area. */
	public function setCols( $aCols )
	{ return $this->setAttr( 'cols', $aCols ) ; }

	/**
	 * Shorthand, with validation, to set the text wrap policy for the box.
	 * see http://www.w3.org/TR/html-markup/textarea.html#textarea.attrs.wrap.hard
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
} // end class TextAreaWidget

} // end namespace com\blackmoonit\widgetbuilder