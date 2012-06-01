<?php
namespace com\blackmoonit\database;
use \PDO;
{//begin namespace

class DbUtils {
	private function __construct() {} //do not instantiate

	static private function cnvDbConnIni2Dns($aConfigData) {
		$theDns = $aConfigData['dbconn']['dbtype'].':host='.$aConfigData['dbconn']['dbhost'].
				((!empty($aConfigData['dbconn']['dbport'])) ? (';port='.$aConfigData['dbconn']['dbport']) : '' ).
				';dbname='.$aConfigData['dbconn']['dbname'];
		return array('dns'=>$theDns,'usr'=>$aConfigData['dbconn']['dbuser'],'pwd'=>$aConfigData['dbconn']['dbpwrd']);
	}

	/**
	 * Returns the database connection driver being used.
	 * cubrid	PDO_CUBRID	Cubrid
	 * dblib 	PDO_DBLIB 	FreeTDS / Microsoft SQL Server / Sybase
	 * firebird	PDO_FIREBIRD Firebird/Interbase 6
	 * ibm		PDO_IBM 	IBM DB2
	 * informix	PDO_INFORMIX IBM Informix Dynamic Server
	 * mysql	PDO_MYSQL 	MySQL 3.x/4.x/5.x
	 * oci		PDO_OCI 	Oracle Call Interface
	 * odbc		PDO_ODBC 	ODBC v3 (IBM DB2, unixODBC and win32 ODBC)
	 * pgsql	PDO_PGSQL 	PostgreSQL
	 * sqlite	PDO_SQLITE 	SQLite 3 and SQLite 2
	 * sqlsrv	PDO_SQLSRV 	Microsoft SQL Server / SQL Azure
	 * 4d		PDO_4D		4D
	 */
	static public function getDbType($aDbConn) {
		if (isset($aDbConn)) {
			return $aDbConn->getAttribute(PDO::ATTR_DRIVER_NAME);
		} else {
			return '';
		}
	}
	
	/**
	 * Load pertinent database connection information from INI config file.
	 * @param string $aConfigPath
	 * @return array The settings are returned as an associative array.
	 * @throws InvalidArgumentException if aConfigPath is empty.
	 * @throws RuntimeException if unable to import the file.
	 */
	static public function readDbConnInfo($aConfigPath) {
		if (empty($aConfigPath)) {
			throw new InvalidArgumentException('Config path is empty.');
		}
		
		$theDefaultConfig = array('dbopts','dbconn');
		$theDefaultConfig['dbopts'] = array_fill_keys(array('table_prefix','dns_scheme','dns_value'),null);
		$theDefaultConfig['dbconn'] = array_fill_keys(array('dbtype','dbhost','dbport','dbname','dbuser','dbpwrd'),null);

		if ($theConfig = parse_ini_file($aConfigPath, TRUE)) {
			$theConfig = array_replace_recursive($theDefaultConfig,$theConfig);
			switch ($theConfig['dbopts']['dns_scheme']) {
				case 'ini':
					$theConfig = array_merge($theConfig,DbUtils::cnvDbConnIni2Dns($theConfig));
				case 'alias':
					$theConfig['dns'] = $theConfig['dbopts']['dns_value'];
				default:
					$theConfig['dns'] = $theConfig['dbopts']['dns_scheme'].':'.$theConfig['dbopts']['dns_value'];
			}
			return $theConfig;
		} else {
			throw new RuntimeException('Unable to import '.$aConfigPath.'.');
		}
	}
	
	/**
	 * 
	 * @param string $aDnsScheme - 'alias', 'ini', 'uri'
	 * @param string $aDnsValue - string whose parsed value varies by aDnsScheme.
	 */
	static public function getDbConnInfo($aDnsScheme, $aDnsValue) {
		switch ($aDnsScheme) {
			case 'ini':
				return DbUtils::getDnsFromIniFile($aDnsValue);
			case 'alias':
				return $aDnsValue;
			default:
				return $aDnsScheme.':'.$aDnsValue;
		}
	}
	
	/**
	 * Returns an array containing ('dns'=>$dnsString, 'usr'=>$username, 'pwd'=>$password) for PDO constructor
	 * 
	 * ;comments begin with semi-colon
	 * [dbconn]
	 * driver = mysql
	 * host = localhost
	 * ;port = 3306
	 * dbname = my_db_name
	 * username = rootbeer
	 * password = "DoubleHelix!"
	 * 
	 * @param string $aConfigFilename - filename containing the INI information needed.
	 * @throws InvalidArgumentException - if the filename is empty.
	 * @throws RuntimeException - if the filename fails to import.
	 */
	static public function getDnsFromIniFile($aConfigFilename) {
		if (empty($aConfigFilename)) {
			throw new InvalidArgumentException('Config filename empty.');
		}
		if (!$theConfig = parse_ini_file($aConfigFilename, TRUE)) {
			throw new RuntimeException('Unable to import '.$aConfigFilename.'.');
		}
		return DbUtils::cnvDbConnIni2Dns($theConfig);
	}
	
	/**
	 * Get a PDO database connection.
	 * @param string/array $aDnsInfo - if string, use as dns (see link for acceptable formats); else array(dns,usr,pwd).
	 * @link http://php.net/manual/pdo.construct.php
	 */
	static public function getPDOConnection($aDnsInfo) {
		$theResult = null;
		if (is_array($aDnsInfo))
			$theResult = new PDO($aDnsInfo['dns'],$aDnsInfo['usr'],$aDnsInfo['pwd']);
		else
			$theResult = new PDO($aDnsInfo,'','');
		$theResult->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
		$theResult->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		return $theResult;
	}
	
	
}//class

}//namespace
