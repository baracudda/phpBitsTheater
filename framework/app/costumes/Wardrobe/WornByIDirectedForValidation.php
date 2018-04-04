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
{//begin namespace

trait WornByIDirectedForValidation
{
	/**
	 * Throw an exception if the argument is empty. Advantage to using this method
	 * is that empty() requires its parameter to be a variable, whereas this method
	 * can take a function result.
	 * @param string $aArgName - name to insert into the Exception message if needed.
	 * @param string $aArgValue - value to check for being not
	 *   <span style="font-family:monospace">empty()</span>.
	 * @return $this Returns $this for chaining purposes.
	 * @throws \BitsTheater\BrokenLeg::ACT_MISSING_ARGUMENT if empty() returns TRUE.
	 */
	public function checkIsNotEmpty($aArgName, $aArgValue)
	{
		if ( empty($aArgValue) )
			throw \BitsTheater\BrokenLeg::toss($this,
					\BitsTheater\BrokenLeg::ACT_MISSING_ARGUMENT, $aArgName);
		return $this;
	}
		
}//end trait

}//end namespace