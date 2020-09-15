<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes ;
use BitsTheater\costumes\colspecs\CommonMySql;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\DbException;
use Exception;
use PDOException;
{ // begin namespace

/**
 * A set of methods useful when implementing IFeatureVersioning.
 */
trait WornForFeatureVersioning
{
	/**
	 * Returns the current feature metadata for the given feature ID.
	 * @param string $aFeatureId - the feature ID needing its current metadata.
	 * @return array Current feature metadata.
	 */
	public function getCurrentFeatureVersion($aFeatureId=null) {
		return array(
				'feature_id' => self::FEATURE_ID,
				'model_class' => $this->mySimpleClassName,
				'version_seq' => self::FEATURE_VERSION_SEQ,
		);
	}

	/**
	 * Meta data may be necessary to make upgrades-in-place easier. Check for
	 * existing meta data and define if not present.
	 * @param \BitsTheater\Scene $aScene - (optional) extra data may be supplied
	 */
	public function setupFeatureVersion($aScene) {
		/* @var $dbMeta \BitsTheater\models\PropCloset\SetupDb */
		$dbMeta = $this->getProp('SetupDb');
		$theFeatureData = $dbMeta->getFeature(self::FEATURE_ID);
		if (empty($theFeatureData)) {
			$theFeatureData = $this->getCurrentFeatureVersion();
			$theFeatureData['version_seq'] = $this->determineExistingFeatureVersion($aScene);
			$dbMeta->insertFeature($theFeatureData);
		}
	}
	
	/**
	 * Sets up one table in the model.
	 * Consumed by setupModel().
	 * @param string $aTableConst the table name constant
	 * @param string $aTableName the table name value
	 */
	protected function setupTable( $aTableConst, $aTableName )
	{
		$theSql = '' ;
		try
		{
			$theSql = $this->getTableDefSql( $aTableConst ) ;
			$this->execDML( $theSql ) ;
			$this->debugLog(
					$this->getRes( 'install/msg_create_table_x_success/' . $aTableName )
			) ;
		}
		catch( PDOException $pdox )
		{
			$this->errorLog(__METHOD__.' failed: '.$pdox->getMessage().' sql='.$theSql);
			throw new DbException( $pdox, $theSql ) ;
		}
	}

	/**
	 * Get the field size as the db has it currently defined.
	 * @param string $aFieldName - the field/column name.
	 * @param string $aTableName - the table name.
	 * @return number Returns the size of the field.
	 */
	protected function getFieldSize($aFieldName, $aTableName) {
		$theSql = SqlBuilder::withModel($this);
		switch( $this->dbType() )
		{
			case static::DB_TYPE_MYSQL:
			default:
				//for this particular operation, having the database prepended to the table name breaks the SQL
				if (strpos($aTableName, $this->myDbConnInfo->dbName)!==false)
					$aTableName = Strings::strstr_after($aTableName, '`'.$this->myDbConnInfo->dbName.'`.');
				//define the meta-table-info query
				$theSql->startWith(CommonMySql::getFieldSizeSql(
						$this->myDbConnInfo->dbName, $aTableName, $aFieldName)
				);
		}
		//$this->debugLog(__METHOD__.' '.$this->debugStr($theSql));
		return $theSql->getTheRow()['size']+0 ;
	}
	
	/**
	 * Returns the database's description of a table column. This is
	 * idiosyncratic to each engine; only mySQL is supported so far.
	 * @param string $aTableName the table in which the column is defined
	 * @param string $aColumnName the name of the column to be described
	 * @return object|NULL|boolean an object describing the column, or null if
	 *  the column doesn't exist, or false if the database type is not yet
	 *  supported
	 * @since BitsTheater v4.0.0
	 */
	protected function describeColumn( $aTableName, $aColumnName )
	{
		$theSql = SqlBuilder::withModel($this) ;
		switch( $this->dbType() )
		{
			case static::DB_TYPE_MYSQL:
			{
				//for this particular operation, having the database prepended to the table name breaks the SQL
				if ( strpos($aTableName, $this->myDbConnInfo->dbName)!==false )
					$aTableName = Strings::strstr_after($aTableName, $theSql->getQuoted($this->myDbConnInfo->dbName) . '.');
				$theSql->startWith( 'SELECT * FROM information_schema.COLUMNS' )
					->startWhereClause()
					->mustAddParam( 'TABLE_SCHEMA', $this->myDbConnInfo->dbName )
					->setParamPrefix( ' AND ' )
					->mustAddParam( 'TABLE_NAME', $aTableName )
					->mustAddParam( 'COLUMN_NAME', $aColumnName )
					->endWhereClause()
					;
				/* Available fields for MySQL result:
				 * TABLE_CATALOG
				 * TABLE_SCHEMA
				 * TABLE_NAME
				 * COLUMN_NAME
				 * ORDINAL_POSITION
				 * COLUMN_DEFAULT
				 * IS_NULLABLE
				 * DATA_TYPE
				 * CHARACTER_MAXIMUM_LENGTH
				 * CHARACTER_OCTET_LENGTH
				 * NUMERIC_PRECISION
				 * NUMERIC_SCALE
				 * DATETIME_PRECISION
				 * CHARACTER_SET_NAME
				 * COLLATION_NAME
				 * COLUMN_TYPE
				 * COLUMN_KEY
				 * EXTRA
				 * PRIVILEGES
				 * COLUMN_COMMENT
				 */
				return ((object)($theSql->getTheRow())) ;
			} break ;
			default:
				$this->debugLog( __METHOD__
						. ' is not yet supported for DB type '
						. $this->dbType()
					);
				return false ;
		}
	}
	
	/**
	 * Return a String representation of the type of the field,
	 * or false if it doesn't exist.
	 * @param string $aFieldName - the field name to check.
	 * @param string $aTableName - the table to check.
	 * @return boolean|string Return type of field if the field
	 *   exists in table, FALSE otherwise.
	 */
	public function getFieldType($aFieldName, $aTableName)
	{
		try
		{
			switch( $this->dbType() )
			{
				case static::DB_TYPE_MYSQL:
				default:
					$r = $this->query("DESCRIBE {$aTableName} $aFieldName");
					$result = $r->fetch();
					if(!empty($r))
					{
						return $result["Type"];
					}
			}
		}
		catch (Exception $e)
		{
			//if there is any kind of exception, just eat so
			//  we can return FALSE to the caller.
			//echo $e->getMessage();
		}
		return false;
	}
	
	/**
	 * Sometimes a model changes its name and/or its FeatureID.
	 * @param string $aOldFeatureId - the old feature ID.
	 */
	public function migrateFeatureVersionId($aOldFeatureId)
	{
		$dbMeta = $this->getProp('SetupDb');
		if (!empty($dbMeta) && $dbMeta->isConnected()) {
			$theOldFeatureData = $dbMeta->getFeature($aOldFeatureId);
			if (!empty($theOldFeatureData)) {
				$dbMeta->removeFeature($aOldFeatureId);
				$theFeatureData = $this->getCurrentFeatureVersion();
				$theFeatureData['version_seq'] = $theOldFeatureData['version_seq'];
				$dbMeta->insertFeature($theFeatureData);
			}
		}
	}
	
	/**
	 * Add a field to an existing table.
	 * @param number|string $aVersionNum - the version number for log entries.
	 * @param string $aField - the field name.
	 * @param string $aTable - the fully qualified table name.
	 * @param string $aFieldDef - the SQL field definition.
	 * @param string $aAfterExistingFieldX - (optional) - place the audit
	 *   fields structurally after this one.
	 */
	public function addFieldToTable($aVersionNum, $aField, $aTable,
			$aFieldDef, $aAfterExistingFieldX=null)
	{
		$theSql = SqlBuilder::withModel($this);
		//previous versions may have added the field already, so double check before adding it.
		if ( !$this->isFieldExists($aField, $aTable) )
		{
			$theSql->startWith('ALTER TABLE')->add($aTable);
			$theSql->add('  ADD COLUMN')->add($aFieldDef);
			if (!empty($aAfterExistingFieldX))
				$theSql->add('AFTER')->add($aAfterExistingFieldX);
			try
			{ $theSql->execDML(); }
			catch (\Exception $e)
			{ throw $theSql->newDbException(__METHOD__ . " [{$aTable}]", $e); }
			$this->debugLog("v{$aVersionNum}: added [{$aField}] to [{$aTable}]");
		} else {
			$this->debugLog("v{$aVersionNum}: {$aTable} already updated.");
		}
	}
	
	/**
	 * Return true/false if an index has been defined or not with a given name.
	 * @param string $aTableName - the table name.
	 * @param string $aIndexName - the index name.
	 * @return boolean Returns TRUE if the index name exists.
	 */
	protected function isIndexDefined($aTableName, $aIndexName) {
		$theSql = SqlBuilder::withModel($this);
		switch( $this->dbType() )
		{
			case static::DB_TYPE_MYSQL:
			default:
				$theSql->startWith(CommonMySql::getIndexDefinitionSql(
						$aTableName, $aIndexName
				));
		}
		//$theSql->logSqlDebug(__METHOD__);
		$ps = $theSql->query();
		//$this->debugLog($ps->rowCount());
		return ($ps->rowCount()>0);
	}
	
	/**
	 * Checks whether a column is indexed. Distinct from
	 * <code>isIndexDefined</code> in that it checks using the name of the
	 * column that is indexed, not the name of the index itself.
	 *
	 * Currently supports either mySQL or PostgreSQL; default is to return a
	 * false negative. There is no <code>CommonMySql</code> function available
	 * for the query, because there is no commonality to exploit; everybody does
	 * something different to support this type of operation.
	 *
	 * @param string $aTableName the name of the table in which the index would
	 *  be defined
	 * @param string $aFieldName the name of the column that would be indexed
	 * @return boolean <code>true</code> if the column is indexed
	 * @since BitsTheater v4.0.0
	 */
	public function isFieldIndexed( $aTableName, $aFieldName )
	{
		try
		{
			switch( $this->dbType() )
			{
				case static::DB_TYPE_MYSQL:
				{ // https://dev.mysql.com/doc/refman/5.7/en/show-index.html
					$theIndices = $this->query(
							'SHOW INDEX FROM ' . $aTableName
							.	' WHERE Column_name=\'' . $aFieldName . '\''
							);
					return ( !empty($theIndices) ) ;
				} break ;
				case static::DB_TYPE_PGSQL:
				{ // https://www.postgresql.org/docs/9.1/static/view-pg-indexes.html
					$theResult = $this->query(
							'SELECT COUNT(indexname) AS indexcount '
							.	'FROM pg_indexes WHERE tablename=\'' . $aTableName
							.	'\' AND indexdef LIKE \'%' . $aFieldName . '%\''
							);
					return ( !empty($theResult['indexcount']) ) ;
				} break ;
				default:
				{
					$this->debugLog( __METHOD__
							. ' does not yet support DB type [' . $this->dbType()
							. ']; returning potentially-false negative.' ) ;
					return false ;
				}
			}
		}
		catch( PDOException $pdox )
		{
			$this->errorLog( __METHOD__
					. ' encountered an exception and is returning negative:  '
					. $pdox->getMessage()
					);
			return false ;
		}
	}
	
	/**
	 * Return the Collation (sorting ruleset) of the field,
	 * or false if it doesn't exist.
	 * @param string $aFieldName - the field name to check.
	 * @param string $aTableName - the table to check.
	 * @return boolean|string Return Collation of field if the field
	 *   exists in table and has a Collation, FALSE otherwise.
	 */
	public function getFieldCollation($aFieldName, $aTableName)
	{
		$theSql = SqlBuilder::withModel($this);
		switch ( $this->dbType() ) {
			case static::DB_TYPE_MYSQL:
			default:
				$theSql->startWith('SHOW FULL COLUMNS FROM')
					->add($aTableName)
					->startWhereClause()
					->mustAddParam('Field', $aFieldName)
					->endWhereClause()
					;
		}
		try {
			switch ( $this->dbType() ) {
				case static::DB_TYPE_MYSQL:
				default:
					$rs = $theSql->getTheRow();
					if ( !empty($rs) && !empty($rs['Collation']) ) {
						return $rs['Collation'];
					}
			}
		}
		catch ( \Exception $x )
		{
			//if there is any kind of exception, just eat so
			//  we can return FALSE to the caller.
			//echo $e->getMessage();
		}
		return false;
	}
	
	/**
	 * Return TRUE if the field is NULLable.
	 * @param string $aFieldName - the field name to check.
	 * @param string $aTableName - the table to check.
	 * @return boolean Return TRUE if thefield
	 *   exists in table and can be set to NULL, FALSE otherwise.
	 */
	public function isFieldNullable($aFieldName, $aTableName)
	{
		$theSql = SqlBuilder::withModel($this);
		switch ( $this->dbType() ) {
			case static::DB_TYPE_MYSQL:
			default:
				$theSql->startWith('SHOW FULL COLUMNS FROM')
					->add($aTableName)
					->startWhereClause()
					->mustAddParam('Field', $aFieldName)
					->endWhereClause()
					;
		}
		try {
			switch ( $this->dbType() ) {
				case static::DB_TYPE_MYSQL:
				default:
					$rs = $theSql->getTheRow();
					if ( !empty($rs) && !empty($rs['Null']) ) {
						return ($rs['Null'] === 'YES');
					}
			}
		}
		catch ( \Exception $x )
		{
			//if there is any kind of exception, just eat so
			//  we can return FALSE to the caller.
			//echo $e->getMessage();
		}
		return false;
	}
	
	
} // end trait

} // end namespace
