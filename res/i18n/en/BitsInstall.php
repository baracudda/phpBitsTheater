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
use com\blackmoonit\database\DbConnOptions;
use com\blackmoonit\database\DbConnSettings;
use com\blackmoonit\database\DbConnInfo;
{//begin namespace

class BitsInstall extends BaseResources {
	
	public $landing_page = 'home';
	
	public $continue_button_text = '&gt;&gt; Continue';
	public $back_button_text = 'Back &lt;&lt;';
	
	public $validate_table_prefix = 'A table prefix will prevent table name collisions.';
	public $validate_dbhost = 'Where is the database located?';
	public $validate_dbname = 'What is the database called?';
	public $validate_dbuser = 'Who am I requesting data as?';
	public $validate_dbpwrd = 'What is your quest?';
	
	public $label_dns_scheme_ini = 'Standard Credentials';
	public $label_dns_scheme_alias = 'Alias defined in php.ini';
	public $label_dns_scheme_uri = 'URI';
	public $label_dns_scheme_custom = 'Custom DNS';
	
	public $label_dns_table_prefix = 'Table Prefix';
	public $label_dns_dbhost = 'Host';
	public $label_dns_dbtype = 'Database Type';
	public $label_dns_dbname = 'Database Name';
	public $label_dns_dbuser = 'Username';
	public $label_dns_dbpwrd = 'Password';
		
	public $label_dns_alias = 'Alias';
	
	public $label_dns_uri = 'URI';
	
	public $label_dns_custom = 'DNS';
	
	public $label_site_id = 'Site ID';
	public $desc_site_id = 'A string used to identify this site. This is also used as the Registration Code for default privileges.';
	
}//end class

}//end namespace
