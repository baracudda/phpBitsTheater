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

class FormWidget extends BlockWidget
{
	const URL_ENCODED = 'application/x-www-form-urlencoded' ;
	const MULTIPART = 'multipart/form-data' ;
	const PLAIN_TEXT = 'text/plain' ;
	
	const METHOD_GET = 'GET' ;
	const METHOD_POST = 'POST' ;
	
	protected $myElementType = 'form' ;
	protected $myRedirect = null ;

	/**
	 * Sets the form's encoding type, silently ignoring invalid types. Use one
	 * of the class's constants (URL_ENCODED, MULTIPART, or PLAIN_TEXT) to
	 * set the value.
	 * @param string $aEncoding a MIME type for form encoding
	 * @return \com\blackmoonit\widgetbuilder\FormWidget the updated widget
	 */
	public function setEncoding( $aEncoding=self::PLAIN_TEXT )
	{
		switch( $aEncoding )
		{
			case self::URL_ENCODED:
			case self::MULTIPART:
			case self::PLAIN_TEXT:
				$this->setAttr( 'enctype', $aEncoding ) ;
				break ;
			default: ;
		}
		return $this ;
	}
	
	public function setAction( $aAction )
	{ $this->setAttr( 'action', $aAction ) ; return $this ; }
	
	public function getAction()
	{ return $this->getAttr('action') ; }
	
	public function setName( $aName )
	{ $this->setAttr( 'name', $aName ) ; return $this ; }
	
	/**
	 * Silently substitutes the element's ID for its name if the name is unset.
	 */
	public function getName()
	{
		if( $this->hasAttr('name') )
			return $this->myAttrs['name'] ;
		else return $this->myID ;
	}
	
	/**
	 * Sets the form's HTTP method. Silently ignores invalid values.
	 * @param string $aMethod "GET" or "POST"
	 * @return \com\blackmoonit\widgetbuilder\FormWidget the updated widget
	 */
	public function setMethod( $aMethod )
	{
		$theMethod = strtoupper($aMethod) ;
		switch( $theMethod )
		{
			case self::METHOD_GET:
			case self::METHOD_POST:
				$this->setAttr( 'method', $theMethod ) ;
				break ;
			default: ;
		}
		return $this ;
	}
	
	public function setRedirect( $aTarget )
	{ $this->myRedirect = $aTarget ; return $this ; }
	
	public function getRedirect()
	{ return $this->myRedirect ; }
	
	/**
	 * Overrides the BaseWidget and AlwaysBlock render() functions.
	 */
	public function render( $bIndentFirst=true )
	{
		$theHTML = ( $bIndentFirst ? $this->indent() : '' ) ;
		$theHTML .= $this->indent()
			. $this->renderOpen() . PHP_EOL
			. $this->renderRedirect()       // This is unique to the FormWidget.
			. $this->indent( $this->myIndentLevel + 1 )
			. $this->myContent . PHP_EOL
			. $this->indent()
			. $this->renderClose() . PHP_EOL
			;
		return $theHTML ;
	}
	
	/**
	 * Renders a hidden redirection input, if any.
	 * @return string an HTML input tag with a redirection URL
	 */
	protected function renderRedirect()
	{
		if( isset($this->myRedirect) )
		{
			$theHTML = $this->indent( $this->myIndentLevel + 1 )
				. '<input type="hidden" name="redirect" value="'
				. $this->myRedirect
				. '"/>' . PHP_EOL
				;
			return $theHTML ;
		}
		else return '' ;
	}
}
	
} // end namespace com\blackmoonit\widgetbuilder