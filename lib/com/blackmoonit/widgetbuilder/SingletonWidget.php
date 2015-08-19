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
{

/**
 * All other widgets should extend BaseWidget.
 */
class SingletonWidget extends BaseWidget
{
	/** Is the tag a singleton? */
	protected $isSingleton = true ;
	/** Is the element a "block" element? */
	protected $isBlock = false ;

	public function setIsSingleton($b)
	{ return $this ; }

	public function setIsBlock($b)
	{ return $this ; }
	
	public function setContent($aContent)
	{ return $this ; }
	
	public function getContent()
	{ return '' ; }

	public function render( $bIndentFirst=true )
	{
		$theHTML = ( $bIndentFirst ? $this->indent() : '' )
			. $this->renderOpen()
			;
		return $theHTML ;
	}
	
	public function renderInline()
	{ return $this->renderOpen() ; }
	
} // end class BlockWidget

} // end namespace com\blackmoonit\widgetbuilder