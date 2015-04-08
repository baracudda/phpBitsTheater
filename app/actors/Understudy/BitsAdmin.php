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
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\database\DbUtils;
use BitsTheater\models\SetupDb; /* @var $dbMeta SetupDb */
use com\blackmoonit\Strings;
use \PDOException;
{//namespace begin

class BitsAdmin extends BaseActor {
	const DEFAULT_ACTION = 'websiteStatus';

	/**
	 * URL: %site%/admin/ajaxUpdateDb
	 */
	private function updateEntireDb() {
		$theSetupDb = $this->getProp('SetupDb');
		$theSetupDb->setupModels($this->scene);
	}
	
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
	
	public function ajaxUpdateFeature() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$this->viewToRender('_blank');
		//auth
		if ($this->isAllowed('config', 'modify')) {
			$dbMeta = $this->getProp('SetupDb');
			$dbMeta->upgradeFeature($v);
		}
	}
	
	public function ajaxResetupDb() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$this->viewToRender('_blank');
		//auth
		if ($this->isAllowed('config', 'modify')) {
			$dbMeta = $this->getProp('SetupDb');
			$dbMeta->setupModels($v);
		}
	}
	
}//end class

}//end namespace

