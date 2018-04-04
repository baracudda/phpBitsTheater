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
use BitsTheater\costumes\CursorCloset\ARecordList as BaseCostume;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a list of auth groups.
 *
 * <pre>
 * $theSet = AuthGroupList::create($this)
 *     ->setListOfIds($theListToUse)
 *     ;
 * </pre>
 */
class AuthGroupList extends BaseCostume
{
	/**
	 * NOTE: since group_id is an INTEGER, we need to PRESERVE KEYS
	 */
	const ID_IS_NUMERIC = true;

	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return string
	 */
	protected function getModelClassToUse() {
		return 'AuthGroups';
	}
	
	/**
	 * Name of the ID field to use.
	 * @return string
	 */
	protected function getIdFieldName()
	{ return 'group_id'; }
	
	/**
	 * Name of the table where to get the record from.
	 * @return string
	 */
	protected function getIdTableName()
	{ return $this->getModel()->tnGroups; }
	
}//end class

}//end namespace
