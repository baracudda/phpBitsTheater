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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\Scene as MyScene; /* @var $v MyScene */
use BitsTheater\models\SetupDb; /* @var $dbMeta SetupDb */
use com\blackmoonit\Strings;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use Exception;
{//namespace begin

class BitsAdmin extends BaseActor {
	const DEFAULT_ACTION = 'websiteStatus';

	/**
	 * Webpage endpoint.
	 */
	public function websiteStatus() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//auth
		if (!$this->isAllowed('config', 'modify')) {
			return $this->getSiteURL();
		}
		
		$v->addUserMsg($v->getRes('admin/msg_warning_backup_db'),$v::USER_MSG_WARNING);
		$dbMeta = $this->getProp('SetupDb');
		$v->feature_version_list = $dbMeta->getFeatureVersionList();
	}
	
	/**
	 * Update a specific feature.
	 */
	public function ajajUpdateFeature() {
		$v =& $this->scene;
		if ($this->isAllowed('config', 'modify')) {
			$dbMeta = $this->getProp('SetupDb');
			try {
				$v->results = APIResponse::resultsWithData($dbMeta->upgradeFeature($v));
			} catch (Exception $e) {
				$v->addUserMsg($e->getMessage(), $v::USER_MSG_ERROR);
				throw BrokenLeg::tossException($this, $e);
			}
		} else {
			throw BrokenLeg::toss($this, 'FORBIDDEN');
		}
	}
	
	/**
	 * Create any missing tables.
	 */
	public function ajajResetupDb() {
		$v =& $this->scene;
		$dbAccounts = $this->getProp('Accounts');
		if ( $this->getDirector()->isInstalled() && (
				!$dbAccounts->isExists($dbAccounts->tnAccounts) ||
				$dbAccounts->isEmpty($dbAccounts->tnAccounts) ||
				$this->isAllowed('config','modify')
			) )
		{
			$dbMeta = $this->getProp('SetupDb');
			try {
				$v->results = APIResponse::resultsWithData($dbMeta->setupModels($v));
			} catch (Exception $e) {
				throw BrokenLeg::tossException($this, $e);
			}
		} else
			throw BrokenLeg::toss($this, 'FORBIDDEN');
	}
	
	/**
	 * API for getting the feature version list.
	 */
	public function ajajGetFeatureVersionList() {
		$v =& $this->scene;
		if ($this->isAllowed('config', 'modify')) {
			$dbMeta = $this->getProp('SetupDb');
			try {
				$v->results = APIResponse::resultsWithData($dbMeta->getFeatureVersionList());
			} catch (Exception $e) {
				throw BrokenLeg::tossException($this, $e);
			}
		} else
			throw BrokenLeg::toss($this, 'FORBIDDEN');
	}
	
}//end class

}//end namespace

