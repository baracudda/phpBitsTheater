<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Resources;
use com\blackmoonit\database\DbConnOptions;
use com\blackmoonit\database\DbConnSettings;
use com\blackmoonit\database\DbConnInfo;
{//begin namespace

class Install extends Resources {

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
