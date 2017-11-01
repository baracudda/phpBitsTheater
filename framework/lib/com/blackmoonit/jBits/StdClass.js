/*
 * Copyright (C) 2008 John Resig
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
 * FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

/* Simple JavaScript Inheritance
 * By John Resig http://ejohn.org/
 * MIT Licensed.
 */
// Inspired by base2 and Prototype
// changes by Ryan Fischbach: Class -> StdClass, init -> onCreate
(function(){
	var bLoadClassDef = false, fnTest = /xyz/.test(function(){xyz;}) ? /\b_super\b/ : /.*/;

	// The base StdClass implementation (does nothing)
	this.StdClass = function(){};
	
	// Create a new StdClass that inherits from this class
	StdClass.extend = function(prop) {
		var _super = this.prototype;

		// Instantiate a base class (but only create the instance,
		// don't run the onCreate constructor)
		bLoadClassDef = true;
		var prototype = new this();
		bLoadClassDef = false;

		// Copy the properties over onto the new prototype
		for (var name in prop) {
			// Check if we're overwriting an existing function
			prototype[name] = typeof prop[name] === "function" &&
				typeof _super[name] === "function" && fnTest.test(prop[name]) ?
					(function(name, fn){
						return function() {
							var tmp = this._super;

							// Add a new ._super() method that is the same method
							// but on the super-class
							this._super = _super[name];

							// The method only need to be bound temporarily, so we
							// remove it when we're done executing
							var ret = fn.apply(this, arguments);
							this._super = tmp;

							return ret;
						};
					})(name, prop[name]) :
					prop[name];
		}

		// The dummy class constructor
		function StdClass() {
			// All construction is actually done in the onCreate method
			if ( !bLoadClassDef && this.onCreate )
				this.onCreate.apply(this, arguments);
		}

		// Populate our constructed prototype object
		StdClass.prototype = prototype;

		// Enforce the constructor to be what we expect
		StdClass.prototype.constructor = StdClass;

		// And make this class extendable
		StdClass.extend = arguments.callee;

		return StdClass;
	};
})();
