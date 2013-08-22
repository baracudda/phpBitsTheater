<?php
/*
 * Copyright (C) 2013 Blackmoon Info Tech Services
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

namespace com\blackmoonit;
{//begin namespace

class Arrays {

	private function __construct() {} //do not instantiate

	/**
	 * Circularly shifts an array
	 *
	 * Shifts to right for $steps > 0. Shifts to left for $steps < 0. Keys are
	 * preserved.
	 *
	 * @param array $aArray - array to shift
	 * @param int $aSteps - # of steps to shift array
	 * @return array Resulting array
	 */
	static public function array_shift_circular(array $aArray, $aSteps = 1) {
		if (!is_int($aSteps)) {
			throw new \InvalidArgumentException('steps has to be an (int)');
		}
		$len = count($aArray);
		if ($len === 0 || $aSteps === 0) {
			return $aArray;
		}
		$theBreakIdx = ($aSteps % $len) * -1;
		return array_merge(array_slice($aArray, $theBreakIdx), array_slice($aArray, 0, $theBreakIdx));
	}

}//end class

}//end namespace
