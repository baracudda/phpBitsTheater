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

namespace BitsTheater\costumes;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
{//namespace begin

class GenericRowData extends BaseCostume {
	private $__bNeedsUpdate = false;
	
	/**
	 * Copies values into self regardless of existing property name.
	 * @param array|object $aThing - array or object to copy from.
	 */
	protected function copyFrom( $aThing )
	{
		foreach ($aThing as $theName => $theValue) {
			$this->{$theName} = $theValue;
		}
	}
	
	/**
	 * Reset the internal counters to detect if a row needs updating.
	 */
	public function startUpdateIfDiffFrom() {
		$this->__bNeedsUpdate = false;
	}
	
	/**
	 * When determining if row data should be updated, check empty and such.
	 * @param array|object $aThing - the object or array to check against.
	 * @param string $aKeyName - the name of the key in the objects to check.
	 */
	public function updateIfDiffFrom($aThing, $aKeyName) {
		$theValue = (is_array($aThing)) ? $aThing[$aKeyName] : $aThing->{$aKeyName};
		if (property_exists($this, $aKeyName)) {
			if ( ( empty($this->{$aKeyName}) != empty($theValue) ) || ( $this->{$aKeyName} != $theValue ) ) {
				$this->{$aKeyName} = $theValue;
				$this->__bNeedsUpdate = true;
			}
		} else {
			$this->{$aKeyName} = $theValue;
			$this->__bNeedsUpdate = true;
		}
	}
	
	/**
	 * Return TRUE if any of the {@link updateIfDiffFrom()} calls changed data.
	 * @return boolean
	 */
	public function isUpdateIfDiffFrom() {
		return $this->__bNeedsUpdate;
	}
	
}//end class
	
}//end namespace
