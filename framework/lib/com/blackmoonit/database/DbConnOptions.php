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

class DbConnOptions
{
	const DB_CONN_SCHEME_INI = 'ini';		//default connection scheme
	const DB_CONN_SCHEME_ALIAS = 'alias';	//an alias name defined in php.ini
	const DB_CONN_SCHEME_URI = 'uri';		//uri to a file containing the DNS value
	//custom scheme is none of the above
	
	public $myDbConnName = null;
	
	/**
	 * Prefix all table names with this value when using this connection.
	 * @var string
	 */
	public $table_prefix = null;
	/**
	 * @see http://www.php.net/manual/en/pdo.construct.php
	 * @var string
	 */
	public $dns_scheme = null;
	/**
	 * Use this custom PDO DNS string if the scheme is not 'ini'.
	 * @var string
	 */
	public $dns_value = null;
	
	/**
	 * When using INI dns_scheme, this would be the filename used (without path).
	 * @var string
	 */
	public $ini_filename = null;

	/**
	 * Standard constructor takes a name and defaults some properties based on the name.
	 * @param string $aDbConnName - the name to use.
	 */
	public function __construct($aDbConnName=null) {
		$this->myDbConnName = $aDbConnName;
		if ( !empty($aDbConnName) )
			$this->table_prefix = $aDbConnName.'_';
	}
	
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
				if (property_exists($this, $theName))
				{ $this->{$theName} = $theValue; }
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
	 * Constructs dbconn options for an INI scheme with default values.
	 * @param string $aDbConnName - the name to use.
	 * @return \com\blackmoonit\database\DbConnOptions Return the newly constructed object.
	 */
	static public function asSchemeINI($aDbConnName) {
		$o = new DbConnOptions($aDbConnName);
		$o->dns_scheme = self::DB_CONN_SCHEME_INI;
		$o->ini_filename = 'dbconn-'.$aDbConnName;
		return $o;
	}
	
}//class

}//namespace
