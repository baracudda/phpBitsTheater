<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
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
{//namespace begin

class Fragments extends BaseActor {
	const DEFAULT_ACTION = 'view';
	
	/**
	 * Protected from being rendered directly by a URL, other views can 
	 * call this method to obtain HTML snippets to build into their pages. 
	 * Typically used for dialogs, but this can be used for anything, really.
	 * Example use from another view:<pre>
	 * print($v->cueActor('Fragments', 'get', 'myfragmentfile_no_extension'));
	 * </pre>
	 * @param string $aFragmentFilename - fragment filename without the extension.
	 */
	protected function get($aFragmentFilename) {
		$this->renderThisView = (!empty($aFragmentFilename)) ? $aFragmentFilename : '_blank';
	}
	
	/**
	 * The public are not supposed to view fragments via URL, send them to the Home page.
	 */
	public function view() {
		return $this->scene->getHomePage();
	}
	
	/**
	 * Instead of merely returning data from an API post the results to this
	 * endpoint and have your custom HTML wrap the API results in your PHP template.
	 * @param string $aFragmentFilename - fragment filename without the extension.
	 */
	public function ajajGet($aFragmentFilename) {
		$this->renderThisView = (!empty($aFragmentFilename)) ? $aFragmentFilename : '_blank';
	}
	
}//end class

}//end namespace
