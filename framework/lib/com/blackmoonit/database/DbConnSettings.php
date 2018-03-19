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
class DbConnSettings
{
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
	/** @var string The charset the db connection should use */
	public $charset = null;

	/**
	 * Copies values into matching property names
	 * based on the array keys or object property names.
	 * @param array|object $aThing - array or object to copy from.
	 * @return $this Returns $this for chaining purposes.
	 */
	public function copyFrom( $aThing )
	{
		if ( !empty($aThing) ) {
			foreach ($aThing as $theName => $theValue) {
				if (property_exists($this, $theName)) {
					if ( $theName != 'password' )
					{ $this->{$theName} = $theValue; }
					else
					{ $this->password = base64_encode($theValue); }
				}
			}
		}
		return $this;
	}
	
	/**
	 * Copies array values into matching property names
	 * based on the array keys.
	 * @param array|object $anArray - array to copy from.
	 */
	public function copyFromArray( $anArray )
	{ return $this->copyFrom($anArray); }

	/**
	 * Given an array or object, set the data members to its contents.
	 * @param array|object $aThing - associative array or object
	 * @return $this Returns $this for chaining purposes.
	 */
	public function setDataFrom( $aThing )
	{ return $this->copyFrom($aThing); }
	
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
					if ( empty($this->charset) && !empty($this->dbname) )
					{ $this->charset = 'utf8mb4'; }
				default:
					$theDns = $this->driver . ':host=' . $this->host;
					if ( !empty($this->port) )
					{ $theDns .= ';port=' . $this->port; }
					if ( !empty($this->dbname) )
					{ $theDns .= ';dbname=' . $this->dbname; }
					if ( !empty($this->charset) )
					{ $theDns .= ';charset=' . $this->charset; }
					break;
			}//switch
		}//if
		return $theDns;
	}

}//class

}//namespace
