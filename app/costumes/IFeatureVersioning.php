<?php
namespace BitsTheater\costumes;
{//begin namespace

interface IFeatureVersioning {
	/**
	 * Returns the current feature metadata for the given feature ID.
	 * @param string $aFeatureId - the feature ID needing its current metadata.
	 * @return array Current feature metadata.
	 */
	public function getCurrentFeatureVersion($aFeatureId=null);
	
	/**
	 * Other models may need to query ours to determine our version number
	 * during Site Update. Without checking SetupDb, determine what version
	 * we may be running as.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	//public function determineExistingFeatureVersion($aScene);
	//TODO not ready to bite the bullet and force this method, yet.
	
	/**
	 * Meta data may be necessary to make upgrades-in-place easier. Check for
	 * existing meta data and define if not present.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function setupFeatureVersion($aScene);
	
	/**
	 * Check current feature version and compare it to the
	 * current version, upgrading the db schema as needed.
	 * @param array $aFeatureMetaData - the models current feature metadata.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function upgradeFeatureVersion($aFeatureMetaData, $aScene);
	
}

}//end namespace