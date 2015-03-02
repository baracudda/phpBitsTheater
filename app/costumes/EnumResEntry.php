<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
{//namespace begin

/**
 * Many enum resources have UI label and descriptive elements.
 * Helper class for Resource-based classes.
 */
class EnumResEntry extends BaseCostume {
	public $value = null;
	public $label = null;
	public $desc = null;
	
	public function __construct($aEnumValue=null, $aLabel=null, $aDesc=null) {
		$this->value = $aEnumValue;
		$this->label = $aLabel;
		$this->desc = $aDesc;
	}
	
}//end class

}//end namespace
