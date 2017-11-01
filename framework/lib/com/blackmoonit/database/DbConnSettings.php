<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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

namespace com\blackmoonit\database;
{//begin namespace

/**
 * Database connection information used to connect a PDO class instance.<pre>
 * 	const DRIVER_CUBRID	= 'cubrid';	//Cubrid
 *	const DRIVER_DBLIB	= 'dblib';	//FreeTDS / Microsoft SQL Server / Sybase
 *	const DRIVER_FIREBIRD	= 'firebird';	//Firebird/Interbase 6
 *	const DRIVER_IBM	= 'ibm';	//IBM DB2
 *	const DRIVER_INFORMIX	= 'informix';	//IBM Informix Dynamic Server
 *	const DRIVER_MYSQL	= 'mysql';	//MySQL 3.x/4.x/5.x
 *	const DRIVER_OCI	= 'oci';	//Oracle Call Interface
 *	const DRIVER_ODBC	= 'odbc';	//ODBC v3 (IBM DB2, unixODBC and win32 ODBC)
 *	const DRIVER_PGSQL	= 'pgsql';	//PostgreSQL
 *	const DRIVER_SQLITE	= 'sqlite';	//SQLite 3 and SQLite 2
 *	const DRIVER_SQLSRV	= 'sqlsrv';	//Microsoft SQL Server / SQL Azure
 *	const DRIVER_4D		= '4d';		//4D
 *	</pre>
 */
class DbConnSettings {
	const DRIVER_CUBRID		= 'cubrid';		//Cubrid
	const DRIVER_DBLIB		= 'dblib';		//FreeTDS / Microsoft SQL Server / Sybase
	const DRIVER_FIREBIRD	= 'firebird';	//Firebird/Interbase 6
	const DRIVER_IBM		= 'ibm';		//IBM DB2
	const DRIVER_INFORMIX	= 'informix';	//IBM Informix Dynamic Server
	const DRIVER_MYSQL		= 'mysql';		//MySQL 3.x/4.x/5.x
	const DRIVER_OCI		= 'oci';		//Oracle Call Interface
	const DRIVER_ODBC		= 'odbc';		//ODBC v3 (IBM DB2, unixODBC and win32 ODBC)
	const DRIVER_PGSQL		= 'pgsql';		//PostgreSQL
	const DRIVER_SQLITE		= 'sqlite';		//SQLite 3 and SQLite 2
	const DRIVER_SQLSRV		= 'sqlsrv';		//Microsoft SQL Server / SQL Azure
	const DRIVER_4D			= '4d';			//4D
	
	public $driver = null;
	public $host = null;
	public $port = null;
	public $dbname = null;
	public $username = null;
	public $password = null;

	/**
	 * Copies array values into matching property names 
	 * based on the array keys.
	 * @param array $anArray - array to copy from.
	 */
	public function copyFromArray(&$anArray) {
		if (empty($anArray))
			return;
		foreach ($anArray as $theName => $theValue) {
			if (property_exists($this, $theName)) {
				$this->{$theName} = $theValue;
			}
		}
		if (!empty($this->password)) {
			$this->password = base64_encode($this->password);
		}
	}

	/**
	 * Based on the data within this object, construct the DNS to be used
	 * for the first parameter in creating a PDO object.
	 */
	public function getDnsParam() {
		$theDns = '';
		if (!empty($this->driver)) {
			switch ($this->driver) {
				case self::DRIVER_SQLITE:
					$theDns = $this->driver.':'.$this->host;
					break;
				case self::DRIVER_MYSQL:
				default:
					$theDns = $this->driver.':host='.$this->host.
							((!empty($this->port)) ? (';port='.$this->port) : '' ).
							';dbname='.$this->dbname.';charset=utf8';
					break;
			}//switch
		}//if
		return $theDns;
	}

}//class

}//namespace
