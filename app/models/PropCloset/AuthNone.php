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

namespace BitsTheater\models\PropCloset;
use BitsTheater\models\PropCloset\AuthBase as BaseModel;
{//namespace begin

class AuthNone extends BaseModel {
	const TYPE = 'None';  //skip all authentication methods
	const ALLOW_REGISTRATION = false;

	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		return true;
	}
	
	public function getGroupList() {
		return array();
	}

}//end class

}//end namespace
