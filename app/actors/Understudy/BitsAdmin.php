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
use BitsTheater\costumes\WornForCLI;
use BitsTheater\models\SetupDb; /* @var $dbMeta SetupDb */
use com\blackmoonit\Strings;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use Exception;
{//namespace begin

class BitsAdmin extends BaseActor
{
	use WornForCLI;
	
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
		$dbMeta->refreshFeatureTable($v);
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
	
	/**
	 * API for CLI version of websiteStatus() page.
	 */
	public function apiWebsiteUpgrade() {
		$v =& $this->scene;
		if ( !$this->isAllowed( 'config', 'modify' ) ) {
			$myAcctID = $this->getMyAccountID();
			if ($this->isRunningUnderCLI() && empty($myAcctID))
				print($this->getRes('generic/errmsg_cli_not_authed') . PHP_EOL);
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
		}
		try {
			//if ($this->isRunningUnderCLI())
			//	print($this->getRes('admin/msg_warning_backup_db') . PHP_EOL);
			$dbMeta = $this->getProp('SetupDb');
			
			$dbMeta->refreshFeatureTable($v);
			$theFeatureList = $dbMeta->getFeatureVersionList();
			
			//does the framework need updating?
			$v->feature_id = $dbMeta::FEATURE_ID;
			if ($theFeatureList[$v->feature_id]['needs_update']) {
				$dbMeta->upgradeFeature($v);
			}
			else if ($this->isRunningUnderCLI())
			{
				print( Strings::format($this->getRes('admin/msg_cli_feature_up_to_date'),
						$v->feature_id) . PHP_EOL
				);
			}
			unset($theFeatureList[$v->feature_id]);
			
			//does the website itself need updating?
			$v->feature_id = $this->getRes('website/getFeatureId');
			if ($theFeatureList[$v->feature_id]['needs_update']) {
				$dbMeta->upgradeFeature($v);
			}
			else if ($this->isRunningUnderCLI())
			{
				print( Strings::format($this->getRes('admin/msg_cli_feature_up_to_date'),
						$v->feature_id) . PHP_EOL
				);
			}
			unset($theFeatureList[$v->feature_id]);
			
			//check the model list for neccessary upgrading
			foreach ($theFeatureList as $theFeatureInfo)
			{
				$v->feature_id = $theFeatureInfo['feature_id'];
				if ($theFeatureInfo['needs_update'] || $v->force_model_upgrade)
				{
					if ($v->force_model_upgrade)
					{
						$dbMeta->removeFeature($v->feature_id);
						$dbMeta->refreshFeatureTable($v);
					}
					$dbMeta->upgradeFeature($v);
					if ($this->isRunningUnderCLI())
						print( PHP_EOL );
				}
				else if ($this->isRunningUnderCLI())
				{
					print( Strings::format($this->getRes('admin/msg_cli_feature_up_to_date'),
							$v->feature_id) . PHP_EOL
					);
				}
			}//end foreach
			
			//after updating all known features, create any missing ones.
			$dbMeta->setupModels($v);
			
			$v->results = APIResponse::noContentResponse();
		}
		catch (Exception $e)
		{ throw BrokenLeg::tossException($this, $e); }
	}
   	
}//end class

}//end namespace

