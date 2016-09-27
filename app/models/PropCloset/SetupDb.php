<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace BitsTheater\models\PropCloset;
use BitsTheater\Model as BaseModel;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\colspecs\CommonMySql ;
use BitsTheater\costumes\IFeatureVersioning;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use com\blackmoonit\FileUtils;
use PDO;
use PDOException;
use Exception;
use BitsTheater\costumes\WornForFeatureVersioning;
{//namespace begin

class SetupDb extends BaseModel implements IFeatureVersioning
{
	use WornForFeatureVersioning {
		WornForFeatureVersioning::getCurrentFeatureVersion as private getCurrentFeatureVersionForSelf;
		WornForFeatureVersioning::setupFeatureVersion as private setupFeatureVersionForSelf;
	}
	
	const MODEL_NAME = __CLASS__ ;
	/**
	 * Used by meta data mechanism to keep the database up-to-date with the code.
	 * A non-NULL string value here means alter-db-schema needs to be managed.
	 * @var string
	 */
	const FEATURE_ID = 'BitsTheater/framework';
	const FEATURE_VERSION_SEQ = 9; //always ++ when making db schema changes
		
	public $tnSiteVersions; const TABLE_SiteVersions = 'zz_versions';
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
        $this->tbl_ = '`'.$this->myDbConnInfo->dbName.'`.'.$this->tbl_;
		
        $this->tnSiteVersions = $this->tbl_.self::TABLE_SiteVersions;
	}
	
	/**
	 * If one of the db schema updates needs to create temp table, then
	 * putting schema here and supplying a way to provide a different
	 * name allows this process.
	 * @param string $aTABLEconst - one of the defined table name consts.
	 * @param string $aTableNameToUse - (optional) alternate name to use.
	 */
	protected function getTableDefSql($aTABLEconst, $aTableNameToUse=null)
	{
		switch($aTABLEconst)
		{
			case self::TABLE_SiteVersions:
				$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnSiteVersions ;
				switch($this->dbType())
				{
					case self::DB_TYPE_MYSQL:
					default:
						return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
							"( feature_id CHAR(120) CHARACTER SET utf8 NOT NULL".
							", model_class CHAR(120) NOT NULL".
							", version_display CHAR(40) CHARACTER SET utf8 NULL".
							", version_seq INT(11) NOT NULL DEFAULT 0".
							", ".CommonMySql::CREATED_TS_SPEC.
							", ".CommonMySql::UPDATED_TS_SPEC.
							", PRIMARY KEY (feature_id, model_class)".
							") DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
				}
				break;
		}
	}
	
	public function setupModel() {
		$this->setupTable(self::TABLE_SiteVersions, $this->tnSiteVersions);
	}
	
	/**
	 * When tables are created, default data may be needed in them. Check
	 * the table(s) for isEmpty() before filling it with default data.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function setupDefaultData($aScene) {
		$this->setupFeatureVersion($aScene);
	}
	
	/**
	 * Meta data may be necessary to make upgrades-in-place easier. Check for
	 * existing meta data and define if not present.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function setupFeatureVersion($aScene) {
		//setupFeatureVersionForSelf will go unused since it references SetupDb model
		//framework version
		$theFeatureData = $this->getFeature(self::FEATURE_ID);
		if (empty($theFeatureData)) {
			$this->insertFeature($this->getCurrentFeatureVersion());
		}
		//website version
		$theFeatureId = $this->getRes('website/getFeatureId');
		$theFeatureData = $this->getFeature($theFeatureId);
		if (empty($theFeatureData)) {
			$this->insertFeature($this->getCurrentFeatureVersion($theFeatureId));
		}
	}
	
	/**
	 * Returns the current feature metadata for the given feature ID.
	 * @param string $aFeatureId - the feature ID needing its current metadata.
	 * @return array Current feature metadata.
	 */
	public function getCurrentFeatureVersion($aFeatureId=null) {
		$theWebsiteFeatureId = $this->getRes('website/getFeatureId');
		if (!empty($aFeatureId) && $aFeatureId===$theWebsiteFeatureId) {
			/*
			list($theSiteVerMajor, $theSiteVerMinor, $theSiteVerInc) = explode('.',$theSiteVersion);
			if (strpos($theSiteVerInc, ' ')!==false) {
				list($theSiteVerInc, $junk) = explode(' ', $theSiteVerInc);
			}
			*/
			return array(
					'feature_id' => $theWebsiteFeatureId,
					'model_class' => $this->mySimpleClassName,
					'version_seq' => $this->getRes('website/version_seq'),
					'version_display' => $this->getRes('website/version'),
			);
		} else {
			$theResult = $this->getCurrentFeatureVersionForSelf($aFeatureId);
			$theFrameworkVersion = $this->getRes('website/getFrameworkVersion/'.self::FEATURE_VERSION_SEQ);
			$theResult['version_display'] = $theFrameworkVersion;
			return $theResult;
		}
	}
	
	/**
	 * Ensures all features use standardized naming convention.
	 * @param string $aFeatureId Feature id for feature in question.
	 * @throws DbException In case of exceptions, this is thrown.
	 */
	protected function normalizeFeature($aFeatureId) {
		//override if your site cares about such things
	}

	/**
	 * Copy the contents of a file into another using template replacements, if defined.
	 * @param string $aSrcFilePath - template source.
	 * @param string $aDestFilePath - template destination.
	 * @param array $aReplacements - replacement name=>value inside the template.
	 * @throws Exception on failure.
	 */
	protected function copyFileContents($aSrcFilePath, $aDestFilePath, $aReplacements) {
		try {
			if (!FileUtils::copyFileContents($aSrcFilePath, $aDestFilePath, $aReplacements)) {
				$theMsg = $this->getRes('admin/msg_copy_cfg_fail/'.basename($aDestFilePath));
				throw new Exception($theMsg);
			}
		} catch (Exception $e) {
			$this->debugLog(__METHOD__."('$aSrcFilePath','$aDestFilePath') failed: ".$e->getMessage());
			throw $e;
		}
	}
	
	/**
	 * Copies the named template stored in the standard template folder using the replacements
	 * param, if defined.
	 * @param string $aTemplateName - name of template minus path and file extension.
	 * @param string $aNewFilePath - new path and filename to use.
	 * @param array $aReplacements - (OPTIONAL) replacement name=>value inside the template.
	 * @return string|boolean Returns the destination filepath or FALSE on failure.
	 */
	protected function installTemplate($aTemplateName, $aNewFilePath, $aReplacements) {
		//copy the .tpl and fill in the replacements
		$theTemplateFilePath = BITS_RES_PATH.'templates'.DIRECTORY_SEPARATOR.$aTemplateName.'.tpl';
		$this->copyFileContents($theTemplateFilePath, $aNewFilePath, $aReplacements);
	}

	/**
	 * Check current feature version and compare it to the
	 * current version, upgrading the db schema as needed.
	 * @param array $aFeatureMetaData - the models current feature metadata.
	 * @param Scene $aScene - extra data may be supplied
	 */
	public function upgradeFeatureVersion($aFeatureMetaData, $aScene) {
		$theSeq = $aFeatureMetaData['version_seq']+0;
		//framework update
		switch (true) {
			//cases should always be lo->hi, never use break; so all changes are done in order.
			case ($theSeq<3):
				//replace old class file with new class file
				$this->installTemplate('I18N', BITS_CFG_PATH.'I18N.php', array(
						//no other lang possible at this time
						'default_lang' => 'en',
						'default_region' => 'US',
				), $aScene);
			case ($theSeq<4):
				//AuthGroups is a default framework class, but may not be there
				//  in actual website, so check for !empty() before "fixing" it.
				/* @var $dbAuthGroups BitsTheater\models\AuthGroups */
				$dbAuthGroups = $this->getProp('AuthGroups');
				if (!empty($dbAuthGroups)) {
					$this->updateFeature($dbAuthGroups->getCurrentFeatureVersion());
				}
		}//switch

		//update the feature table with all but our own model
		$theModels = self::getAllModelClassInfo();
		for ($i=0; $i<count($theModels); $i++) {
			if ($theModels[$i]->getShortName()===$this->mySimpleClassName) {
				unset($theModels[$i]);
				break;
			}
		}
		$this->callModelMethod($this->director, $theModels, 'setupFeatureVersion', $aScene);
		array_walk($theModels, function(&$n) { unset($n); } );
		unset($theModels);
	}
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnSiteVersions : $aTableName );
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnSiteVersions : $aTableName );
	}
	
	/**
	 * Calls all models to create their tables and insert default data, if necessary.
	 * @param $aScene - the currently running page's Scene object.
	 */
	public function setupModels($aScene) {
		$this->setupModel($aScene);
		$this->setupDefaultData($aScene);
		
		$models = self::getAllModelClassInfo();
		
		//Strings::debugLog('SetupModels: '.Strings::debugStr($models));

		$this->callModelMethod($this->director, $models,'setupModel',$aScene);
		$this->callModelMethod($this->director, $models,'setupDefaultData',$aScene);
		$this->callModelMethod($this->director, $models,'setupFeatureVersion',$aScene);
		
		array_walk($models, function(&$n) { unset($n); } );
		unset($models);
	}
	
	/**
	 * @param string $aFeatureId - the feature ID
	 * @param string $aFieldList - (optional) which fields to return, default is all of them.
	 * @return array Returns the feature row as an array.
	 */
	public function getFeature($aFeatureId, $aFieldList=null) {
		$theResultSet = null;
		if ($this->isConnected()) try {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array('feature_id' => $aFeatureId));
			$theSql->startWith('SELECT')->addFieldList($aFieldList)->add('FROM')->add($this->tnSiteVersions);
			$theSql->startWhereClause()->mustAddParam('feature_id')->endWhereClause();
			$theResultSet = $theSql->getTheRow();
			if (!empty($theResultSet)) {
				$theResultSet['version_seq'] += 0;
				if (empty($theResultSet['version_display'])) {
					$theResultSet['version_display'] = 'v'.$theResultSet['version_seq'];
				}
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe,  __METHOD__.' failed.');
		}
		return $theResultSet;
	}
	
	/**
	 * A feature listed in the table did not map to any model class in the website, remove it.
	 * Most likely, the feature changed its feature id and/or model class name.
	 * @param string $aFeatureId - the feature ID.
	 */
	public function removeFeature($aFeatureId) {
		if ($this->isConnected()) try {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array('feature_id' => $aFeatureId));
			$theSql->startWith('DELETE')->add('FROM')->add($this->tnSiteVersions);
			$theSql->startWhereClause()->mustAddParam('feature_id')->endWhereClause();
			$theSql->execDML();
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe,  __METHOD__.' failed.');
		}
	}
	
	/**
	 * @param string $aFieldList - which fields to return, default is all of them.
	 * @return array Returns all rows as an array.
	 */
	public function getFeatureVersionList($aFieldList=null) {
		$theWebsiteFeatureId = $this->getRes('website/getFeatureId');
		$theResultSet = null;
		if ($this->isConnected() && $this->exists()) {
			try {
				$theSql = SqlBuilder::withModel($this);
				$theSql->startWith('SELECT')->addFieldList($aFieldList)->add('FROM')->add($this->tnSiteVersions);
				//$theSql->add('ORDER BY feature_id');
				$ps = $theSql->query();
				if ($ps) {
					$theFeatures = $ps->fetchAll();
					foreach($theFeatures as &$theFeatureRow) {
						$theFeatureRow['version_seq'] += 0;
						$dbModel = $this->getProp($theFeatureRow['model_class']);
						if (empty($dbModel)) {
							$this->removeFeature($theFeatureRow['feature_id']);
							continue;
						}

						$this->normalizeFeature($theFeatureRow['feature_id']);
						$theNewFeatureData = $dbModel->getCurrentFeatureVersion($theFeatureRow['feature_id']);
						if (empty($theNewFeatureData['version_display'])) {
							$theNewFeatureData['version_display'] = 'v'.$theNewFeatureData['version_seq'];
						}
						$theFeatureRow['version_display_new'] = $theNewFeatureData['version_display'];
						$theFeatureRow['version_seq_new'] = $theNewFeatureData['version_seq'];
						
						if ($theFeatureRow['feature_id'] == $theWebsiteFeatureId &&
								$theFeatureRow['version_display'] != $theFeatureRow['version_display_new'] ||
								$theFeatureRow['version_seq'] != $theFeatureRow['version_seq_new'])
						{
							if (empty($theFeatureRow['version_display']))
								$theFeatureRow['version_display'] = $this->getRes('admin/field_value_unknown_version');
							if ($theFeatureRow['version_display'] == $theFeatureRow['version_display_new'])
							{
								$theFeatureRow['version_display'] .= ' (' . $theFeatureRow['version_seq'] . ')';
								$theFeatureRow['version_display_new'] .= ' (' . $theFeatureRow['version_seq_new'] . ')';
							}
						}
						
						$theFeatureRow['needs_update'] = ($theFeatureRow['version_display'] !=
								$theFeatureRow['version_display_new']);
						
						$theResultSet[$theFeatureRow['feature_id']] = $theFeatureRow;
					}
					//webapp version may be missing, specifically check and add if so
					$theWebappFeatureId = $this->getRes('website/getFeatureId');
					if (empty($theResultSet[$theWebappFeatureId])) {
						$theResultSet[$theWebappFeatureId]['feature_id'] = $theWebappFeatureId;
						$theResultSet[$theWebappFeatureId]['model_class'] = $this->mySimpleClassName;
						$theResultSet[$theWebappFeatureId]['version_seq'] = null;
						$theResultSet[$theWebappFeatureId]['version_seq_new'] = $this->getRes('website/version_seq');
						$theResultSet[$theWebappFeatureId]['version_display'] = $this->getRes('admin/field_value_unknown_version');
						$theResultSet[$theWebappFeatureId]['version_display_new'] = $this->getRes('website/version');
						$theResultSet[$theWebappFeatureId]['needs_update'] = true;
					}
				}
			} catch (PDOException $pdoe) {
				throw new DbException($pdoe,  __METHOD__.' failed.');
			}
		} else {
			$theResultSet = array( self::FEATURE_ID => array(
					'feature_id' => self::FEATURE_ID,
					'model_class' => $this->mySimpleClassName,
					'version_display' => $this->getRes('website/getFrameworkVersion/1'),
					'version_display_new' => $this->getRes('website/getFrameworkVersion/'.self::FEATURE_VERSION_SEQ),
			));
		}
		return $theResultSet;
	}
	
	/**
	 * Insert the feature into our table.
	 * @param $aDataObject - object containing data to be used on INSERT.
	 * @throws DbException
	 * @return Returns array(feature info) on success, else NULL.
	 */
	public function insertFeature($aDataObject) {
		$theResultSet = null;
		if ($this->isConnected()) try {
			$nowAsUTC = $this->utc_now();
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
			$theSql->startWith('INSERT INTO')->add($this->tnSiteVersions);
			$theSql->add('SET')->mustAddParam('created_ts', $nowAsUTC)->setParamPrefix(', ');
			$theSql->mustAddParam('updated_ts', $nowAsUTC);
			$theSql->mustAddParam('feature_id');
			$theSql->mustAddParam('model_class');
			$theSql->mustAddParam('version_seq', 1, PDO::PARAM_INT);
			$theSql->mustAddParam('version_display', 'v'.$theSql->getParam('version_seq'));
			//$this->debugLog($this->debugStr($theSql));
			if ($theSql->execDML()) {
				$theResultSet = $theSql->myParams;
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe,  __METHOD__.' failed.');
		}
		return $theResultSet;
	}
	
	/**
	 * Update an existing Feature record, version_seq is REQUIRED.
	 * @param $aDataObject - object containing data to be used on UPDATE.
	 * @throws DbException
	 * @return Returns array(device_id, name) on success, else NULL.
	 */
	public function updateFeature($aDataObject) {
		$theResultSet = null;
		if ($this->isConnected()) try {
			$nowAsUTC = $this->utc_now();
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
			$theSql->startWith('UPDATE')->add($this->tnSiteVersions);
			$theSql->add('SET')->mustAddParam('updated_ts', $nowAsUTC)->setParamPrefix(', ');
			$theSql->mustAddParam('version_seq');
			$theSql->mustAddParam('version_display', 'v'.$theSql->getParam('version_seq'));
			$theSql->addParam('model_class');
			$theSql->addFieldAndParam('feature_id', 'new_feature_id');
			$theSql->startWhereClause()->mustAddParam('feature_id')->endWhereClause();
			if ($theSql->execDML()) {
				$theResultSet = $theSql->myParams;
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe,  __METHOD__.' failed.');
		}
		return $theResultSet;
	}
	
	/**
	 * Using the data given, update the feature described.
	 * @param $aDataObject - object containing data to be used.
	 */
	public function upgradeFeature($aDataObject) {
		//$this->debugLog('v='.$this->debugStr($aDataObject->feature_id));
		if (!empty($aDataObject) && $this->exists()) {
			$theFeatureData = null;
			if (is_string($aDataObject)) {
				$theFeatureData = $this->getFeature($aDataObject);
			} else if (is_object($aDataObject)) {
				if (!empty($aDataObject->feature_data))
					$theFeatureData = $aDataObject->feature_data;
				else
					$theFeatureData = $this->getFeature($aDataObject->feature_id);
			} else if (is_array($aDataObject)) {
				if (!empty($aDataObject['feature_data']))
					$theFeatureData = $aDataObject['feature_data'];
				else
					$theFeatureData = $this->getFeature($aDataObject['feature_id']);
			}
			//$this->debugLog('feature='.$this->debugStr($theFeatureData));
			if (!empty($theFeatureData)) {
				try {
					$dbModel = $this->getProp($theFeatureData['model_class']);
					$dbModel->upgradeFeatureVersion($theFeatureData, $aDataObject);
					//if no exception occurs, all went well
					$this->updateFeature($dbModel->getCurrentFeatureVersion($theFeatureData['feature_id']));
					
					if (is_object($aDataObject) && is_callable(array($aDataObject,'addUserMsg'),true)) {
						$aDataObject->addUserMsg($this->getRes('admin/msg_update_success'));
					}
				} catch (Exception $e) {
					$this->debugLog($e->getMessage());
					if (is_object($aDataObject) && is_callable(array($aDataObject,'addUserMsg'),true)) {
						$aDataObject->addUserMsg($e->getMessage(), $aDataObject::USER_MSG_ERROR);
					}
				}
			}
		} else if (!empty($aDataObject)) {
			try {
				$this->setupModel();
				//$this->setupDefaultData($aDataObject);
				$this->upgradeFeatureVersion($this->getFeature(self::FEATURE_ID), $aDataObject);
				
				$this->refreshFeatureTable($aDataObject);
				
				if (is_object($aDataObject) && is_callable(array($aDataObject,'addUserMsg'))) {
					$aDataObject->addUserMsg($this->getRes('admin/msg_update_success'));
				}
			} catch (Exception $e) {
				$this->debugLog($e->getMessage());
				if (is_object($aDataObject) && is_callable(array($aDataObject,'addUserMsg'))) {
					$aDataObject->addUserMsg($e->getMessage(), $aDataObject::USER_MSG_ERROR);
				}
			}
		}
	}
	
	/**
	 * Update the features table in case there's new ones since last time it was run.
	 * Ususally performed on framework/website update.
	 * @param $aDataObject
	 */
	public function refreshFeatureTable($aDataObject) {
		//update the feature table
		$theModels = self::getAllModelClassInfo();
		$this->callModelMethod($this->director, $theModels, 'setupFeatureVersion', $aDataObject);
		array_walk($theModels, function(&$n) { unset($n); } );
		unset($theModels);
	}
	
}//end class

}//end namespace
