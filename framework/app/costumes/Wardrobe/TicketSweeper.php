<?php
/*
 * Copyright (C) 2023 Blackmoon Info Tech Services
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
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\AuthPasswordReset;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\models\Auth as AuthModel;
{//namespace begin

/**
 * A cron job will run the TicketSweeper every few minutes to clean up
 * stale tokens. It may also be used for other things that need to occur
 * every few minutes as well.
 */
class TicketSweeper extends BaseCostume
{
	
	protected function getAuthModel(): AuthModel
	{ return (fn($model):AuthModel=>$model)($this->getProp(AuthModel::MODEL_NAME)); }
	
	/**
	 * A cron job that runs every few minutes will call this method to clean up
	 * tokens and whatever else it needs to do on a "every few min" basis.
	 */
	public function onCheckTicketsPollInterval()
	{
		$this->getLogger()->withInfo(
			'remove stale tokens'
		)->log();
		if ( $this->getDirector()->isRunningUnderCLI() ) {
			print('remove stale tokens');
		}
		$dbAuth = $this->getAuthModel();
		try {
			$dbAuth->removeStaleAuthLockoutTokens();

			$dbAuth->removeStaleAntiCsrfTokens();

			$dbAuth->removeStaleRegistrationCapTokens();

			$pwResetFreshness = 3;
			$dbAuth->removeStaleTokens(AuthPasswordReset::TOKEN_PREFIX.'%', $pwResetFreshness.' DAY');
			
			//Moving used cookie GC here instead of within removeStaleCookies() to avoid MySQL deadlock
			//NOTE: in order to support multiple simultaneous request ops, each referencing
			//  the same valid cookie at that time, we don't DELETE cookie tokens immediately.
			//  Instead we mark them so they will be removed "very soon" so that all the
			//  simultaneous requests will succeed, at the cost of recycling through several
			//  cookie tokens. A small price to pay for a better user experience for multi-
			//  threaded webapps.
			$theFilter = SqlBuilder::withModel($dbAuth)
				->mustAddParam('account_id', 0, \PDO::PARAM_INT)
			;
			//remove any cookie tokens marked for garbage collection (account_id = 0)
			$dbAuth->removeStaleTokens($dbAuth::TOKEN_PREFIX_COOKIE.'%', '5 MINUTE', $theFilter);
		}
		catch ( \PDOException $pdox ) {
			$logger = $this->getLogger();
			$logger->withInfo([
					'method' => __METHOD__,
					'action' => 'remove stale tokens',
					'err_msg' => $pdox->getMessage(),
					'message' => 'error during GC, warn and try again next time',
			])->logAs($logger::LOG_WARNING);
			if ( $this->getDirector()->isRunningUnderCLI() ) {
				print('Exception: '.$pdox->getMessage());
			}
		}
	}
	
}//end class

}//end namespace
