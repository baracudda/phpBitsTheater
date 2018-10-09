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
{//begin namespace

/**
 * Button tag element builder.
 */
class ButtonWidget extends BlockWidget
{
	const BUTTON_TYPE_BUTTON = 'button';
	const BUTTON_TYPE_RESET = 'reset';
	const BUTTON_TYPE_SUBMIT = 'submit';
	
	protected $myElementType = 'button' ;
	protected $myHTMLClass = 'btn' ;
	
	/**
	 * Override of inherited field $myAttrs provides some default values for
	 * defining a text area widget.
	 */
	protected $myAttrs = array(
			'type' => self::BUTTON_TYPE_BUTTON,
	);
	
	/**
	 * Set the button type to something other than 'button'.
	 * @param string $aButtonType - one of the self::BUTTON_TYPE_* consts.
	 * @return ButtonWidget Returns $this for chaining.
	 */
	public function setButtonType( $aButtonType )
	{
		if (!empty($aButtonType))
			$this->setAttr( 'type', $aButtonType ) ;
		return $this;
	}
	
}//end class

}//end namespace
