<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes ;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\colspecs\CommonMySql;
{ // begin namespace

/**
 * A set of methods useful when dealing with audit fields.
 */
trait WornForAuditFields
{
	/**
	 * If the table contains standard audit fields, use this mechanism with
	 * your SqlBuilder to provide a consistant way to fill them for INSERT.<br>
	 * NOTE: safe to use multiple times in a query, such as a MERGE query.
	 * @param SqlBuilder $aSqlBuilder - pass in an existing builder to fill the
	 *   fields properly.
	 * @return Returns the passed in SqlBuilder so chaining is possible.
	 */
	protected function addAuditFieldsForInsert(SqlBuilder $aSqlBuilder) {
		$nowAsUTC = $aSqlBuilder->myModel->utc_now();
		$fn = 'created_ts';
		$aSqlBuilder->mustAddFieldAndParam($fn, $aSqlBuilder->getUniqueDataKey($fn), $nowAsUTC);
		$aSqlBuilder->setParamPrefix(', ');
		$fn = 'updated_ts';
		$aSqlBuilder->mustAddFieldAndParam($fn, $aSqlBuilder->getUniqueDataKey($fn), $nowAsUTC);
		$fn = 'created_by';
		$aSqlBuilder->mustAddFieldAndParam($fn, $aSqlBuilder->getUniqueDataKey($fn),
				$aSqlBuilder->getDirector()->getMyUsername()
		);
		$fn = 'updated_by';
		$aSqlBuilder->mustAddFieldAndParam($fn, $aSqlBuilder->getUniqueDataKey($fn));
		return $aSqlBuilder;
	}
	
	/**
	 * If the table contains standard audit fields, use this mechanism with
	 * your SqlBuilder to provide a consistant way to fill them on UPDATE.<br>
	 * NOTE: safe to use multiple times in a query, such as a MERGE query.
	 * @param SqlBuilder $aSqlBuilder - pass in an existing builder to fill the
	 *   fields properly.
	 * @return Returns the passed in SqlBuilder so chaining is possible.
	 */
	protected function addAuditFieldsForUpdate(SqlBuilder $aSqlBuilder) {
		$nowAsUTC = $aSqlBuilder->myModel->utc_now();
		$fn = 'updated_ts';
		$aSqlBuilder->mustAddFieldAndParam($fn, $aSqlBuilder->getUniqueDataKey($fn), $nowAsUTC);
		$aSqlBuilder->setParamPrefix(', ');
		$fn = 'updated_by';
		$aSqlBuilder->mustAddFieldAndParam($fn, $aSqlBuilder->getUniqueDataKey($fn),
				$aSqlBuilder->getDirector()->getMyUsername()
		);
		return $aSqlBuilder;
	}
	
	/**
	 * If the table contains standard audit fields, use this mechanism with
	 * your SqlBuilder to provide a consistant way to fill them on Insert.<br>
	 * NOTE: "SET" will be prepended to the audit fields in the query.
	 * @param SqlBuilder $aSqlBuilder - pass in an existing builder to fill the
	 *   fields properly.
	 * @return Returns the passed in SqlBuilder so chaining is possible.
	 */
	protected function setAuditFieldsOnInsert(SqlBuilder $aSqlBuilder) {
		return $this->addAuditFieldsForInsert( $aSqlBuilder->setParamPrefix(' SET ') );
	}
	
	/**
	 * If the table contains standard audit fields, use this mechanism with
	 * your SqlBuilder to provide a consistant way to fill them on Insert.<br>
	 * NOTE: "SET" will be prepended to the audit fields in the query.
	 * @param SqlBuilder $aSqlBuilder - pass in an existing builder to fill the
	 *   fields properly.
	 * @return Returns the passed in SqlBuilder so chaining is possible.
	 */
	protected function setAuditFieldsOnUpdate(SqlBuilder $aSqlBuilder) {
		return $this->addAuditFieldsForUpdate( $aSqlBuilder->setParamPrefix(' SET ') );
	}
	
	/**
	 * Add audit fields to an existing table that lacks them.
	 * @param string $aTable - the fully qualified table name.
	 * @param number|string $aVersionNum - the version number for log entries.
	 * @param string $aAfterExistingFieldX - (optional) - place the audit
	 *   fields structurally after this one.
	 */
	protected function addAuditFieldsForTable($aTable, $aVersionNum,
			$aAfterExistingFieldX=null)
	{
		$theSql = SqlBuilder::withModel($this);
		if (!$this->isFieldExists('created_by', $aTable)) try {
			$theSql->startWith('ALTER TABLE '.$aTable);
			$theColDef = CommonMySql::CREATED_BY_SPEC;
			$theSql->add('  ADD COLUMN')->add($theColDef);
			if (!empty($aAfterExistingFieldX))
				$theSql->add('AFTER')->add($aAfterExistingFieldX);
			$theColDef = CommonMySql::UPDATED_BY_SPEC;
			$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER created_by');
			$theColDef = CommonMySql::CREATED_TS_SPEC;
			$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER updated_by');
			$theColDef = CommonMySql::UPDATED_TS_SPEC;
			$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER created_ts');
			$theSql->execDML();
			$this->debugLog("v{$aVersionNum}: added audit fields to {$aTable}");
		} catch (\Exception $e) {
			throw $theSql->newDbException(__METHOD__ . "({$aTable})", $e);
		} else {
			$this->debugLog("v{$aVersionNum}: {$aTable} already updated.");
		}
	}
	
} // end trait

} // end namespace
