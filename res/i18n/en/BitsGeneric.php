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

class BitsGeneric extends BaseResources {
	
	public $page_not_found = 'Page Not Found';
	
	public $save_button_text = 'Save';
	
	public $msg_nothing_found = 'Nothing found.';
	public $msg_permission_denied = 'Permission Denied';
	
	public $errmsg_default = 'I am slain, Horatio...' ;
	public $errmsg_not_done_yet = 'Feature is not yet implemented.' ;
	
	public $errmsg_database_not_connected = 'database connection missing';
	public $errmsg_arg_is_empty = 'parameter %s must not be empty';
	public $errmsg_var_is_empty = '%s must not be empty';
	public $errmsg_file_not_found = 'File not found';

	public $errmsg_mailer_missing_config =
		'Mailer was not given the required setting [%1$s].' ;
	public $errmsg_mailer_failed = 'Mailer failed. [%1$s]' ;
	public $errmsg_db_exception =
		'An error occurred while accessing the DB. [%1$s]' ;
	public $errmsg_entity_not_found =
		'Entity with ID [%1$s] not found.' ;
	public $errmsg_service_unavailable = 'The server is currently unavailable.';
	public $errmsg_too_many_requests = 'Too many requests at this time.';

	public $label_header_title_suffix_demo_mode = '(demo)';
	
}//end class

}//end namespace
