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

namespace BitsTheater\models;
use BitsTheater\Model as BaseModel;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\IFeatureVersioning;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use \PDO;
use \PDOException;
use \Exception;
{//namespace begin

class SetupDb extends BaseModel implements IFeatureVersioning {
	const FEATURE_ID = 'BitsTheater/framework';
	const FEATURE_VERSION_SEQ = 4; //always ++ when making db schema changes
		
	public $tnSiteVersions; const TABLE_SiteVersions = 'zz_versions';
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnSiteVersions = $this->tbl_.self::TABLE_SiteVersions;
	}
	
	public function setupModel() {
		switch ($this->dbType()) {
		case self::DB_TYPE_MYSQL: default:
			$theSql = "CREATE TABLE IF NOT EXISTS {$this->tnSiteVersions} ".
					"( feature_id CHAR(120) CHARACTER SET utf8 NOT NULL".
					", model_class CHAR(120) NOT NULL".
					", version_display CHAR(40) CHARACTER SET utf8 NULL".
					", version_seq INT(11) NOT NULL DEFAULT 0".
					", created_ts timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'row created ts'".
					", updated_ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'row updated ts'".
					", PRIMARY KEY (feature_id, model_class)".
					") CHARACTER SET utf8 COLLATE utf8_general_ci";
			try {
				$this->execDML($theSql);
				$this->debugLog('Create table (if not exist) "'.$this->tnSiteVersions.'" succeeded.');
			} catch (PDOException $pdoe) {
				throw new DbException($pdoe,$theSql);
			}
			break;
		}
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
			$theFrameworkVersion = $this->getRes('website/getFrameworkVersion/'.self::FEATURE_VERSION_SEQ);
			return array(
					'feature_id' => self::FEATURE_ID,
					'model_class' => $this->mySimpleClassName,
					'version_seq' => self::FEATURE_VERSION_SEQ,
					'version_display' => $theFrameworkVersion,
			);
		}
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
			$theSrcContents = file_get_contents($aSrcFilePath);
			if ($theSrcContents) {
				foreach ($aReplacements as $theReplacementName => $theReplacementValue) {
					$theSrcContents = str_replace('%'.$theReplacementName.'%', $theReplacementValue, $theSrcContents);
				}
				if (file_put_contents($aDestFilePath,$theSrcContents, LOCK_EX)===false) {
					$theMsg = $this->getRes('admin/msg_copy_cfg_fail/'.basename($aDestFilePath));
					throw new Exception($theMsg);
				}
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
		$theSeq = $aFeatureMetaData['version_seq'];
		if ($aFeatureMetaData['feature_id']!==self::FEATURE_ID) {
			//website update
			$this->getRes('website/updateVersion/'.$theSeq);
			//check for new features
			$this->refreshFeatureTable($aScene);
		} else {
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
	 * @param string $aFeature - the feature name
	 * @param string $aFieldList - (optional) which fields to return, default is all of them.
	 * @return array Returns the feature row as an array.
	 */
	public function getFeature($aFeatureId, $aFieldList=null) {
		$theResultSet = null;
		if ($this->isConnected()) try {
			$theSql = SqlBuilder::withModel($this)->setDataSet(array('feature_id' => $aFeatureId));
			$theSql->startWith('SELECT')->addFieldList($aFieldList)->add('FROM')->add($this->tnSiteVersions);
			$theSql->setParamPrefix(' WHERE ')->mustAddParam('feature_id');
			$theResultSet = $theSql->getTheRow();
			if (!empty($theResultSet) && empty($theResultSet['version_display'])) {
				$theResultSet['version_display'] = 'v'.$theResultSet['version_seq'];
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe,  __METHOD__.' failed.');
		}
		return $theResultSet;
	}
	
	
	/**
	 * @param string $aFieldList - which fields to return, default is all of them.
	 * @return array Returns all rows as an array.
	 */
	public function getFeatureVersionList($aFieldList=null) {
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
						$dbModel = $this->getProp($theFeatureRow['model_class']);
						if (empty($dbModel))
							continue;
						$theNewFeatureData = $dbModel->getCurrentFeatureVersion($theFeatureRow['feature_id']);
						if (empty($theNewFeatureData['version_display'])) {
							$theNewFeatureData['version_display'] = 'v'.$theNewFeatureData['version_seq'];
						}
						$theFeatureRow['version_display_new'] = $theNewFeatureData['version_display'];
						
						$theResultSet[$theFeatureRow['feature_id']] = $theFeatureRow;
					}
					//webapp version may be missing, specifically check and add if so
					$theWebappFeatureId = $this->getRes('website/getFeatureId');
					if (empty($theResultSet[$theWebappFeatureId])) {
						$theResultSet[$theWebappFeatureId]['feature_id'] = $theWebappFeatureId;
						$theResultSet[$theWebappFeatureId]['model_class'] = $this->mySimpleClassName;
						$theResultSet[$theWebappFeatureId]['version_seq'] = null;
						$theResultSet[$theWebappFeatureId]['version_display_new'] = $this->getRes('website/version');
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
			$theSql = SqlBuilder::withModel($this)->setDataSet($aDataObject);
			$theSql->startWith('INSERT INTO '.$this->tnSiteVersions);
			$theSql->add('SET created_ts=NOW()')->setParamPrefix(', ');
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
			$theSql = SqlBuilder::withModel($this)->setDataSet($aDataObject);
			$theSql->startWith('UPDATE '.$this->tnSiteVersions);
			$theSql->setParamPrefix(' SET ')->mustAddParam('version_seq');
			$theSql->setParamPrefix(', ')->mustAddParam('version_display', 'v'.$theSql->getParam('version_seq'));
			$theSql->addParam('model_class');
			$theSql->addFieldAndParam('feature_id', 'new_feature_id');
			$theSql->setParamPrefix(' WHERE ')->mustAddParam('feature_id');
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
