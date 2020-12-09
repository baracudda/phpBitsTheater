<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\CursorCloset\ARecordList as BaseCostume;
use BitsTheater\costumes\AuthGroup as MyRecord;
use BitsTheater\models\AuthGroups as MyModel;
use BitsTheater\models\Auth as AuthModel;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a list of auth groups.
 *
 * <pre>
 * $theSet = AuthGroupList::create($this)
 *     ->setListOfIds($theListToUse)
 *     ;
 * </pre>
 */
class AuthGroupList extends BaseCostume
{
	/** @var string The name of the class to contain items. */
	const DEFAULT_ITEM_CLASS = MyRecord::ITEM_CLASS;
	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @var string
	 */
	const MY_MODEL_CLASS = MyModel::MODEL_NAME;
	
	/**
	 * Name of the ID field to use.
	 * @return string
	 */
	protected function getIdFieldName()
	{ return 'group_id'; }
	
	/** @return MyModel */
	protected function getMyModel()
	{ return $this->getModel(); }
	
	/** @return AuthModel */
	protected function getAuthModel()
	{ return $this->getModel()->getProp(AuthModel::MODEL_NAME); }
	
	/**
	 * Name of the table where to get the record from.
	 * @return string
	 */
	protected function getIdTableName()
	{ return $this->getMyModel()->tnGroups; }
	
	/**
	 * Instead of returning all groups lists, only return the ones
	 * for my current Org and its children.
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\CursorCloset\ARecordList::createSqlQuery()
	 */
	protected function createSqlQuery( $aListOfIds )
	{
		$theSql = parent::createSqlQuery($aListOfIds);
		$theCurrOrgID = $this->getDirector()->getPropsMaster()->getDefaultOrgID();
		if ( !empty($theCurrOrgID) && $theCurrOrgID != AuthModel::ORG_ID_4_ROOT ) {
			$theOrgIDs = array();
			$theOrgRows = $this->getAuthModel()->getOrgChildrenForOrgCursor($theCurrOrgID, array('org_id'));
			$theOrgIDs = $theOrgRows->fetchAll(\PDO::FETCH_COLUMN);
			$theSql->startWhereClause()
				->setParamPrefix(' AND ')
				->mustAddParam('org_id', $theOrgIDs)
				->endWhereClause()
				;
		}
		//$theSql->logSqlDebug(__METHOD__); //DEBUG
		return $theSql;
	}
	
}//end class

}//end namespace
