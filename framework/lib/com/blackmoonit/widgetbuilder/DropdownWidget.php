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
use com\blackmoonit\Widgets ;
use com\blackmoonit\widgetbuilder\BlockWidget ;
{

class DropdownWidget extends BlockWidget
{

	protected $myElementType = 'select' ;
	protected $myOptionData = null ;
	protected $myOptionValueField = null ;
	protected $myOptionDisplayField = null ;
	protected $mySelectedValue = null ;
	protected $bAddBlankTopOption = false ;

	/**
	 * Specifies the name of a field in the options array which should be used
	 * as the "value" of a selectable option. If this value remains null when
	 * the widget is rendered, then the index of each option in the array will
	 * be used as the value.
	 * @param string $aValueField the name of an associative field in the data
	 *  array, whose value should be used as the value of each option
	 * @return \com\blackmoonit\widgetbuilder\DropdownWidget this widget
	 */
	public function setOptionValueField( $aValueField=null )
	{
		$this->myOptionValueField = $aValueField ;
		return $this ;
	}

	/**
	 * Specifies the name of a field in the options array which should be used
	 * as the display text for a selectable option. If this value remains null
	 * when the widget is rendered, then the widget will attempt additional
	 * magic to discover a suitable display text value.
	 * @param string $aDisplayField the name of an associative field in the data
	 *  array, whose value should be used as the display text for each option
	 * @return \com\blackmoonit\widgetbuilder\DropdownWidget this widget
	 */
	public function setOptionDisplayField( $aDisplayField=null )
	{
		$this->myOptionDisplayField = $aDisplayField ;
		return $this ;
	}

	/**
	 * Sets the options for the dropdown list and reconstructs the block
	 * element's content behind the scenes.
	 * @param array $aOptionArray the data to be rendered as selectable options
	 * @return \com\blackmoonit\widgetbuilder\DropdownWidget this widget
	 */
	public function setOptions( $aOptionArray )
	{
		$this->myOptionData = $aOptionArray ;
		return $this ;
	}

	public function setSelectedValue( $aValue )
	{
		$this->mySelectedValue = $aValue ;
		return $this ;
	}

	public function setAddBlankTopOption( $b )
	{
		if( $b ) $this->bAddBlankTopOption = true ;
		else $this->bAddBlankTopOption = false ;
		return $this ;
	}

	/**
	 * Regenerates the element's content based on its other properties.
	 * @return \com\blackmoonit\widgetbuilder\DropdownWidget this widget
	 */
	public function regenerate()
	{
		$this->myContent = '' ;

		if( empty($this->myOptionData) || ! is_array($this->myOptionData) )
			return $this ;

		if( $this->bAddBlankTopOption )
			$this->append( '<option value=""></option>' ) ;

		$theOptions = Widgets::prepareArrayForSelect( $this->myOptionData,
				$this->myOptionValueField, $this->myOptionDisplayField ) ;

		foreach( $theOptions as $theValue => $theCaption )
		{
			$theOption = '<option value="' . $theValue . '"'
					. $this->renderSelectedProperty($theValue)
					. '>' . $theCaption . '</option>'
					;
			$this->append( $theOption ) ;
		}
		return $this ;
	}

	protected function renderSelectedProperty( $aValue )
	{
		if( !empty($this->mySelectedValue) && $this->mySelectedValue==$aValue )
			return ' selected' ;
		else
			return '' ;
	}
} // end class DropdownWidget

} // end namespace com\blackmoonit\widgetbuilder