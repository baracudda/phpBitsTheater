<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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
use BitsTheater\models\Auth as MyModel;
use BitsTheater\costumes\AuthOrg as MyRecord;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a list of auth orgs.
 *
 * <pre>
 * $theSet = AuthOrgList::create($this)
 *     ->setListOfIds($theListToUse)
 *     ;
 * </pre>
 */
class AuthOrgList extends BaseCostume
{
	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = MyRecord::ITEM_CLASS;
	
	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return string
	 */
	protected function getModelClassToUse()
	{ return MyModel::MODEL_NAME; }
	
	/**
	 * Name of the ID field to use.
	 * @return string
	 */
	protected function getIdFieldName()
	{ return 'org_id'; }

	/**
	 * Name of the table where to get the record from.
	 * @return string
	 */
	protected function getIdTableName()
	{ return $this->getModel()->tnAuthOrgs; }
	
}//end class

}//end namespace
