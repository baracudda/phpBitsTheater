<?php
namespace res\en;
use res\Resources;
{

class Install extends Resources {

	public $continue_button_text = '&gt;&gt; Continue';
	public $back_button_text = 'Back &lt;&lt;';
	
	public $db_types = array(
				'mysql' => 'MySQL 3.x/4.x/5.x',
				'pgsql' => 'PostgreSQL',
				'sqlite' => 'SQLite',
				'firebird' => 'Firebird/Interbase 6',
				'sqlsrv' => 'Microsoft SQL Server / SQL Azure',
				'oci' => 'Oracle Call Interface',
				'dblib' => 'FreeTDS / Microsoft SQL Server / Sybase',
				'odbc' => 'ODBC v3 (IBM DB2, unixODBC and win32 ODBC)',
			);

	public $game_types = array(
				'wow' => 'World of Warcraft',
			);

}//end class

}//end namespace
