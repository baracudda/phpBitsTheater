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

namespace BitsTheater\costumes\colspecs;
{//namespace begin

	/**
	 * Common column specifications using MySQL syntax.
	 *
	 *
	 * 		 * We cannot construct a const from other consts, so cannot use concatonation here!!!
	 *
	 *
	 */
	class CommonMySql
	{
		/**
		 * Use this to specify primary and foreign key columns
		 * @var string
		 */
		const TYPE_UUID = 'char(36) CHARACTER SET ascii COLLATE ascii_bin' ;
		/**
		 * Boolean style field defaulting to FALSE.
		 * @var string
		 */
		const TYPE_BOOLEAN_0 = 'TINYINT(1) NOT NULL DEFAULT 0' ;
		/**
		 * Boolean style field defaulting to TRUE.
		 * @var string
		 */
		const TYPE_BOOLEAN_1 = 'TINYINT(1) NOT NULL DEFAULT 1' ;


		/**
		 * Dummy time to use for non-null timestamp columns.
		 * @var string
		 */
		const DUMMY_TIME = '1970-01-01 00:00:01' ;


		/**
		 * Fully-defines a column for tracking a row's creation time.
		 * We cannot construct a const from other consts, so cannot use DUMMY_TIME here.
		 * We supply a default value to avoid INSERT errors.
		 * @var string
		 */
		const CREATED_TS_SPEC =
				'created_ts timestamp NOT NULL DEFAULT \'1970-01-01 00:00:01\'' ;
		/**
		 * Fully-defines a column for tracking a row's update time.
		 * In MySQL, only one column may use the CURRENT_TIMESTAMP default.
		 * @var string
		 */
		const UPDATED_TS_SPEC =
				'updated_ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' ;
		/**
		 * User that created a record may be recorded.
		 * @var string
		 */
		const CREATED_BY_SPEC =
				"`created_by` varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NULL" ;
		/**
		 * User that updated a record may be recorded.
		 * @var string
		 */
		const UPDATED_BY_SPEC =
				"`updated_by` varchar(60) CHARACTER SET utf8 COLLATE utf8_general_ci NULL" ;
		/**
		 * Version of this current record
		 * @var string
		 */
		const VERSION_NUM_SPEC =
				"`version_num` INT(11) NULL" ;
		/**
		 * UUID of the record that replaced this one
		 * @var string
		 */
		const VERSION_REPLACED_BY_SPEC =
				"`replaced_by` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL" ;


		/**
		 * Insert standard audit fields into a table definition.
		 * @return string
		 */
		static function getAuditFieldsForTableDefSql() {
			return
					self::CREATED_BY_SPEC.', '.
					self::UPDATED_BY_SPEC.', '.
					self::CREATED_TS_SPEC.', '.
					self::UPDATED_TS_SPEC
			;
		}
		/**
		 * Insert standard versioning fileds into a table definition.
		 * @return string
		 */
		static function getVersioningFieldsForTableDefSql()
		{
			return
					self::VERSION_NUM_SPEC.', '.
					self::VERSION_REPLACED_BY_SPEC;
		}


	}//end class

}//end namespace
