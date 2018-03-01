<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
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

namespace BitsTheater\res\en;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class BitsAuth extends BaseResources {
	
	public $msg_basic_auth_fail = 'Not authorized';
	public $msg_sanitized_input_is_empty = '[%1$s] is too malformed to use.';
	public $msg_no_permission_to_create_user = 'Database user not permitted to create users.';
	public $msg_generic_create_user_error = 'SQLSTATE[%1$s] returned trying to create a user: %2$s';
	
}//end class

}//end namespace
