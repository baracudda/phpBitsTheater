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

namespace BitsTheater\res;
{//begin namespace

class MenuInfo extends MenuInfoBase {
	
	public function setup($aDirector) {
		parent::setup($aDirector);
		//strings that require concatination need to be defined during setup()
		
		//app menu defined here so that updates to main program will not affect derived menus
	}
		
}//end class

}//end namespace
