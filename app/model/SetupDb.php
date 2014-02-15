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

namespace com\blackmoonit\bits_theater\app\model;
use com\blackmoonit\bits_theater\app\Model;
{//namespace begin

class SetupDb extends Model {

	public function setupModels($aScene) {
		$models = self::getAllModelClassInfo();

		$this->callModelMethod($this->director, $models,'setupModel',$aScene);
		$this->callModelMethod($this->director, $models,'setupDefaultData',$aScene);

		array_walk($models, function(&$n) { unset($n); } );
		unset($models);
	}
	

}//end class

}//end namespace
