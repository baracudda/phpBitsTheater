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

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\CursorCloset\ARecordSet as BaseCostume;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of auth groups.
 *
 * <pre>
 * $theSet = AuthGroupSet::create($this->getDirector())
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 */
class AuthGroupSet extends BaseCostume
{
	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = 'AuthGroup';

	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return class|string
	 * @see Director::getProp()
	 */
	protected function getModelClassToUse() {
		return 'AuthGroups';
	}
	
	/**
	 * Return the property name the JSON export should use for the array of records.
	 * @return string "records" is used unless overridden by a descendant.
	 */
	protected function getJsonPropertyName() {
		return 'authgroups';
	}

}//end class

}//end namespace
