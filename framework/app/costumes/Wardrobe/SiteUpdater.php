<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ABitsCostume as BaseCostume ;
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\WornByModel;
use BitsTheater\costumes\WornForCLI;
{//namespace begin

/**
 * Costume to centralize the site upgrade algorithm. This may be called from a
 * REST API actor or from a CLI actor.
 * @since BitsTheater [NEXT]
 * @see BitsTheater\actors\Understudy\BitsAdmin
 */
class SiteUpdater extends BaseCostume
{ use WornByModel, WornForCLI ;

	protected $myUpgradeData ;
	
	/**
	 * Constructs an instance.
	 * @param IDirected $aContext the context from which resources are available
	 * @param array|object $aDataObject an object from which to draw inputs
	 * @param string $aModelName an alternative upgrade model, if desired
	 *  (defaults to the canonical <code>SetupDb</code>)
	 */
	public function __construct( IDirected $aContext, $aDataObject=null, $aModelName='SetupDb' )
	{
		$this->setup($aContext) ;
		$this->setUpgradeData( $aDataObject ) ;
		$this->setModel( $this->getProp($aModelName) ) ;
	}

	/**
	 * Mutator for the data to be used as input to the upgrade process. There is
	 * no corresponding accessor.
	 * @param array|object $aObject an object from which to draw upgrade data
	 * @return \BitsTheater\costumes\SiteUpdater $this
	 */
	public function setUpgradeData( $aObject )
	{ $this->myUpgradeData = ((object)($aObject)) ; return $this ; }
	
	/**
	 * Upgrades a single site feature.
	 * @return null (the underlying method has no return statements)
	 */
	public function upgradeFeature()
	{ return $this->model->upgradeFeature( $this->myUpgradeData ) ; }
	
	/**
	 * Upgrades all features of the site.
	 */
	public function upgradeAllFeatures()
	{
		$theData = clone $this->myUpgradeData ; // algorithm is destructive
		$this->model->refreshFeatureTable($theData) ;
		$theFeatureList = $this->model->getFeatureVersionList() ;
		
		$this->upgradeFramework( $theFeatureList, $theData ) ;
		$this->upgradeSiteVersion( $theFeatureList, $theData ) ;
		
		//ensure that Auth model gets updated first.
		$dbAuth = $this->getProp('Auth');
		$theFeatureData = $theFeatureList[$dbAuth::FEATURE_ID];
		unset($theFeatureList[$dbAuth::FEATURE_ID]);
		array_unshift($theFeatureList, $theFeatureData);

		foreach( $theFeatureList as $theFeatureData )
		{ // Use remaining entries to upgrade other site features.
			$theFeatureInfo = ((object)($theFeatureData)) ;
			if( $theFeatureInfo->needs_update || $theData->force_model_upgrade )
			{
				$theData->feature_id = $theFeatureInfo->feature_id ;
				if( $theData->force_model_upgrade )
				{
					$this->model->removeFeature( $theData->feature_id ) ;
					$this->model->refreshFeatureTable($theData) ;
				}
				$this->model->upgradeFeature($theData) ;
				if( $this->isRunningUnderCLI() ) print(PHP_EOL) ;
			}
			else if( $this->isRunningUnderCLI() )
				$this->printFeatureUpToDate( $theFeatureInfo->feature_id ) ;
		}
		
		if ($this->isRunningUnderCLI())
		{ print( $this->getRes('admin', 'msg_cli_check_for_missing') . PHP_EOL ); }
		//after updating all known features, create any missing ones.
		$this->model->setupModels($theData) ;
	}
	
	/**
	 * Upgrades the framework if needed.
	 * @param array $aFeatureList a list of site features
	 * @param object $aDataClone a clone of the upgrade input data
	 * @return mixed the returned value of
	 *  <code>upgradeFundamentalFeature()</code>. The base class's method is
	 *  void, but an override might want to return data
	 */
	protected function upgradeFramework( $aFeatureList, $aDataClone )
	{ // Obtain a static constant using an instance of the model class.
		return $this->upgradeFundamentalFeature( $aFeatureList, $aDataClone,
				constant( get_class($this->model)  . '::FEATURE_ID' ) ) ;
	}
	
	/**
	 * Upgrades the website to a new version if needed.
	 * @param array $aFeatureList a list of site features
	 * @param object $aDataClone a clone of the upgrade input data
	 * @return mixed the returned value of
	 *  <code>upgradeFundamentalFeature()</code>. The base class's method is
	 *  void, but an override might want to return data
	 */
	protected function upgradeSiteVersion( $aFeatureList, $aDataClone )
	{
		return $this->upgradeFundamentalFeature( $aFeatureList, $aDataClone,
				$this->getRes( 'website/getFeatureId' ) ) ;
	}
	
	/**
	 * Upgrades some fundamental feature of the site.
	 * @param array $aFeatureList a list of site features
	 * @param object $aDataClone a clone of the upgrade input data
	 * @param string $aFeatureID the fundamental feature to be updated
	 * @return null (void)
	 */
	protected function upgradeFundamentalFeature( $aFeatureList, $aDataClone, $aFeatureID )
	{
		if( $aFeatureList[$aFeatureID]['needs_update'] )
		{
			$aDataClone->feature_id = $aFeatureID ;
			$this->model->upgradeFeature( $aDataClone ) ;
		}
		else if( $this->isRunningUnderCLI() )
			$this->printFeatureUpToDate( $aFeatureID ) ;
		unset( $aFeatureList[$aFeatureID] ) ;
	}
	
	/**
	 * When operating as part of a CLI script, use this function to print a
	 * status message indicating that the feature is already up-to-date.
	 * @param string $aFeatureID the feature ID
	 */
	protected function printFeatureUpToDate( $aFeatureID )
	{
		print( $this->getRes('admin', 'msg_cli_feature_up_to_date', $aFeatureID)
			. PHP_EOL
			);
	}
	
}//end class

}//end namespace
