/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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

/**
 * JavaScript utility functions.
 */
function selectElement(aId) {
	if (typeof aId!=='undefined') {
		var r = document.createRange();
		var e = document.getElementById(aId);
		var s = window.getSelection();
		r.selectNode(e);
		s.removeAllRanges();
		s.addRange(r);
	}
}

function jump_to_top() {
	$('html, body').animate({ scrollTop: 0 }, 'fast');
}

function zulu_to_local(id, phpTs) {
	var sts = new Date(phpTs*1000), s = sts.toLocaleString();
	document.getElementById(id).innerHTML = s;
}

