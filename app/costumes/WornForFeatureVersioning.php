<?php
namespace BitsTheater\costumes ;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\models\SetupDb as MetaModel;
use Exception;
use PDOException;
use com\blackmoonit\exceptions\DbException;
use BitsTheater\costumes\colspecs\CommonMySql;
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
			$this->debugLog(__METHOD__.' failed: '.$pdox->getMessage().' sql='.$theSql);
			throw new DbException( $pdox, $theSql ) ;
		}
	}

	/**
	 * Get the field size as the db has it currently defined.
	 * @param string $aFieldName - the field/column name.
	 * @param string $aTableName - the table name.
	 */
	protected function getFieldSize($aFieldName, $aTableName) {
		$theSql = SqlBuilder::withModel($this);
		switch( $this->dbType() )
		{
			case self::DB_TYPE_MYSQL:
			default:
				$theSql->startWith(CommonMySql::getFieldSizeSql(
						$this->myDbConnInfo->dbName, $aTableName, $aFieldName)
				);
		}
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
	
} // end trait

} // end namespace
