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
use BitsTheater\costumes\DbConnInfo;
use com\blackmoonit\exceptions\DbException;
{//begin namespace

abstract class ANonDbModel extends BaseModel
{
	/** @var NULL Non DB Models have no connection. */
	const DB_CONN_NAME = null;
	
	/**
	 * While not connecting to a database, we need the org ID info it
	 * contains in order to direct our getConfigSetting() calls to it.
	 * @param DbConnInfo $aDbConnInfo - the connection information.
	 * @throws DbException - if failed to connect, this exception is thrown.
	 * @return $this Returns $this for chaining.
	 */
	public function connectTo( DbConnInfo $aDbConnInfo )
	{
		//even though we have no DB Connection, by default when none
		//  are specified, the Root org's db conn info will be used.
		//  This is great because Config Settings are defined by org
		//  and now we know which org settings will be used whenever
		//  model code tries to use getConfigSetting() method.
		$this->myDbConnInfo = $aDbConnInfo;
		//call our own setup method rather than the typical one.
		$this->setupNonDbModel();
	}
	
	/**
	 * Generic setup method for non-Db Models since most Db models use
	 * setupAfterDbConnected() method.
	 */
	public function setupNonDbModel()
	{
		//setup code would go in a descendant
	}

}//end class

}//end namespace
