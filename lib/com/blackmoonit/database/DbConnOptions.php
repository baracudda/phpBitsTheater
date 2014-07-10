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

class DbConnOptions {
	const DB_CONN_SCHEME_INI = 'ini';		//default connection scheme
	const DB_CONN_SCHEME_ALIAS = 'alias';	//an alias name defined in php.ini
	const DB_CONN_SCHEME_URI = 'uri';		//uri to a file containing the DNS value
	//custom scheme is none of the above
	
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
	}
	
}//class

}//namespace
