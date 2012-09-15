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
{//begin namespace

class Permissions extends Resources {

	public $right_value_keys = array('allow','disallow','deny');
	
	public $namespace_keys = array('auth','config','accounts','home',);
			
	public $auth_keys = array('modify','create','delete');
	
	public $config_keys = array('modify');
	
	public $accounts_keys = array('modify','delete'); //anyone can create/register a new account
			
	public $home_keys = array('view',);

}//end class

}//end namespace
