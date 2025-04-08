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
use BitsTheater\costumes\APIResponse;
use BitsTheater\costumes\SiteUpdater ;
use BitsTheater\costumes\WornForCLI;
use BitsTheater\models\SetupDb as MetaModelProp;
use BitsTheater\BrokenLeg;
use Exception;
{//namespace begin

class BitsAdmin extends BaseActor
{
	use WornForCLI;
	
	const DEFAULT_ACTION = 'websiteStatus';
	
	/**
	 * @return MetaModelProp Returns the database model reference.
	 */
	protected function getMetaModel()
	{ return (fn($model):MetaModelProp=>$model)($this->getProp(MetaModelProp::MODEL_NAME)) ; }

	/**
	 * Gives the name of the model that should be used to upgrade the site.
	 * A class that overrides BitsAdmin can override this method to provide an
	 * alternative model.
	 * @return string the model to be used for site upgrades
	 * @since BitsTheater v4.0.0
	 */
	protected function getUpdateModelName(): string
	{ return $this->getMetaModel()::MODEL_NAME; }

	/**
	 * Webpage endpoint.
	 */
	public function websiteStatus()
	{
		if (!$this->isAllowed('config', 'modify')) {
			return $this->getSiteURL();
		}
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		$v->addUserMsg($this->getRes('admin/msg_warning_backup_db'), $v::USER_MSG_WARNING);
		$dbMeta = $this->getMetaModel();
		$dbMeta->refreshFeatureTable($this->getMyScene());
		$v->feature_version_list = $dbMeta->getFeatureVersionList();
	}
	
	/**
	 * Update a specific feature.
	 */
	public function ajajUpdateFeature()
	{
		if ( $this->checkAllowed( 'config', 'modify' ) )
		{
			$v = $this->getMyScene();
			$theUpdater = new SiteUpdater($this, $v, $this->getUpdateModelName());
			try {
				$this->setApiResults($theUpdater->upgradeFeature());
			}
			catch( Exception $x ) {
				$v->addUserMsg( $x->getMessage(), $v::USER_MSG_ERROR ) ;
				throw BrokenLeg::tossException( $this, $x ) ;
			}
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
			throw BrokenLeg::toss($this, BrokenLeg::ACT_PERMISSION_DENIED);
	}
	
	/**
	 * API for getting the feature version list.
	 */
	public function ajajGetFeatureVersionList() {
		if ( $this->checkAllowed('config', 'modify') ) {
			$dbMeta = $this->getMetaModel();
			try {
				$this->setApiResults($dbMeta->getFeatureVersionList());
			} catch (Exception $e) {
				throw BrokenLeg::tossException($this, $e);
			}
		}
	}
	
	/**
	 * API for updating all features of the website.
	 */
	public function apiWebsiteUpgrade()
	{
		$v =& $this->scene ;
		$this->checkAllowed( 'config', 'modify' ) ;
		try
		{
			$theUpdater = new SiteUpdater(
					$this, $v, $this->getUpdateModelName() ) ;
			$theUpdater->upgradeAllFeatures() ;
			$v->results = APIResponse::noContentResponse() ;
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}
 
}//end class

}//end namespace
