<?php
namespace BitsTheater\costumes ;
use BitsTheater\costumes\SqlBuilder;
{ // begin namespace

/**
 * A set of methods useful when dealing with audit fields.
 */
trait WornForAuditFields
{
	/**
	 * If the table contains standard audit fields, use this mechanism with
	 * your SqlBuilder to provide a consistant way to fill them on Insert.
	 * @param SqlBuilder $aSqlBuilder - pass in an existing builder to fill the
	 *   fields properly.
	 * @return Returns the passed in SqlBuilder so chaining is possible.
	 */
	protected function setAuditFieldsOnInsert(SqlBuilder $aSqlBuilder) {
		$nowAsUTC = $aSqlBuilder->myModel->utc_now();
		$aSqlBuilder->add('SET')->mustAddParam('created_ts', $nowAsUTC)->setParamPrefix(', ');
		$aSqlBuilder->mustAddParam('updated_ts', $nowAsUTC);
		$aSqlBuilder->mustAddParam('created_by', $aSqlBuilder->getDirector()->getMyUsername());
		//$aSqlBuilder->mustAddParam('updated_by'); //leaving blank/db default
		return $aSqlBuilder;
	}
	
	/**
	 * If the table contains standard audit fields, use this mechanism with
	 * your SqlBuilder to provide a consistant way to fill them on Insert.
	 * @param SqlBuilder $aSqlBuilder - pass in an existing builder to fill the
	 *   fields properly.
	 * @return Returns the passed in SqlBuilder so chaining is possible.
	 */
	protected function setAuditFieldsOnUpdate(SqlBuilder $aSqlBuilder) {
		$nowAsUTC = $aSqlBuilder->myModel->utc_now();
		$aSqlBuilder->add('SET')->mustAddParam('updated_ts', $nowAsUTC)->setParamPrefix(', ');
		$aSqlBuilder->mustAddParam('updated_by', $aSqlBuilder->getDirector()->getMyUsername());
		return $aSqlBuilder;
	}
	
} // end trait

} // end namespace
