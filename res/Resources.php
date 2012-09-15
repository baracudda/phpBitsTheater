<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace com\blackmoonit\bits_theater\res;
use com\blackmoonit\AdamEve as BaseResources;
use com\blackmoonit\Strings;
use com\blackmoonit\bits_theater\app\IllegalArgumentException;
{//begin namespace

class Resources extends BaseResources {
	const _SetupArgCount = 1; //number of args required to call the setup() method.
	protected $_director = null;
	
	public function setup($aDirector) {
		parent::setup();
		if (empty($aDirector))
			throw new IllegalArgumentException('Director is null.');
		$this->_director = $aDirector;
	}

	/**
	 * Descendants want to merge translated labels with their static definitions in ancestor.
	 */
	public function res_array_merge(array &$res1, &$res2) {
		if (!is_array($res1) || empty($res2)) {
			throw new IllegalArgumentException('res_array_merge requires first param to be an array and second != null. '.
					Strings::debugStr($res1));
		}
		if (!is_array($res2)) {
			$res1[] = $res2;
		} else {
			foreach ($res2 as $key => &$value) {
				if (is_string($key)) {
					if (is_array($value) && array_key_exists($key, $res1) && is_array($res1[$key])) {
						$this->res_array_merge($res1[$key],$value);
					} else {
						$res1[$key] = $value;
					}
				} else {
					$res1[] = $value;
				}
			}
		}
	}
	
	public function getRes($aResName) {
		return $this->_director->getRes($aResName);
	}
	
}//end class

}//end namespace
