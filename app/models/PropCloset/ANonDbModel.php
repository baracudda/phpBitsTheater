<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
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

namespace BitsTheater\models\PropCloset;
use BitsTheater\Model as BaseModel;
{//begin namespace

abstract class ANonDbModel extends BaseModel
{
	
	public function connect($aDbConnName=null)
	{
		//do not call parent::connect() since we are not connecting to a db.
		$this->setupModel();
	}
	
	/**
	 * Generic setup method for non-Db Models
	 */
	public function setupModel()
	{
		//setup code would go in a descendant
	}

}//end class

}//end namespace
