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
use com\blackmoonit\widgetbuilder\BaseWidget ;
use com\blackmoonit\Strings ;
{

/**
 * All other widgets should extend BaseWidget.
 */
class BlockWidget extends BaseWidget
{
	/** Is the tag a singleton? */
	protected $isSingleton = false ;
	/** Is the element a "block" element? */
	protected $isBlock = true ;

	protected $myAppendIndents = 1 ;
	protected $myLastAppendEndedALine = false ;
	
	public function setIsSingleton($b)
	{ return $this ; }
	
	public function setIsBlock($b)
	{ return $this ; }
	
	/**
	 * Appends a string directly to the block widget's content.
	 * @param string $aString the string to append
	 * @param boolean $bReturnAndAppend whether to also append a newline 
	 * @return \com\blackmoonit\widgetbuilder\BlockWidget the updated widget
	 */
	public function append( $aString, $bReturnAndAppend=true )
	{
		if( $this->myLastAppendEndedALine )
			$this->myContent .= $this->indentForAppend() ;
		$this->myContent .= $aString ;
		if( $bReturnAndAppend )
		{
			$this->myContent .= PHP_EOL ;
			$this->myLastAppendEndedALine = true ;
		}
		else
			$this->myLastAppendEndedALine = false ;
		return $this ;
	}
	
	/**
	 * Applies the internal indent.
	 */
	protected function indentForAppend()
	{ return Strings::repeat( $this->myIndentString, $this->myAppendIndents ); }
	
	/**
	 * Increases the internal indent counter.
	 * @param number $aCount the number of indent levels by which to increase
	 * @return \com\blackmoonit\widgetbuilder\BlockWidget the updated widget
	 */
	public function increase( $aCount=1 )
	{ $this->myAppendIndents += $aCount ; return $this ; }

	/**
	 * Decreases the internal indent counter.
	 * @param number $aCount the number of indent levels by which to decrease
	 * @return \com\blackmoonit\widgetbuilder\BlockWidget the updated widget
	 */
	public function decrease( $aCount=1 )
	{
		if( $this->myAppendIndents - $aCount < 0 )
			$this->myAppendIndents = 0 ;
		else
			$this->myAppendIndents -= $aCount ;
		return $this ;
	}
	
	public function render( $bIndentFirst=true )
	{
		$theHTML = ( $bIndentFirst ? $this->indent() : '' )
			. $this->renderOpen() . PHP_EOL
			. $this->indent( $this->myIndentLevel + 1 )
			. $this->myContent . PHP_EOL
			. $this->indent()
			. $this->renderClose() . PHP_EOL
			;
		return $theHTML ;
	}
	
	public function renderInline()
	{ return $this->renderOpen() . $this->myContent . $this->renderClose() ; }
	
} // end class BlockWidget

} // end namespace com\blackmoonit\widgetbuilder