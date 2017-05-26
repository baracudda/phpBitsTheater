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
use BitsTheater\costumes\CursorCloset\ARecord as BaseCostume;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Auth accounts can use this costume to wrap auth group info.
 * PDO statements can fetch data directly into this class.
 */
class AuthGroup extends BaseCostume
{
	/**
	 * My fully qualified classname.
	 * @var string
	 */
	const ITEM_CLASS = __CLASS__;
	
	public $group_id;
	public $group_name;
	public $parent_group_id;
	
	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		//$o->group_id = intval($o->group_id); leave as string
		$o->parent_group_id = Strings::toInt($o->parent_group_id);
		return $o;
	}

}//end class
	
}//end namespace
