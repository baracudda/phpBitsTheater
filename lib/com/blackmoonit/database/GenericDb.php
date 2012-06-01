<?php
namespace com\blackmoonit\database;
use \PDO;
use com\blackmoonit\AdamEve as BaseDbClass;
use com\blackmoonit\DbUtils;
{//begin namespace

class GenericDb extends BaseDbClass {
	public $db = null;

	/**
	 * PDO database connection and helper functions.
	 * @param string/array $aDnsInfo - if string, use as dns (see link for acceptable formats); else array(dns,usr,pwd).
	 * @link http://php.net/manual/pdo.construct.php
	 */
	public function connect($aDnsInfo) {
		$this->db = DbUtils::getConnection($aDnsInfo);
	}

	/**
	 * Get a PDO database connection.
	 * @param string/array $aDnsInfo - if string, use as dns (see link for acceptable formats); else array(dns,usr,pwd).
	 * @link http://php.net/manual/pdo.construct.php
	 */
	static public function getConnection($aDnsInfo) {
		return DbUtils::getPDOConnection($aDnsInfo);
	}

	public function dbType() {
		return DbUtils::getDbType($this->db);
	}
	
	/**
	 * Return TRUE if SQL statement returns NO results.
	 * @param string $aSql
	 * @param array $aSqlParams
	 */
	function isNoResults($aSql, array $aSqlParams) {
		$theStatement = $this->db->prepare($aSql);
		foreach ($aSqlParams as $theKey=>$theValue) {
			$theStatement->bindValue($theKey,$theValue);
		}
		return ($theStatement->execute() && !$theStatement->fetch());
	}
	
	/**
	 * Bind each named param with its value.
	 * @param PDOStatement or string $aStatement - the prepared SQL statement, or SQL string to be prepared.
	 * @param array $aSqlParams - the values to bind to the prepared statement.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 */
	public function bindValues($aStatement, array $aSqlParams, $aParamTypes = NULL) {
		if (is_string($aStatement))
			$theStatement = $this->db->prepare($aStatement);
		else
			$theStatement = $aStatement;
		foreach ($aSqlParams as $theKey=>$theValue) {
			if (is_array($theValue))
				continue;
			if ($aParamTypes!=null) {
				$theParamType = $aParamTypes[$theKey];
			} else {
				$theParamType = PDO::PARAM_STR;
				if (is_string($theValue))	$theParamType = PDO::PARAM_STR;
				elseif (is_int($theValue)) 	$theParamType = PDO::PARAM_INT;
				elseif (is_bool($theValue))	$theParamType = PDO::PARAM_BOOL;
				elseif (is_null($theValue))	$theParamType = PDO::PARAM_NULL;
			}
			$theStatement->bindValue($theKey,$theValue,$theParamType);
		}
		return $theStatement;
	}
	
	/**
	 * Return an array with missing fields set to NULL so we do not get "key does not exist" notifications.
	 * @param array $aKeys - keys that should exist.
	 * @param array $aValues - values to be used for the existing array keys.
	 */
	public function forceKeysExist(array $aKeys, array $aValues) {
		return array_replace(array_fill_keys($aKeys,NULL),$aValues);
	}
	
	/**
	 * Return SQL format field listing; ie: <code>"rec_id, name".</code>
	 * @param array $aFieldList - fields we want formatted for SQL.
	 * @param array $aTextIdFieldList - (optional) TextId fields require special attention for SELECT statements.
	 */
	public function getSqlFields(array $aFieldList, array $aTextIdFieldList = NULL) {
		$theResult = '';
		foreach ($aFieldList as $theFieldname) {
			if (!in_array($theFieldname, $aTextIdFieldList, true)) {
				$theResult .= $theFieldname.', ';
			} else {
				$theResult .= 'HEX('.$theFieldname.') as '.$theFieldname.', ';
			}
		}
		if (!empty($theResult))
			return substr($theResult,0,-2);
		else
			return '*';
	}
	
	/**
	 * Return SQL format for INSERT/UPDATE value field list. 
	 * @param array $aFieldList - fields we want returned from SQL SELECT statement.
	 * @param array $aTextIdFieldList - (optional) TextId fields require special attention.
	 * @throws InvalidArgumentException - if the fieldlist is empty.
	 */
	public function getSqlValueFields(array $aFieldList, array $aTextIdFieldList = NULL) {
		$theResult = '';
		foreach ($aFieldList as $theFieldname) {
			if (!in_array($theFieldname, $aTextIdFieldList, true)) {
				$theResult .= ':'.$theFieldname.', ';
			} else {
				$theResult .= 'UNHEX(:'.$theFieldname.'), ';
			}
		}
		if (!empty($theResult))
			return substr($theResult,0,-2);
		else
			throw new InvalidArgumentException('invalid field listing');
	}
	
}//class

}//namespace