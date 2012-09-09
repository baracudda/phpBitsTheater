<?php
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
