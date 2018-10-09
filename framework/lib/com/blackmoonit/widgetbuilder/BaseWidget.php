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
use com\blackmoonit\Strings ;
use com\blackmoonit\Widgets ;
{

/**
 * All other widgets should extend BaseWidget.
 */
class BaseWidget
{
	/** HTML element name. */
	protected $myElementType = 'foo' ;
	/** Element's ID. Treated specially in rendering. */
	protected $myID = null ;
	/** Element's class. Treated specially in rendering. */
	protected $myHTMLClass = null ;
	/** All other HTML attributes. */
	protected $myAttrs = array() ;
	/** Content of a non-singleton widget, if it's simple. */
	protected $myContent = null ;
	/** Number of indents to apply when rendering as a block. */
	protected $myIndentLevel = 0 ;
	/** String/chars to use when indenting a block. */
	protected $myIndentString = ' ' ;
	/** Is the tag a singleton? */
	protected $isSingleton = false ;
	/** Is the element a "block" element? */
	protected $isBlock = false ;

	public function __construct( $aID=null )
	{
		$this->setID($aID) ;
	}
	
	/**
	 * Factory convenience method for use instead of (new X()) constructor.
	 * @param string $aID - the ID of the element.
	 * @return BaseWidget Returns the new object for chaining.
	 */
	static public function newWidget( $aID=null )
	{
		$thisClassName = get_called_class();
		$o = new $thisClassName( $aID );
		return $o;
	}

	/**
	 * Sets the element's type. Silently ignores blank values.
	 * @param string $aType an HTML element type
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function setType( $aType )
	{
		if( !empty($aType) ) $this->myElementType = (string)$aType ;
		return $this ;
	}

	/** Gets the HTML element type. */
	public function getType()
	{ return $this->myElementType ; }

	/**
	 * Sets the element's ID. This is santitized with
	 * Widgets::sanitizeElementID().
	 * @param string $aID an HTML element ID
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function setID( $aID )
	{ $this->myID = Widgets::sanitizeElementID($aID) ; return $this ; }

	/** Returns the widget's HTML element ID. */
	public function getID()
	{ return $this->myID ; }

	/**
	 * Adds an HTML class to the widget's list of classes.
	 * @param string $aClass an HTML class spec
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function addClass( $aClass )
	{
		if( empty($aClass) ) return $this ;

		if( empty( $this->myHTMLClass ) )
			$this->myHTMLClass = $aClass ;
		else
			$this->myHTMLClass .= ' ' . $aClass ;

		return $this ;
	}

	/**
	 * Adds an array of HTML classes to the widget's list of classes.
	 * @param array $aClasses an array of HTML class specs
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function addClasses( array $aClasses )
	{
		foreach( $aClasses as $theClass )
			$this->addClass($theClass) ;
		return $this ;
	}

	/**
	 * Removes an HTML class from the widget's list of classes.
	 * @param string $aClass an HTML class spec
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function removeClass( $aClass=null )
	{
		if( empty($aClass) )
			$this->myHTMLClass = null ;
		else
			$this->myHTMLClass = str_replace( $aClass, '', $this->myHTMLClass );

		return $this ;
	}

	/**
	 * Removes an array of HTML classes from the widget's list of classes.
	 * @param array $aClasses a list of HTML class specs
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function removeClasses( array $aClasses )
	{
		foreach( $aClasses as $theClass )
			$this->removeClass($theClass) ;
		return $this ;
	}

	/**
	 * Sets an HTML attribute in the widget. Will handle "id" and "class"
	 * specially by setting those properties instead.
	 * @param string $aName attribute name
	 * @param string $aValue attribute value
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function setAttr( $aName, $aValue )
	{
		switch( strtolower($aName) )
		{
			case 'id':
				$this->setID($aValue) ;
				break ;
			case 'class':
				$this->myHTMLClass = (string)$aValue ;
				break ;
			default: $this->myAttrs[$aName] = (string)$aValue ; break ;
		}
		return $this ;
	}

	/**
	 * Sets an array of HTML attributes into the widget.
	 * @param array $aAttrs attributes ( name => value )
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function setAttrs( array $aAttrs )
	{
		foreach( $aAttrs as $theName => $theValue )
			$this->setAttr( $theName, $theValue ) ;
		return $this ;
	}

	/**
	 * Accesses an HTML attribute value.
	 * @param string $aName attribute name
	 * @return string value of the attribute, or null if not found
	 */
	public function getAttr( $aName )
	{
		switch( strtolower($aName) )
		{
			case 'id': return $this->myID ;
			case 'class': return $this->myHTMLClass ;
			default:
				if( array_key_exists( $aName, $this->myAttrs ) )
					return $this->myAttrs[$aName] ;
				else return null ;
		}
	}

	/**
	 * Tests whether a value has been set for an attribute.
	 * @param string $aName attribute name
	 * @return boolean true if a value has been set
	 */
	public function hasAttr( $aName )
	{
		switch( strtolower($aName) )
		{
			case 'id': return !empty($this->myID) ;
			case 'class': return !empty($this->myHTMLClass) ;
			default: return array_key_exists( $aName, $this->myAttrs ) ;
		}
	}

	public function setContent( $aContent=null )
	{ $this->myContent = (string)$aContent ; return $this ; }

	public function getContent()
	{ return $this->myContent ; }

	public function setIndentLevel( $aLevel )
	{ $this->myIndentLevel = (int)$aLevel ; return $this ; }

	public function getIndentLevel()
	{ return $this->myIndentLevel ; }

	public function setIndentString( $aString )
	{ $this->myIndentString = (string)$aString ; return $this ; }

	public function getIndentString()
	{ return $this->myIndentString ; }

	/**
	 * Sets whether element is a singleton. If true, isBlock() becomes
	 * false.
	 * @param boolean $b new value
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function setIsSingleton( $b=true )
	{
		$this->isSingleton = (boolean)$b ;
		if( $b ) $this->isBlock = false ;
		return $this ;
	}

	public function isSingleton()
	{ return $this->isSingleton ; }

	/**
	 * Sets whether element is a block. If true, isSingleton() becomes
	 * false.
	 * @param boolean $b new value
	 * @return \com\blackmoonit\widgetbuilder\BaseWidget the updated widget
	 */
	public function setIsBlock( $b=true )
	{
		$this->isBlock = (boolean)$b ;
		if( $b ) $this->isSingleton = false ;
		return $this ;
	}

	public function isBlock()
	{ return $this->isBlock ; }

	/**
	 * Renders the widget as a string. If the element is a block element,
	 * then the content will be indented one more level between the opening
	 * and closing tags.
	 * @param boolean $bIndentFirst whether to apply the indent before the
	 *  opening tag
	 * @return string the HTML code for the widget
	 */
	public function render( $bIndentFirst=true )
	{
		$theHTML = ( $bIndentFirst ? $this->indent() : '' ) ;
		if( $this->isBlock )
		{
			$theHTML .= $this->renderOpen() . PHP_EOL
				. $this->indent( $this->myIndentLevel + 1 )
				. $this->myContent . PHP_EOL
				. $this->indent()
				. $this->renderClose() . PHP_EOL
				;
		}
		else if( $this->isSingleton )
			$theHTML .= $this->renderOpen() ;
		else
		{
			$theHTML .= $this->renderOpen()
				. $this->myContent
				. $this->renderClose()
				;
		}
		return $theHTML ;
	}

	/**
	 * Renders the widget without applying any indents or newlines.
	 * @return string the HTML code for the widget
	 */
	public function renderInline()
	{
		$theHTML = $this->renderOpen() ;
		if( ! $this->isSingleton )
			$theHTML .= $this->myContent . $this->renderClose() ;
		return $theHTML ;
	}

	/**
	 * Renders only the opening tag of the widget.
	 * @return string the HTML code for the widget's opening tag.
	 */
	public function renderOpen()
	{
		$myElementID = $this->getID() ;        // Sometimes this can be special.

		$theHTML = '<'
				. $this->myElementType
				. ( !empty($myElementID) ?
						' id="' . $myElementID . '"' : '' )
				. ( isset($this->myHTMLClass) ?
						' class="' . $this->myHTMLClass . '"' : '' )
				;
		foreach( $this->myAttrs as $theAttrName => $theAttrValue )
			$theHTML .= ' ' . $theAttrName . '="' . $theAttrValue . '"' ;
		if( $this->isSingleton )
			$theHTML .= '/>' ;
		else
			$theHTML .= '>' ;
		return $theHTML ;
	}

	/**
	 * Renders only the closing tag of the widget. If a singleton, returns
	 * an empty string.
	 * @return string the HTML code for the widget's closing tag
	 */
	public function renderClose()
	{
		if( $this->isSingleton ) return '' ;
		else return '</' . $this->myElementType . '>' ;
	}

	/**
	 * Indents a line of the widget's HTML code.
	 * @param integer $aLevel an indent level; default is the widget's set level
	 */
	protected function indent( $aLevel=null )
	{
		if( isset($aLevel) )
			return Strings::repeat( $this->myIndentString, $aLevel ) ;
		else
			return Strings::repeat( $this->myIndentString, $this->myIndentLevel );
	}

	/**
	 * In case you try to print a widget to the output stream because you forgot
	 * to invoke render() or renderInline() instead...
	 * @return string Returns the rendered HTML code.
	 */
	public function __toString()
	{ return $this->render() ; }
	
	/**
	 * HTML5 introduces the "data-X" attribute so custom attrs can be used and
	 * their namespace is protected so that HTML standards will never conflict.
	 * @param string $aDataName - the X part of the "data-X" attribute name.
	 * @param string $aDataValue - the "data-X" value to HTML-encode.
	 * @return $this Returns $this for chaining.
	 */
	public function setDataAttr($aDataName, $aDataValue)
	{
		return $this->setAttr( 'data-' . Widgets::sanitizeAttributeName($aDataName),
				htmlentities($aDataValue)
		);
	}
	
} // end class

} // end namespace com\blackmoonit\widgetbuilder
