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
var BaseClass = StdClass.extend({
	onCreate: function() {
		//defined just to avoid a crash if you call _super() during onCreate
	}
});

/**
 * Since JS cannot handle default values in the function definition,
 * this function will return the arg or the default if arg is undefined.
 * @param aArg - the argument
 * @param aDef - the default
 * @returns Arg unless it is undefined, else Default.
 */
BaseClass.prototype.argOrDefault = function(aArg, aDef) {
	return ((typeof aArg) !== 'undefined') ? aArg : aDef;
};

/**
 * Helper function to bind methods as callback functions.
 * @param aMethod - name of method to call, or function itself
 * @returns {Function} Returns the anonymous function used as a callback.
 */
BaseClass.prototype.asCB = function(aMethod) {
	var thisone = this;
	var theFn = ((typeof aMethod) === 'function') ? aMethod : this[aMethod];
	return function() {
		return theFn.apply(thisone,arguments);
	};
};
BaseClass.prototype.bind = BaseClass.prototype.asCB; //alias

/**
 * Helper function to bind methods as callback functions.
 * @param aContext - the "this" var the method needs.
 * @returns {Function} Returns the anonymous function used as a callback.
 */
Function.prototype.asCB = function(aContext) {
	var theFn = this;
	return function() {
		return theFn.apply(aContext,arguments);
	};
};

/**
 * Pseudo-UUID, random + time "version 4" generated UUID.
 * @param {string} aGlue - (optional) defaults to "-".
 * @returns {String} v4 UUID.
 * @see http://stackoverflow.com/questions/105034/how-to-create-a-guid-uuid-in-javascript
 */
BaseClass.prototype.createUUID = function(aGlue) {
	var g = this.argOrDefault(aGlue,'-');
	var d = new Date().getTime();
	var s = 'xxxxxxxx'+g+'xxxx'+g+'4xxx'+g+'yxxx'+g+'xxxxxxxxxxxx';
	var uuid = s.replace(/[xy]/g, function(c) {
		var r = (d + Math.random()*16)%16 | 0;
		d = Math.floor(d/16);
		return (c==='x' ? r : (r&0x7|0x8)).toString(16);
	});
	return uuid;
};
