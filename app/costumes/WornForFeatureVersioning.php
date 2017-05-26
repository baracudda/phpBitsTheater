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
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\colspecs\CommonMySql;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
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
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function setupFeatureVersion($aScene) {
		/* @var $dbMeta MetaModel */
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
			case self::DB_TYPE_MYSQL:
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
				case self::DB_TYPE_MYSQL:
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
	 * @param unknown $aNewFeatureData
	 */
	public function migrateFeatureVersionId($aOldFeatureId)
	{
		/* @var $dbMeta MetaModel */
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
			{ throw $theSql->newDbException(__METHOD__ . "[{$aTable}]", $e); }
			$this->debugLog("v{$aVersionNum}: added [{$aField}] to [{$aTable}]");
		} else {
			$this->debugLog("v{$aVersionNum}: {$aTable} already updated.");
		}
	}
	
} // end trait

} // end namespace
