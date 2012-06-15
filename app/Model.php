<?php
namespace app;
use com\blackmoonit\database\GenericDb as BaseModel;
use com\blackmoonit\database\DbUtils;
use com\blackmoonit\Strings;
use app\config\Settings;
use app\DbException;
use app\Director;
{//begin namespace

/*
 * Base class for Models.
 */
class Model extends BaseModel {
	public $myAppNamespace;
	public $tbl_;
	public $director;
	
	public function __construct(Director $aDirector, $aDbConn) {
		$this->myAppNamespace = strtolower($this->name);
		$this->tbl_ = $aDirector->table_prefix;
		$this->director = $aDirector;
		$this->_setupArgCount = 1;
		parent::__construct($aDbConn);
	}

	/*
	 * $aDbConn - use this connection. If null, create a new one.
	 */
	public function setup($aDbConn) {
		if (is_null($aDbConn))
			$this->connect($this->getDbConnInfo());
		else {
			$this->db = $aDbConn;
		}
		
		parent::setup();
	}
	
	public function cleanup() {
		unset($this->db);
		unset($this->myAppNamespace);
		unset($this->director);
		unset($this->tbl_);
		parent::cleanup();
	}
	
	public function isConnected() {
		return (isset($this->db));
	}
	
	public function getDbConnInfo() {
		return DbUtils::readDbConnInfo(BITS_DB_INFO);
	}
	
	/**
	 * Execute DML (data manipulation language - INSERT, UPDATE, DELETE) statements
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too. 
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return number of rows affected; using params returns TRUE instead.
	 */
	public function execDML($aSql, $aParamValues=null, $aParamTypes=null) {
		try {
			if (is_null($aParamValues)) {
				return $this->db->exec($aSql);
			} else {
				$theStatement = $this->db->prepare($aSql);
				$this->bindValues($theStatement,$aParamValues,$aParamTypes);
				return $theStatement->execute();
			}
		} catch (\PDOException $pdoe) {
			throw new DbException($pdoe);
		}
	}

	/**
	 * Execute Select query, returns PDOStatement.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too. 
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return PDOStatement on success.
	 */
	public function query($aSql, $aParamValues=null, $aParamTypes=null) {
		try {
			if (is_null($aParamValues)) {
				return $this->db->query($aSql);
			} else {
				$theStatement = $this->db->prepare($aSql);
				$this->bindValues($theStatement,$aParamValues,$aParamTypes);
				$theStatement->execute();
				return $theStatement;
			}
		} catch (\PDOException $pdoe) {
			throw new DbException($pdoe);
		}
	}
	
	/**
	 * combination query & fetch a single row, returns null if errored
	 */
	public function getTheRow($aSql, $aParamValues=null, $aParamTypes=null) {
		$theResult = null;
		$r = $this->query($aSql,$aParamValues,$aParamTypes);
		if ($r)
			$theResult = $r->fetch();
		$r->closeCursor();
		return $theResult;
	}
	
	/**
	 * Return TRUE if specified table exists.
	 * @param string $aTableName
	 */
	public function exists($aTableName) {
		try {
			$this->query("SELECT 1 FROM $aTableName WHERE 1=0");
			return true;
		} catch (DbException $dbe) {
			return false;
		}
	}
	
	/**
	 * return TRUE iff table exists and is empty.
	 */	
	public function isEmpty($aTableName) {
		$r = $this->query("SELECT 1 FROM $aTableName WHERE EXISTS(SELECT * FROM $aTableName LIMIT 1)");
		return ($r->fetch()==null);
	}
	
	//===== Parameterized queries =====	

	/**
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aParamSql - the parameterized SQL string.
	 * @throws DbException if there is an error.
	 * @return PDOStatement is returned, ready for binding to params.
	 */
	public function prepareSQL($aParamSql) {
		try {
			return $this->db->prepare($aParamSql);
		} catch (\PDOException $pdoe) {
			throw new DbException($pdoe, 'Preparing: '.$aParamSql);
		}
	}

	/**
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aParamSql - the parameterized SQL string.
	 * @param array $aListOfParamValues - array with the array of values for the parameters in the SQL statement.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 */
	public function execMultiDML($aParamSql, $aListOfParamValues, $aParamTypes=null) {
		try {
			$theStatement = $this->db->prepare($aParamSql);
			foreach ($aListOfParamValues as $theSqlParams) {
				$this->bindValues($theStatement,$theSqlParams,$aParamTypes);
				$theStatement->execute();
				$theStatement->closeCursor();
			}
		} catch (\PDOException $pdoe) {
			throw new DbException($pdoe);
		}
	}
	
	
	//===== static helper functions =====
	
	static public function getModelClassPattern() {
		return BITS_PATH.'app'.DIRECTORY_SEPARATOR.'model'.DIRECTORY_SEPARATOR.'*.php';
	}
	
	static public function getAllModelClassInfo() {
		$theModels = array();
		foreach (glob(self::getModelClassPattern()) as $theModelFile) {
			$theModelClass = str_replace('.php','',basename($theModelFile));
			$classInfo = new \ReflectionClass('\\app\\model\\'.$theModelClass);
			if (!$classInfo->isAbstract()) {
			    $theModels[] = $classInfo;
			}
			unset($classInfo);
		}
		return $theModels;
	}
	
	public function getRes($aName) {
		return $this->director->getRes($aName);
	}
	
}//end class

}//end namespace
